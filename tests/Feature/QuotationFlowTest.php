<?php

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Livewire\Admin\Leads\Index;
use App\Livewire\Admin\Quotations\Builder;
use App\Livewire\Portal\Negotiation as PortalNegotiation;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Setting;
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

it('builder menerapkan diskon dan PPN dari pengaturan, dan menyimpan margin dalam basis point', function () {
    Setting::query()->create(['key' => 'tax.rate_bp', 'value' => ['basis_points' => 1100]]);
    $project = Project::factory()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    Livewire::test(Builder::class, ['project' => $project->id])
        ->assertSet('apply_tax', true)
        ->set('items', [
            ['title' => 'Backend', 'description' => '', 'hours' => '10', 'rate' => '150000'],
            ['title' => 'Antarmuka', 'description' => '', 'hours' => '12.5', 'rate' => '200000'],
        ])
        ->set('operational_cost', '500000')
        ->set('margin_percent', '20')
        ->set('discount', '400000')
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::firstOrFail();

    expect($quotation->margin_bp)->toBe(2000)
        ->and($quotation->discount_amount)->toBe(400_000)
        ->and($quotation->tax_bp)->toBe(1100)
        ->and($quotation->tax_amount)->toBe(550_000)
        ->and($quotation->total)->toBe(5_550_000)
        ->and($quotation->marginMoney()->amount)->toBe(900_000);
});

it('quotation tanpa PPN bila opsi dimatikan, dan diskon berlebih ditolak', function () {
    Setting::query()->create(['key' => 'tax.rate_bp', 'value' => ['basis_points' => 1100]]);
    $project = Project::factory()->for($this->client, 'client')->create();
    $items = [['title' => 'Backend', 'description' => '', 'hours' => '10', 'rate' => '150000']];

    $this->actingAs($this->admin);
    Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', $items)->set('margin_percent', '0')->set('discount', '99999999')
        ->call('save');

    expect(Quotation::count())->toBe(0);

    Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', $items)->set('margin_percent', '0')->set('discount', '0')->set('apply_tax', false)
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::firstOrFail();

    expect($quotation->tax_bp)->toBe(0)->and($quotation->tax_amount)->toBe(0)->and($quotation->total)->toBe(1_500_000);
});

it('builder menyimpan skema termin kustom dan menolak yang totalnya bukan 100%', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $items = [['title' => 'Backend', 'description' => '', 'hours' => '10', 'rate' => '150000']];

    $this->actingAs($this->admin);
    Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', $items)->set('margin_percent', '0')
        ->set('dp_percent', 'custom')->set('custom_terms', '60, 60')
        ->call('save')
        ->assertHasErrors('custom_terms');

    expect(Quotation::count())->toBe(0);

    Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', $items)->set('margin_percent', '0')
        ->set('dp_percent', 'custom')->set('custom_terms', '100')
        ->call('save')
        ->assertHasErrors('custom_terms');

    Livewire::test(Builder::class, ['project' => $project->id])
        ->set('items', $items)->set('margin_percent', '0')
        ->set('dp_percent', 'custom')->set('custom_terms', '20, 30, 50')
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::firstOrFail();

    expect($quotation->payment_terms)->toBe([2000, 3000, 5000])
        ->and($quotation->dp_percent)->toBe(20)
        ->and(array_map(fn ($m) => $m->amount, $quotation->termMoney()))->toBe([300_000, 450_000, 750_000]);

    Livewire::test(Builder::class, ['project' => $project->id, 'quotation' => $quotation->id])
        ->assertSet('dp_percent', 'custom')
        ->assertSet('custom_terms', '20, 30, 50');
});

it('quotation lama tanpa skema tersimpan memakai preset dari dp_percent', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $legacy = Quotation::factory()->for($project)->create(['dp_percent' => 30, 'payment_terms' => null]);

    expect($legacy->termBps())->toBe([3000, 4000, 3000])
        ->and($legacy->dpMoney()->amount)->toBe(7_920_000);
});

it('Invoice DP otomatis memakai termin pertama dari skema kustom', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project, ['dp_percent' => 20, 'payment_terms' => [2000, 3000, 5000]]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])
        ->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id)
        ->assertHasNoErrors();

    expect(Invoice::firstOrFail()->amount)->toBe(5_280_000); // 20% x 26,4 jt
});

it('persetujuan butuh dua langkah: tanpa konfirmasi dan centang pernyataan, tidak ada yang berubah', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    $this->actingAs($this->client);
    $page = Livewire::test(PortalNegotiation::class, ['project' => $project->id]);

    $page->call('accept', $quotation->id)->assertHasErrors('agreed');
    $page->call('startAccept', $quotation->id)->assertSet('confirmingId', $quotation->id)
        ->call('accept', $quotation->id)->assertHasErrors('agreed');
    $page->call('cancelAccept')->assertSet('confirmingId', null)->assertSet('agreed', false);

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and(Invoice::count())->toBe(0)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Quoted);
});

