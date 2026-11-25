<?php

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Livewire\Admin\Projects\Negotiation as AdminNegotiation;
use App\Livewire\Portal\Negotiation as PortalNegotiation;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\QuotationService;
use App\Support\Money;
use App\Support\QuotationComparison;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Quoted]);

    $this->addItem = fn (Quotation $q, string $title, int $hours, int $sort, string $description = '') => QuotationItem::factory()->for($q)->create([
        'title' => $title, 'description' => $description, 'hours' => $hours, 'rate' => 100_000, 'amount' => $hours * 100_000, 'sort_order' => $sort,
    ]);

    /**
     * v1: A 10 jam, B 5 jam, C 2 jam; operasional 100 rb, margin 20% -> total 2.160.000.
     * v2: A tetap, B jadi 8 jam, C dihapus, D 3 jam baru; operasional 150 rb, margin 20% -> total 2.700.000.
     */
    $this->makeVersions = function (string $v2State = 'sent'): array {
        $v1 = Quotation::factory()->sent()->for($this->project)->create([
            'version' => 1, 'subtotal' => 1_700_000, 'operational_cost' => 100_000, 'total' => 2_160_000, 'dp_percent' => 50,
        ]);
        ($this->addItem)($v1, 'Modul A', 10, 0);
        ($this->addItem)($v1, 'Modul B', 5, 1);
        ($this->addItem)($v1, 'Modul C', 2, 2);

        $factory = Quotation::factory()->for($this->project);
        $v2 = ($v2State === 'draft' ? $factory : $factory->{$v2State}())->create([
            'version' => 2, 'subtotal' => 2_100_000, 'operational_cost' => 150_000, 'total' => 2_700_000, 'dp_percent' => 50,
        ]);
        ($this->addItem)($v2, 'Modul A', 10, 0);
        ($this->addItem)($v2, 'Modul B', 8, 1);
        ($this->addItem)($v2, 'Modul D', 3, 2);

        return [$v1, $v2];
    };
});

// ------------------------------------------------------------------ status superseded

it('mengirim versi baru menandai versi terkirim sebelumnya sebagai superseded, bukan rejected', function () {
    [$v1, $v2] = ($this->makeVersions)('draft');

    app(QuotationService::class)->send($v2, $this->admin);

    $logs = ActivityLog::query()->where('action', ActivityAction::QuotationSuperseded->value)->get();

    expect($v1->fresh()->status)->toBe(QuotationStatus::Superseded)
        ->and($v2->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($logs)->toHaveCount(1)
        ->and($logs->first()->properties)->toBe(['version' => 1, 'superseded_by' => 2])
        ->and(ActivityLog::query()->where('action', ActivityAction::QuotationRejected->value)->count())->toBe(0);
});

it('klien tidak bisa menyetujui atau menolak versi yang sudah digantikan', function () {
    [$v1] = ($this->makeVersions)('sent');
    $v1->forceFill(['status' => QuotationStatus::Superseded])->save();

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $this->project->id])->call('startAccept', $v1->id)->assertForbidden();
    Livewire::test(PortalNegotiation::class, ['project' => $this->project->id])->call('reject', $v1->id)->assertForbidden();

    expect(fn () => app(QuotationService::class)->accept($v1->fresh(), $this->client, '203.0.113.9'))->toThrow(DomainException::class)
        ->and($this->project->fresh()->status)->toBe(ProjectStatus::Quoted);
});

it('klien melihat petunjuk bahwa versi lama sudah digantikan', function () {
    [$v1] = ($this->makeVersions)('sent');
    $v1->forceFill(['status' => QuotationStatus::Superseded])->save();

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $this->project->id])
        ->assertSee('Digantikan')
        ->assertSee('sudah digantikan oleh versi yang lebih baru');
});

// ------------------------------------------------------------------ perbandingan (data)

