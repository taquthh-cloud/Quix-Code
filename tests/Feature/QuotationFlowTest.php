<?php

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Livewire\Admin\Quotations\Builder;
use App\Livewire\Portal\Negotiation as PortalNegotiation;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();

    /** Quotation terkirim dengan satu baris: 100 jam x 200.000 = 20 jt; total 26,4 jt; DP 50% = 13,2 jt. */
    $this->makeSent = function (Project $project, array $overrides = []): Quotation {
        $quotation = Quotation::factory()->sent()->for($project)->create($overrides);
        QuotationItem::factory()->for($quotation)->create([
            'title' => 'Sistem inti', 'hours' => 100, 'rate' => 200_000, 'amount' => 20_000_000, 'sort_order' => 0,
        ]);

        return $quotation;
    };
});

// ------------------------------------------------------------------ builder

it('admin menyusun quotation dengan hitungan jam x rate + operasional + margin', function () {
    $project = Project::factory()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', [
            ['title' => 'Backend', 'description' => 'API dan basis data', 'hours' => '10', 'rate' => '150000'],
            ['title' => 'Antarmuka', 'description' => '', 'hours' => '12.5', 'rate' => '200000'],
        ])
        ->set('operational_cost', '500000')
        ->set('margin_percent', '20')
        ->set('dp_percent', '30')
        ->set('valid_until', now()->addDays(10)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::with('items')->firstOrFail();

    expect($quotation->status)->toBe(QuotationStatus::Draft)
        ->and($quotation->version)->toBe(1)
        ->and($quotation->currency)->toBe(Currency::IDR)
        ->and($quotation->subtotal)->toBe(4_000_000)
        ->and($quotation->operational_cost)->toBe(500_000)
        ->and($quotation->total)->toBe(5_400_000)
        ->and($quotation->dp_percent)->toBe(30)
        ->and($quotation->items->pluck('amount')->all())->toBe([1_500_000, 2_500_000])
        ->and($quotation->items->pluck('sort_order')->all())->toBe([0, 1])
        ->and($project->fresh()->status)->toBe(ProjectStatus::Lead); // draf tidak mengubah status
});

it('quotation USD disimpan dalam sen mengikuti mata uang proyek', function () {
    $project = Project::factory()->usd()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', [['title' => 'Landing page', 'description' => '', 'hours' => '3.5', 'rate' => '85.50']])
        ->set('operational_cost', '0')
        ->set('margin_percent', '0')
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::firstOrFail();

    expect($quotation->currency)->toBe(Currency::USD)
        ->and($quotation->total)->toBe(29_925); // $299,25
});

it('builder menolak isian tidak valid dan tidak menyimpan apa pun', function () {
    $project = Project::factory()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    $component = fn () => Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', [['title' => 'X', 'description' => '', 'hours' => '1', 'rate' => '1000']]);

    $component()->set('items.0.hours', '0')->call('save')->assertHasErrors('items.0.hours');
    $component()->set('items.0.hours', 'abc')->call('save')->assertHasErrors('items.0.hours');
    $component()->set('items.0.title', '')->call('save')->assertHasErrors('items.0.title');
    $component()->set('items.0.rate', '100.50')->call('save')->assertHasErrors('items.0.rate'); // IDR tanpa desimal
    $component()->set('margin_percent', '150')->call('save')->assertHasErrors('margin_percent');
    $component()->set('dp_percent', '40')->call('save')->assertHasErrors('dp_percent');
    $component()->set('valid_until', now()->subDay()->toDateString())->call('save')->assertHasErrors('valid_until');

    expect(Quotation::count())->toBe(0);
});

it('hanya admin yang bisa membuka builder', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $project->staff()->attach($this->staff->id);

    $this->actingAs($this->staff)->get(route('admin.quotations.create', $project->id))->assertForbidden();
    $this->actingAs($this->staff)->get(route('admin.projects.negotiation', $project->id))->assertForbidden();
    $this->actingAs($this->client)->get(route('admin.quotations.create', $project->id))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.quotations.create', $project->id))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.leads.index'))->assertOk();
    $this->actingAs($this->staff)->get(route('admin.leads.index'))->assertForbidden();
});

it('draf yang sudah ada dilanjutkan, bukan dibuat ganda', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $draft = Quotation::factory()->for($project)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.quotations.create', $project->id))
        ->assertRedirect(route('admin.quotations.edit', [$project->id, $draft->id]));
});

it('quotation tidak bisa dibuat atau diubah setelah harga disepakati', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::AwaitingDp]);

    $this->actingAs($this->admin)->get(route('admin.quotations.create', $project->id))->assertForbidden();

    expect(fn () => app(QuotationService::class)->saveDraft($project, null, [
        'operational_cost' => 0, 'margin_percent' => '0', 'dp_percent' => 50, 'valid_until' => null,
    ], [['title' => 'X', 'description' => null, 'hours' => '1', 'rate' => 1000]]))
        ->toThrow(DomainException::class);
});

