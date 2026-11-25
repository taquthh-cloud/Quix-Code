<?php

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Events\QuotationExpired;
use App\Events\QuotationExpiringSoon;
use App\Livewire\Chat\Room;
use App\Livewire\Portal\Negotiation as PortalNegotiation;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\OrderChat;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\QuotationService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    // Waktu dibekukan agar batas hari (hari ini / besok / H-3) deterministik.
    $this->travelTo(Carbon::parse('2026-12-10 10:00:00'));
    app()->setLocale('id');

    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();

    /** Proyek berstatus quoted milik klien + satu quotation (versi 1) dengan satu baris item. */
    $this->makeQuotation = function (string $state = 'sent', array $overrides = [], ?Project $project = null): Quotation {
        $project ??= Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);
        $factory = Quotation::factory()->for($project);
        $factory = $state === 'draft' ? $factory : $factory->{$state}(); // draf = keadaan bawaan factory
        $quotation = $factory->create($overrides);
        QuotationItem::factory()->for($quotation)->create([
            'title' => 'Sistem inti', 'hours' => 100, 'rate' => 200_000, 'amount' => 20_000_000, 'sort_order' => 0,
        ]);

        return $quotation;
    };
});

afterEach(fn () => $this->travelBack());

// ------------------------------------------------------------------ kedaluwarsa oleh scheduler

