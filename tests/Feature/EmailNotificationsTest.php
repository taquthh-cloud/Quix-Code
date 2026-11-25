<?php

use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\OrderChat;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Notifications\ChatDigestNotification;
use App\Notifications\DownPaymentInvoiceNotification;
use App\Notifications\QuotationExpiringNotification;
use App\Notifications\QuotationSentNotification;
use App\Services\ChatDigestService;
use App\Services\Notifier;
use App\Services\QuotationService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification as NotificationFacade;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-12-10 10:00:00'));
    app()->setLocale('id');

    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);

    $this->makeQuotation = function (string $state = 'sent', array $overrides = []): Quotation {
        $factory = Quotation::factory()->for($this->project);
        $factory = $state === 'draft' ? $factory : $factory->{$state}();
        $quotation = $factory->create($overrides);
        QuotationItem::factory()->for($quotation)->create([
            'title' => 'Sistem inti', 'hours' => 100, 'rate' => 200_000, 'amount' => 20_000_000, 'sort_order' => 0,
        ]);

        return $quotation;
    };

    /** Pesan chat dengan umur tertentu (menit). */
    $this->chat = fn (User $sender, int $minutesAgo, array $overrides = []): OrderChat => OrderChat::factory()->create([
        'project_id' => $this->project->id,
        'sender_id' => $sender->id,
        'created_at' => now()->subMinutes($minutesAgo),
        ...$overrides,
    ]);
});

afterEach(fn () => $this->travelBack());

// ------------------------------------------------------------------ quotation terkirim

it('mengirim quotation mengirim email ke klien saja dan mencatatnya di log', function () {
    NotificationFacade::fake();

    $quotation = ($this->makeQuotation)('draft');

    app(QuotationService::class)->send($quotation, $this->admin);

    NotificationFacade::assertSentTo($this->client, QuotationSentNotification::class, fn ($n) => $n->quotation->id === $quotation->id);
    NotificationFacade::assertNotSentTo([$this->admin, $this->staff], QuotationSentNotification::class);

    $log = NotificationLog::firstOrFail();

    expect(NotificationLog::count())->toBe(1)
        ->and($log->user_id)->toBe($this->client->id)
        ->and($log->channel)->toBe('email')
        ->and($log->template)->toBe('quotation_sent')
        ->and($log->status)->toBe('sent')
        ->and($log->sent_at)->not->toBeNull();
});

it('quotation yang gagal dikirim tidak menghasilkan email', function () {
    NotificationFacade::fake();

    $empty = Quotation::factory()->for($this->project)->create(); // tanpa baris item

    expect(fn () => app(QuotationService::class)->send($empty, $this->admin))->toThrow(DomainException::class);

    NotificationFacade::assertNothingSent();
    expect(NotificationLog::count())->toBe(0);
});

it('isi email quotation hanya memuat total dan tautan portal, dan mengikuti bahasa klien', function () {
    $quotation = ($this->makeQuotation)('sent');

    $html = (string) (new QuotationSentNotification($quotation, $this->project))->toMail($this->client)->render();

    // Yang dibaca penerima: tanpa blok <style> (CSS email memuat kata "margin") dan tanpa tag HTML.
    $text = strtolower(strip_tags(preg_replace('#<style.*?</style>#si', '', $html)));

    expect($html)->toContain($quotation->totalMoney()->format())
        ->and($html)->toContain(route('portal.projects.negotiation', $this->project->id))
        ->and($text)->not->toContain('margin')
        ->and($text)->not->toContain('operasional');

    NotificationFacade::fake();
    $this->client->forceFill(['locale' => 'en'])->save();

    app(Notifier::class)->quotationSent($quotation);

    NotificationFacade::assertSentTo($this->client, QuotationSentNotification::class, fn ($n) => $n->locale === 'en');
});

// ------------------------------------------------------------------ invoice DP

it('persetujuan klien mengirim satu email Invoice DP, dan tidak dua kali bila diulang', function () {
    NotificationFacade::fake();

    $quotation = ($this->makeQuotation)('sent');
    $service = app(QuotationService::class);

    $service->accept($quotation, $this->client, '127.0.0.1');

    expect(fn () => $service->accept($quotation, $this->client, '127.0.0.1'))->toThrow(DomainException::class);

    $invoice = Invoice::firstOrFail();

    NotificationFacade::assertSentToTimes($this->client, DownPaymentInvoiceNotification::class, 1);
    NotificationFacade::assertSentTo($this->client, DownPaymentInvoiceNotification::class, fn ($n) => $n->invoice->id === $invoice->id);
    NotificationFacade::assertNotSentTo($this->admin, DownPaymentInvoiceNotification::class);

    $html = (string) (new DownPaymentInvoiceNotification($invoice, $this->project))->toMail($this->client)->render();

    expect($html)->toContain($invoice->invoice_number)->and($html)->toContain($invoice->money()->format());
});

// ------------------------------------------------------------------ pengingat masa berlaku

it('pengingat masa berlaku dikirim ke klien sekali per quotation', function () {
    NotificationFacade::fake();

    $quotation = ($this->makeQuotation)('sent', ['valid_until' => today()->addDays(2)]);
    $service = app(QuotationService::class);

    $service->remindExpiring();
    $service->remindExpiring();

    NotificationFacade::assertSentToTimes($this->client, QuotationExpiringNotification::class, 1);
    NotificationFacade::assertSentTo($this->client, QuotationExpiringNotification::class, fn ($n) => $n->quotation->id === $quotation->id);
});