// ------------------------------------------------------------------ kirim & versi

it('mengirim quotation memindahkan proyek lead ke quoted dan tercatat', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $quotation = Quotation::factory()->for($project)->create();
    QuotationItem::factory()->for($quotation)->create(['hours' => 10, 'rate' => 150_000, 'amount' => 1_500_000]);

    $this->actingAs($this->admin);
    Livewire::test(Builder::class, ['project' => $project->id, 'quotation' => $quotation->id])
        ->call('saveAndSend')
        ->assertHasNoErrors();

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($quotation->fresh()->sent_at)->not->toBeNull()
        ->and($project->fresh()->status)->toBe(ProjectStatus::Quoted)
        ->and(ActivityLog::where('action', ActivityAction::QuotationSent->value)->count())->toBe(1)
        ->and(ActivityLog::where('action', ActivityAction::StatusChanged->value)->first()->properties)
        ->toEqual(['old' => 'lead', 'new' => 'quoted']);
});

it('quotation yang sudah terkirim tidak bisa diubah; versi baru menggantikan yang lama', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $v1 = ($this->makeSent)($project, ['version' => 1]);

    $this->actingAs($this->admin);
    Livewire::test(Builder::class, ['project' => $project->id, 'quotation' => $v1->id])
        ->call('save')
        ->assertForbidden();

    $service = app(QuotationService::class);
    $v2 = $service->newVersionFrom($v1->load('items'));

    expect($v2->version)->toBe(2)
        ->and($v2->status)->toBe(QuotationStatus::Draft)
        ->and($v2->items()->count())->toBe(1)
        ->and($service->newVersionFrom($v1)->id)->toBe($v2->id); // draf yang ada dipakai ulang

    $service->send($v2, $this->admin);

    expect($v1->fresh()->status)->toBe(QuotationStatus::Rejected) // digantikan
        ->and($v2->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Quoted)
        ->and(ActivityLog::where('action', ActivityAction::QuotationRejected->value)->first()->properties['reason'])->toBe('superseded');
});

it('quotation kosong atau kedaluwarsa tidak bisa dikirim', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $empty = Quotation::factory()->for($project)->create(['version' => 1]);
    $service = app(QuotationService::class);

    expect(fn () => $service->send($empty, $this->admin))->toThrow(DomainException::class);

    $expired = Quotation::factory()->for($project)->create(['version' => 2, 'valid_until' => now()->subDays(2)]);
    QuotationItem::factory()->for($expired)->create();

    expect(fn () => $service->send($expired, $this->admin))->toThrow(DomainException::class)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Lead);
});

// ------------------------------------------------------------------ klien menyetujui

it('klien menyetujui: deal, harga dan mata uang terkunci, Invoice DP terbit, menunggu DP', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])
        ->call('accept', $quotation->id)
        ->assertHasNoErrors();

    $project->refresh();
    $invoice = Invoice::firstOrFail();
    $quotation->refresh();

    expect($quotation->status)->toBe(QuotationStatus::Accepted)
        ->and($quotation->accepted_at)->not->toBeNull()
        ->and($quotation->accepted_ip)->not->toBeNull()
        ->and($project->status)->toBe(ProjectStatus::AwaitingDp)
        ->and($project->final_price)->toBe(26_400_000)
        ->and($project->isCurrencyLocked())->toBeTrue()
        ->and($invoice->type)->toBe(InvoiceType::Dp)
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->amount)->toBe(13_200_000)
        ->and($invoice->currency)->toBe(Currency::IDR)
        ->and($invoice->invoice_number)->toBe('QC/'.now()->year.'/0001')
        ->and($invoice->due_date)->not->toBeNull();

    $statusLogs = ActivityLog::where('action', ActivityAction::StatusChanged->value)->orderBy('id')->get();

    expect($statusLogs->pluck('properties')->all())->toEqual([
        ['old' => 'quoted', 'new' => 'deal'],
        ['old' => 'deal', 'new' => 'awaiting_dp'],
    ])
        ->and($statusLogs->pluck('user_id')->unique()->values()->all())->toEqual([$this->client->id])
        ->and(ActivityLog::where('action', ActivityAction::QuotationAccepted->value)->count())->toBe(1)
        ->and(ActivityLog::where('action', ActivityAction::InvoiceCreated->value)->count())->toBe(1)
        ->and(ActivityLog::where('action', ActivityAction::PriceChanged->value)->first()->properties['new']['final_price'])->toBe(26_400_000);
});

it('persetujuan dari status negotiating juga berjalan, dan skema 30% memakai DP 30%', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Negotiating]);
    $quotation = ($this->makeSent)($project, ['dp_percent' => 30]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('accept', $quotation->id);

    expect($project->fresh()->status)->toBe(ProjectStatus::AwaitingDp)
        ->and(Invoice::firstOrFail()->amount)->toBe(7_920_000); // 30% x 26,4 jt
});