it('scheduler menandai expired hanya quotation terkirim yang lewat valid_until', function () {
    $overdue = ($this->makeQuotation)('sent', ['valid_until' => today()->subDay()]);
    $dueToday = ($this->makeQuotation)('sent', ['valid_until' => today()]);
    $future = ($this->makeQuotation)('sent', ['valid_until' => today()->addDays(5)]);
    $noLimit = ($this->makeQuotation)('sent', ['valid_until' => null]);
    $draft = ($this->makeQuotation)('draft', ['valid_until' => today()->subDays(3)]);
    $accepted = ($this->makeQuotation)('accepted', ['valid_until' => today()->subDays(3)]);

    expect(app(QuotationService::class)->expireOverdue())->toBe(1);

    expect($overdue->fresh()->status)->toBe(QuotationStatus::Expired)
        ->and($overdue->fresh()->expired_at)->not->toBeNull()
        ->and($dueToday->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($future->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($noLimit->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($draft->fresh()->status)->toBe(QuotationStatus::Draft)
        ->and($accepted->fresh()->status)->toBe(QuotationStatus::Accepted);
});

it('kedaluwarsa dicatat sebagai aksi sistem, status proyek tidak berubah, dan aman diulang', function () {
    $quotation = ($this->makeQuotation)('sent', ['valid_until' => today()->subDays(2)]);
    $service = app(QuotationService::class);

    $service->expireOverdue();

    $logs = ActivityLog::query()->where('action', ActivityAction::QuotationExpired->value)->get();

    expect($logs)->toHaveCount(1)
        ->and($logs->first()->user_id)->toBeNull()
        ->and($logs->first()->subject_id)->toBe((string) $quotation->project_id)
        ->and($logs->first()->properties['version'])->toBe(1)
        ->and($quotation->project->fresh()->status)->toBe(ProjectStatus::Quoted);

    // Jalan kedua: tidak ada yang berubah dan tidak ada log ganda.
    expect($service->expireOverdue())->toBe(0)
        ->and(ActivityLog::query()->where('action', ActivityAction::QuotationExpired->value)->count())->toBe(1);
});

it('event QuotationExpired dikirim sekali per quotation yang kedaluwarsa', function () {
    Event::fake([QuotationExpired::class]);

    ($this->makeQuotation)('sent', ['valid_until' => today()->subDay()]);
    ($this->makeQuotation)('sent', ['valid_until' => today()->addDay()]);

    app(QuotationService::class)->expireOverdue();
    app(QuotationService::class)->expireOverdue();

    Event::assertDispatchedTimes(QuotationExpired::class, 1);
});

it('quotation expired tidak bisa disetujui maupun ditolak klien', function () {
    $quotation = ($this->makeQuotation)('sent', ['valid_until' => today()->subDay()]);
    app(QuotationService::class)->expireOverdue();

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $quotation->project_id])
        ->call('startAccept', $quotation->id)->assertForbidden();
    Livewire::test(PortalNegotiation::class, ['project' => $quotation->project_id])
        ->call('reject', $quotation->id)->assertForbidden();

    expect(fn () => app(QuotationService::class)->accept($quotation->fresh(), $this->client, '203.0.113.9'))
        ->toThrow(DomainException::class)
        ->and($quotation->project->fresh()->status)->toBe(ProjectStatus::Quoted)
        ->and(Invoice::count())->toBe(0);
});

it('admin bisa membuat versi baru dari quotation expired, tanpa masa berlaku lama', function () {
    $quotation = ($this->makeQuotation)('sent', ['valid_until' => today()->subDays(4)]);
    app(QuotationService::class)->expireOverdue();

    $draft = app(QuotationService::class)->newVersionFrom($quotation->fresh()->load('items'));

    expect($draft->status)->toBe(QuotationStatus::Draft)
        ->and($draft->version)->toBe(2)
        ->and($draft->valid_until)->toBeNull()
        ->and($draft->items()->count())->toBe(1)
        ->and($quotation->fresh()->status)->toBe(QuotationStatus::Expired);
});

it('klien tetap bisa menulis di chat setelah quotation expired; pesan tersimpan, status tidak berubah', function () {
    $quotation = ($this->makeQuotation)('sent', ['valid_until' => today()->subDay()]);
    app(QuotationService::class)->expireOverdue();

    $this->actingAs($this->client);
    Livewire::test(Room::class, ['projectId' => $quotation->project_id])
        ->set('message', 'Apakah penawarannya masih bisa diperpanjang?')
        ->call('send')
        ->assertHasNoErrors();

    expect(OrderChat::count())->toBe(1)
        ->and($quotation->project->fresh()->status)->toBe(ProjectStatus::Quoted);
});

// ------------------------------------------------------------------ pengingat H-3

it('pengingat dicatat untuk quotation terkirim yang berakhir dalam 3 hari atau kurang', function () {
    $in3 = ($this->makeQuotation)('sent', ['valid_until' => today()->addDays(3)]);
    $in2 = ($this->makeQuotation)('sent', ['valid_until' => today()->addDays(2)]);
    $today = ($this->makeQuotation)('sent', ['valid_until' => today()]);
    $in4 = ($this->makeQuotation)('sent', ['valid_until' => today()->addDays(4)]);
    $noLimit = ($this->makeQuotation)('sent', ['valid_until' => null]);
    $overdue = ($this->makeQuotation)('sent', ['valid_until' => today()->subDay()]);
    $acceptedSoon = ($this->makeQuotation)('accepted', ['valid_until' => today()->addDay()]);
    $draftSoon = ($this->makeQuotation)('draft', ['valid_until' => today()->addDay()]);

    expect(app(QuotationService::class)->remindExpiring())->toBe(3);

    expect($in3->fresh()->reminded_at)->not->toBeNull()
        ->and($in2->fresh()->reminded_at)->not->toBeNull()
        ->and($today->fresh()->reminded_at)->not->toBeNull()
        ->and($in4->fresh()->reminded_at)->toBeNull()
        ->and($noLimit->fresh()->reminded_at)->toBeNull()
        ->and($overdue->fresh()->reminded_at)->toBeNull()
        ->and($acceptedSoon->fresh()->reminded_at)->toBeNull()
        ->and($draftSoon->fresh()->reminded_at)->toBeNull()
        ->and(ActivityLog::query()->where('action', ActivityAction::QuotationReminded->value)->count())->toBe(3);
});

it('pengingat hanya sekali per quotation walau scheduler jalan berulang', function () {
    Event::fake([QuotationExpiringSoon::class]);

    ($this->makeQuotation)('sent', ['valid_until' => today()->addDays(2)]);
    $service = app(QuotationService::class);

    expect($service->remindExpiring())->toBe(1)
        ->and($service->remindExpiring())->toBe(0)
        ->and(ActivityLog::query()->where('action', ActivityAction::QuotationReminded->value)->count())->toBe(1);

    Event::assertDispatchedTimes(QuotationExpiringSoon::class, 1);
});

// ------------------------------------------------------------------ command & jadwal

it('command quotations:expire dan quotations:remind berjalan dan melaporkan jumlahnya', function () {
    ($this->makeQuotation)('sent', ['valid_until' => today()->subDay()]);
    ($this->makeQuotation)('sent', ['valid_until' => today()->addDay()]);

    $this->artisan('quotations:expire')->expectsOutputToContain('1 quotation ditandai kedaluwarsa')->assertSuccessful();
    $this->artisan('quotations:remind')->expectsOutputToContain('1 pengingat dicatat')->assertSuccessful();
});

it('kedua command terdaftar di scheduler', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('quotations:expire')->and($output)->toContain('quotations:remind');
});