it('perbandingan menandai item baru, dihapus, diubah, dan tetap, serta selisih angkanya', function () {
    [$v1, $v2] = ($this->makeVersions)('sent');

    $diff = QuotationComparison::between($v1->load('items'), $v2->load('items'), internal: true);
    $items = collect($diff['items'])->keyBy('title');
    $rows = collect($diff['rows'])->keyBy('label');

    expect($diff['changed'])->toBeTrue()
        ->and($items['Modul A']['status'])->toBe('same')
        ->and($items['Modul B']['status'])->toBe('changed')
        ->and($items['Modul B']['old'])->toBe(500_000)
        ->and($items['Modul B']['new'])->toBe(800_000)
        ->and($items['Modul C']['status'])->toBe('removed')
        ->and($items['Modul C']['new'])->toBeNull()
        ->and($items['Modul D']['status'])->toBe('added')
        ->and($items['Modul D']['old'])->toBeNull()
        ->and($rows['quotations.total']['delta'])->toBe(540_000)
        ->and($rows['quotations.builder.operational']['delta'])->toBe(50_000)
        ->and($rows['quotations.builder.margin']['old'])->toBe(360_000)
        ->and($rows['quotations.builder.margin']['new'])->toBe(450_000)
        ->and($rows['quotations.builder.discount']['changed'])->toBeFalse()
        ->and($rows)->not->toHaveKey('quotations.overhead');
});

it('mode klien menggabungkan operasional dan margin', function () {
    [$v1, $v2] = ($this->makeVersions)('sent');

    $rows = collect(QuotationComparison::between($v1->load('items'), $v2->load('items'))['rows'])->keyBy('label');

    expect($rows['quotations.overhead']['old'])->toBe(460_000)
        ->and($rows['quotations.overhead']['new'])->toBe(600_000)
        ->and($rows)->not->toHaveKey('quotations.builder.operational')
        ->and($rows)->not->toHaveKey('quotations.builder.margin');
});

it('dua versi identik dilaporkan tanpa perubahan, dan judul item dicocokkan tanpa beda huruf', function () {
    $a = Quotation::factory()->sent()->for($this->project)->create(['version' => 1]);
    ($this->addItem)($a, 'Halaman Depan', 10, 0, 'Beranda');
    $b = Quotation::factory()->sent()->for($this->project)->create(['version' => 2]);
    ($this->addItem)($b, 'halaman depan', 10, 0, 'Beranda');

    $diff = QuotationComparison::between($a->load('items'), $b->load('items'));

    expect($diff['changed'])->toBeFalse()
        ->and($diff['items'])->toHaveCount(1)
        ->and($diff['items'][0]['status'])->toBe('same');
});

// ------------------------------------------------------------------ perbandingan (tampilan)

it('klien membandingkan versi dan tidak melihat operasional maupun margin', function () {
    [, $v2] = ($this->makeVersions)('sent');
    $fmt = fn (int $amount) => Money::of($amount, Currency::IDR)->format();

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $this->project->id])
        ->call('toggleCompare', $v2->id)
        ->assertSee('Perubahan dari versi 1 ke versi 2')
        ->assertSee('Modul D')
        ->assertSee('Modul C')
        ->assertSee('Baru')
        ->assertSee('Dihapus')
        ->assertSee($fmt(540_000)) // selisih total
        ->assertDontSee($fmt(150_000)) // operasional v2
        ->assertDontSee($fmt(450_000)) // margin v2
        ->assertDontSee('Margin');
});

it('admin membandingkan versi dan melihat operasional dan margin', function () {
    [, $v2] = ($this->makeVersions)('sent');
    $fmt = fn (int $amount) => Money::of($amount, Currency::IDR)->format();

    $this->actingAs($this->admin);
    Livewire::test(AdminNegotiation::class, ['project' => $this->project->id])
        ->call('toggleCompare', $v2->id)
        ->assertSee('Perubahan dari versi 1 ke versi 2')
        ->assertSee($fmt(150_000))
        ->assertSee($fmt(450_000))
        ->call('toggleCompare', $v2->id) // klik kedua menutup
        ->assertDontSee('Perubahan dari versi 1 ke versi 2');
});

it('versi pertama tidak punya tombol bandingkan, dan klien tidak bisa membandingkan dengan draf', function () {
    $v1 = Quotation::factory()->sent()->for($this->project)->create(['version' => 1]);
    ($this->addItem)($v1, 'Modul A', 10, 0);

    $this->actingAs($this->client);
    Livewire::test(PortalNegotiation::class, ['project' => $this->project->id])
        ->assertDontSee('Bandingkan dengan versi sebelumnya');

    // Draf v2 tidak terlihat klien: perbandingan v1 tidak punya pembanding dan tidak menampilkan apa pun.
    $draft = Quotation::factory()->for($this->project)->create(['version' => 2]);
    ($this->addItem)($draft, 'Rahasia Draf', 99, 0);

    Livewire::test(PortalNegotiation::class, ['project' => $this->project->id])
        ->call('toggleCompare', $v1->id)
        ->assertDontSee('Perubahan dari versi')
        ->assertDontSee('Rahasia Draf');
});