it('proyek USD: invoice DP dalam sen dan mata uang tetap USD', function () {
    $project = Project::factory()->usd()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project, ['currency' => Currency::USD, 'total' => 300_000, 'dp_percent' => 50]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('accept', $quotation->id);

    $invoice = Invoice::firstOrFail();

    expect($invoice->currency)->toBe(Currency::USD)
        ->and($invoice->amount)->toBe(150_000)
        ->and($project->fresh()->currency)->toBe(Currency::USD)
        ->and($project->fresh()->final_price)->toBe(300_000);
});

it('nomor invoice berurutan antar proyek', function () {
    foreach ([1, 2] as $n) {
        $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
        $quotation = ($this->makeSent)($project);

        $this->actingAs($this->client);
        Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('accept', $quotation->id);
    }

    expect(Invoice::orderBy('invoice_number')->pluck('invoice_number')->all())
        ->toBe(['QC/'.now()->year.'/0001', 'QC/'.now()->year.'/0002']);
});

it('persetujuan tidak bisa diulang dan tidak menggandakan invoice', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('accept', $quotation->id);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('accept', $quotation->id)->assertForbidden();

    expect(Invoice::count())->toBe(1);
});

it('klien menolak: negosiasi lanjut, status proyek tetap, tidak ada invoice', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('reject', $quotation->id);

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Rejected)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Quoted)
        ->and(Invoice::count())->toBe(0);

    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('accept', $quotation->id)->assertForbidden();
});

it('quotation kedaluwarsa tidak bisa disetujui', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project, ['valid_until' => now()->subDays(3)]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('accept', $quotation->id)->assertForbidden();

    expect($project->fresh()->status)->toBe(ProjectStatus::Quoted)->and(Invoice::count())->toBe(0);
});

// ------------------------------------------------------------------ isolasi

it('klien lain tidak bisa membuka halaman negosiasi proyek ini, staf dan admin ditolak', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $other = User::factory()->client()->create();

    $this->actingAs($other)->get(route('portal.projects.negotiation', $project->id))->assertNotFound();
    $this->actingAs($this->staff)->get(route('portal.projects.negotiation', $project->id))->assertForbidden();
    $this->actingAs($this->admin)->get(route('portal.projects.negotiation', $project->id))->assertForbidden();
    $this->actingAs($this->client)->get(route('portal.projects.negotiation', $project->id))->assertOk();
});

it('klien tidak melihat draf dan tidak bisa menanggapi quotation proyek lain', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $draft = Quotation::factory()->for($project)->create(['version' => 1]);

    $otherClient = User::factory()->client()->create();
    $otherProject = Project::factory()->for($otherClient, 'client')->create(['status' => ProjectStatus::Quoted]);
    $foreign = ($this->makeSent)($otherProject);

    $this->actingAs($this->client);
    $page = Livewire::test(PortalNegotiation::class, ['project' => $project->id]);

    // Draf dan quotation proyek lain tidak ditemukan lewat visibleTo: aksi gagal (404), apa pun bentuk galatnya.
    foreach ([$draft, $foreign] as $target) {
        try {
            $page->call('accept', $target->id);
        } catch (Throwable) {
            // diharapkan
        }
    }

    expect(Invoice::count())->toBe(0)
        ->and($draft->fresh()->status)->toBe(QuotationStatus::Draft)
        ->and($foreign->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Quoted);
});

it('klien tidak melihat operasional dan margin, hanya subtotal, biaya layanan, dan total', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    ($this->makeSent)($project, ['margin_percent' => 37.5, 'operational_cost' => 1_234_567]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])
        ->assertSee('Rp 26.400.000')
        ->assertSee('Biaya operasional & layanan')
        ->assertDontSee('Margin')
        ->assertDontSee('37,5')
        ->assertDontSee('1.234.567');
});

it('admin melihat operasional dan margin di builder untuk versi terkirim', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    $this->actingAs($this->admin)
        ->get(route('admin.quotations.edit', [$project->id, $quotation->id]))
        ->assertOk()
        ->assertSee('Margin');
});

// ------------------------------------------------------------------ daftar prospek

it('daftar prospek tidak N+1 dan hanya memuat proyek sebelum deal', function () {
    $this->actingAs($this->admin);
    Project::factory()->for($this->client, 'client')->create(['title' => 'Sudah Aktif', 'status' => ProjectStatus::Active]);

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(App\Livewire\Admin\Leads\Index::class);

        return count(DB::getQueryLog());
    };

    $lead = Project::factory()->for($this->client, 'client')->create(['title' => 'Prospek A']);
    ($this->makeSent)($lead);
    $sedikit = $count();

    foreach (range(1, 5) as $i) {
        $p = Project::factory()->for($this->client, 'client')->create();
        ($this->makeSent)($p);
    }

    expect($count())->toBe($sedikit);

    Livewire::test(App\Livewire\Admin\Leads\Index::class)->assertSee('Prospek A')->assertDontSee('Sudah Aktif');
});