// ------------------------------------------------------------------ tampilan

it('klien melihat peringatan ketika penawaran tinggal beberapa hari, dan petunjuk setelah expired', function () {
    $soon = ($this->makeQuotation)('sent', ['valid_until' => today()->addDays(2)]);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $soon->project_id])
        ->assertSee('berakhir dalam 2 hari');

    $soon->update(['valid_until' => today()]);
    Livewire::test(PortalNegotiation::class, ['project' => $soon->project_id])
        ->assertSee('berakhir hari ini');

    $soon->update(['valid_until' => today()->subDay()]);
    app(QuotationService::class)->expireOverdue();
    Livewire::test(PortalNegotiation::class, ['project' => $soon->project_id])
        ->assertSee('Kedaluwarsa')
        ->assertSee('sudah berakhir');
});

// ------------------------------------------------------------------ cetak / PDF

it('klien membuka halaman cetak quotation proyeknya tanpa operasional dan margin', function () {
    $quotation = ($this->makeQuotation)('sent');

    $this->actingAs($this->client)
        ->get(route('quotations.print', $quotation->id))
        ->assertOk()
        ->assertSee(Money::of(26_400_000, Currency::IDR)->format())
        ->assertSee(Money::of(20_000_000, Currency::IDR)->format())
        ->assertSee('Sistem inti')
        ->assertDontSee(Money::of(2_000_000, Currency::IDR)->format())
        ->assertDontSee(Money::of(4_400_000, Currency::IDR)->format());
});

it('admin juga mendapat dokumen versi klien (tanpa operasional dan margin)', function () {
    $quotation = ($this->makeQuotation)('sent');

    $this->actingAs($this->admin)
        ->get(route('quotations.print', $quotation->id))
        ->assertOk()
        ->assertSee(Money::of(26_400_000, Currency::IDR)->format())
        ->assertDontSee(Money::of(2_000_000, Currency::IDR)->format())
        ->assertDontSee(Money::of(4_400_000, Currency::IDR)->format());
});

it('halaman cetak ditolak untuk tamu, staf, klien lain, dan draf bagi klien', function () {
    $sent = ($this->makeQuotation)('sent');
    $draft = ($this->makeQuotation)('draft', ['version' => 2], $sent->project);
    $otherClient = User::factory()->client()->create();

    $this->get(route('quotations.print', $sent->id))->assertRedirect();

    $this->actingAs($this->staff)->get(route('quotations.print', $sent->id))->assertNotFound();
    $this->actingAs($otherClient)->get(route('quotations.print', $sent->id))->assertNotFound();
    $this->actingAs($this->client)->get(route('quotations.print', $draft->id))->assertNotFound();
});

it('teks S&K ikut tercetak bila versinya masih berlaku, dan disembunyikan bila sudah berubah sejak disetujui', function () {
    Setting::query()->create(['key' => 'quotation.terms.id', 'value' => ['value' => 'Revisi gratis dua kali.']]);

    $current = ($this->makeQuotation)('sent');
    $this->actingAs($this->client)
        ->get(route('quotations.print', $current->id))
        ->assertOk()
        ->assertSee('Revisi gratis dua kali.');

    $old = ($this->makeQuotation)('accepted', ['terms_version' => 'tc-lama00000000']);
    $this->actingAs($this->client)
        ->get(route('quotations.print', $old->id))
        ->assertOk()
        ->assertSee('tc-lama00000000')
        ->assertDontSee('Revisi gratis dua kali.');
});