// ------------------------------------------------------------------ penjaga pengiriman

it('email yang gagal dicatat sebagai failed dan tidak melempar error ke alur utama', function () {
    $channel = new class
    {
        public function send($notifiable, $notification): void
        {
            throw new RuntimeException('smtp down');
        }
    };
    app()->instance($channel::class, $channel);

    $notification = new class($channel::class) extends Notification
    {
        public function __construct(private string $channelClass) {}

        public function via(object $notifiable): array
        {
            return [$this->channelClass];
        }
    };

    expect(app(Notifier::class)->deliver($this->client, $notification, 'uji'))->toBeFalse();

    $log = NotificationLog::firstOrFail();

    expect($log->status)->toBe('failed')->and($log->error)->toContain('smtp down')->and($log->sent_at)->toBeNull();
});

it('staf dan akun nonaktif tidak pernah dikirimi email', function () {
    NotificationFacade::fake();

    $inactive = User::factory()->client()->create(['is_active' => false]);
    $notification = new QuotationSentNotification(($this->makeQuotation)('sent'), $this->project);
    $notifier = app(Notifier::class);

    expect($notifier->deliver($this->staff, $notification, 'uji'))->toBeFalse()
        ->and($notifier->deliver($inactive, $notification, 'uji'))->toBeFalse()
        ->and($notifier->deliver(null, $notification, 'uji'))->toBeFalse();

    NotificationFacade::assertNothingSent();
    expect(NotificationLog::count())->toBe(0);
});

// ------------------------------------------------------------------ digest chat

it('pesan klien yang masih baru belum diemail, setelah cukup lama dirangkum satu email per admin aktif', function () {
    NotificationFacade::fake();

    $admin2 = User::factory()->admin()->withTwoFactor()->create();
    $inactiveAdmin = User::factory()->admin()->withTwoFactor()->create(['is_active' => false]);

    ($this->chat)($this->client, 2);

    expect(app(ChatDigestService::class)->sendDue())->toBe(0);
    NotificationFacade::assertNothingSent();

    ($this->chat)($this->client, ChatDigestService::DELAY_MINUTES + 5);
    ($this->chat)($this->client, 1);

    expect(app(ChatDigestService::class)->sendDue())->toBe(2);

    NotificationFacade::assertSentTo([$this->admin, $admin2], ChatDigestNotification::class, fn ($n) => $n->count === 3 && $n->forAdmin === true);
    NotificationFacade::assertNotSentTo([$inactiveAdmin, $this->staff, $this->client], ChatDigestNotification::class);
});

it('pesan admin dirangkum ke klien pemilik proyek', function () {
    NotificationFacade::fake();

    ($this->chat)($this->admin, 30);
    ($this->chat)($this->admin, 20);

    expect(app(ChatDigestService::class)->sendDue())->toBe(1);

    NotificationFacade::assertSentTo($this->client, ChatDigestNotification::class, fn ($n) => $n->count === 2 && $n->forAdmin === false);
    NotificationFacade::assertNotSentTo([$this->admin, $this->staff], ChatDigestNotification::class);
});

it('digest tidak mengulang pesan yang sudah dirangkum, dan pesan baru masuk digest berikutnya', function () {
    NotificationFacade::fake();
    $service = app(ChatDigestService::class);

    ($this->chat)($this->admin, 30);

    expect($service->sendDue())->toBe(1)
        ->and($service->sendDue())->toBe(0)
        ->and(OrderChat::whereNull('notified_at')->count())->toBe(0);

    ($this->chat)($this->admin, 15);

    expect($service->sendDue())->toBe(1);

    NotificationFacade::assertSentToTimes($this->client, ChatDigestNotification::class, 2);
});

it('pesan yang sudah dibaca tidak diemailkan', function () {
    NotificationFacade::fake();

    ($this->chat)($this->admin, 30, ['read_at' => now()->subMinutes(25)]);

    expect(app(ChatDigestService::class)->sendDue())->toBe(0);
    NotificationFacade::assertNothingSent();
});

it('isi pesan tidak ikut dikirim di email digest', function () {
    ($this->chat)($this->admin, 30, ['message' => 'Rahasia: harga final 12 juta']);

    $html = (string) (new ChatDigestNotification($this->project, 1, false))->toMail($this->client)->render();

    expect($html)->not->toContain('Rahasia')->and($html)->toContain(route('portal.projects.negotiation', $this->project->id));
});

it('command chat:digest berjalan, melaporkan jumlahnya, dan terdaftar di scheduler', function () {
    NotificationFacade::fake();

    ($this->chat)($this->admin, 30);

    $this->artisan('chat:digest')->expectsOutputToContain('1 email digest chat terkirim')->assertSuccessful();

    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('chat:digest');
});

// ------------------------------------------------------------------ bahasa

it('teks email tersedia dalam dua bahasa dengan kunci yang sama', function () {
    $id = array_keys(Arr::dot(require base_path('lang/id/mail.php')));
    $en = array_keys(Arr::dot(require base_path('lang/en/mail.php')));

    sort($id);
    sort($en);

    expect($en)->toBe($id);
});