it('persetujuan mencatat waktu, IP, user agent, versi S&K, dan hash snapshot', function () {
    Setting::query()->create(['key' => 'quotation.terms.id', 'value' => ['value' => 'Revisi maksimal dua kali.']]);
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);
    $service = app(QuotationService::class);
    $expectedVersion = $service->currentTermsVersion();

    $service->accept($quotation, $this->client, '203.0.113.7', str_repeat('A', 600));

    $quotation->refresh();

    expect($expectedVersion)->toStartWith('tc-')
        ->and($quotation->accepted_at)->not->toBeNull()
        ->and($quotation->accepted_ip)->toBe('203.0.113.7')
        ->and(mb_strlen($quotation->accepted_user_agent))->toBe(512)
        ->and($quotation->terms_version)->toBe($expectedVersion)
        ->and($quotation->accepted_snapshot_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($quotation->hasIntactSnapshot())->toBeTrue()
        ->and(ActivityLog::where('action', ActivityAction::QuotationAccepted->value)->first()->properties['snapshot_hash'])
        ->toBe($quotation->accepted_snapshot_hash);
});

it('hash snapshot berubah bila harga atau baris quotation diubah setelah disetujui', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    app(QuotationService::class)->accept($quotation, $this->client, '127.0.0.1', 'UA');
    $accepted = Quotation::findOrFail($quotation->id);

    expect($accepted->hasIntactSnapshot())->toBeTrue();

    QuotationItem::query()->where('quotation_id', $quotation->id)->update(['amount' => 1]);

    expect(Quotation::findOrFail($quotation->id)->hasIntactSnapshot())->toBeFalse();
});

it('versi S&K berubah mengikuti teks di Pengaturan, dan "none" bila kosong', function () {
    $service = app(QuotationService::class);

    expect($service->currentTermsVersion())->toBe('none');

    Setting::query()->create(['key' => 'quotation.terms.en', 'value' => ['value' => 'Two revisions included.']]);
    $first = $service->currentTermsVersion();
    Setting::query()->where('key', 'quotation.terms.en')->update(['value' => json_encode(['value' => 'Three revisions included.'])]);

    expect($first)->not->toBe('none')->and($service->currentTermsVersion())->not->toBe($first);
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

    expect($v1->fresh()->status)->toBe(QuotationStatus::Superseded) // digantikan
        ->and($v2->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($project->fresh()->status)->toBe(ProjectStatus::Quoted)
        ->and(ActivityLog::where('action', ActivityAction::QuotationSuperseded->value)->first()->properties)->toBe(['version' => 1, 'superseded_by' => 2])
        ->and(ActivityLog::where('action', ActivityAction::QuotationRejected->value)->count())->toBe(0);
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
        ->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id)
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
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id);

    expect($project->fresh()->status)->toBe(ProjectStatus::AwaitingDp)
        ->and(Invoice::firstOrFail()->amount)->toBe(7_920_000); // 30% x 26,4 jt
});

it('proyek USD: invoice DP dalam sen dan mata uang tetap USD', function () {
    $project = Project::factory()->usd()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project, ['currency' => Currency::USD, 'total' => 300_000, 'dp_percent' => 50]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id);

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
        Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id);
    }

    expect(Invoice::orderBy('invoice_number')->pluck('invoice_number')->all())
        ->toBe(['QC/'.now()->year.'/0001', 'QC/'.now()->year.'/0002']);
});

it('persetujuan tidak bisa diulang dan tidak menggandakan invoice', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id);
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
            $page->call('startAccept', $target->id)->set('agreed', true)->call('accept', $target->id);
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

it('klien melihat diskon dan PPN terpisah, tetap tanpa operasional dan margin', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    ($this->makeSent)($project, [
        'discount_amount' => 400_000, 'tax_bp' => 1100, 'tax_amount' => 2_860_000, 'total' => 28_860_000,
    ]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])
        ->assertSee('Rp 28.860.000')
        ->assertSee('Diskon')
        ->assertSee('PPN (11%)')
        ->assertSee('Rp 2.860.000')
        ->assertSee('Rp 6.400.000') // operasional + margin digabung
        ->assertDontSee('Margin');
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
        Livewire::test(Index::class);

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

    Livewire::test(Index::class)->assertSee('Prospek A')->assertDontSee('Sudah Aktif');
});

it('Invoice DP otomatis mengisi subtotal, diskon, dan PPN proporsional terhadap porsi termin', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project, [
        'discount_amount' => 400_000, 'tax_bp' => 1100, 'tax_amount' => 550_000, 'total' => 5_550_000,
        'dp_percent' => 30, 'payment_terms' => [3000, 4000, 3000],
    ]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])
        ->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id)
        ->assertHasNoErrors();

    $invoice = Invoice::firstOrFail();

    expect($invoice->amount)->toBe(1_665_000)          // 30% x 5.550.000
        ->and($invoice->discount_amount)->toBe(120_000) // 30% x 400.000
        ->and($invoice->tax_amount)->toBe(165_000)      // 30% x 550.000
        ->and($invoice->subtotal)->toBe(1_620_000)      // 30% x (5.000.000 + 400.000)
        ->and($invoice->term_percent_bp)->toBe(3000)
        ->and($invoice->subtotal - $invoice->discount_amount + $invoice->tax_amount)->toBe($invoice->amount);
});

it('invoice DP tanpa diskon dan PPN tetap konsisten: subtotal sama dengan jumlah', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
    $quotation = ($this->makeSent)($project);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $project->id])
        ->call('startAccept', $quotation->id)->set('agreed', true)->call('accept', $quotation->id);

    $invoice = Invoice::firstOrFail();

    expect($invoice->discount_amount)->toBe(0)
        ->and($invoice->tax_amount)->toBe(0)
        ->and($invoice->subtotal)->toBe($invoice->amount);
});
