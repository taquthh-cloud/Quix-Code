<?php

use App\Models\Project;
use App\Models\ProjectGitCommit;
use App\Models\User;
use App\Support\PortalNavigation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    app()->setLocale('id');
    $this->client = User::factory()->client()->create();
    $this->project = Project::factory()->active()->for($this->client, 'client')->create(['title' => 'Toko Online Budi']);
});

// ------------------------------------------------------------------ T4-01 layout

it('beranda portal memakai layout portal: viewport ponsel, navigasi bawah dan sidebar, tanpa tautan starter kit', function () {
    $this->actingAs($this->client)
        ->get(route('portal.overview'))
        ->assertOk()
        ->assertSee('data-portal-layout', false)
        ->assertSee('data-portal-nav="bottom"', false)
        ->assertSee('data-portal-nav="sidebar"', false)
        ->assertSee('width=device-width', false)
        ->assertDontSee('livewire-starter-kit', false)
        ->assertSee('Toko Online Budi');
});

it('di luar proyek hanya menu Proyek yang tampil; di dalam proyek muncul Ringkasan, Quotation, dan Tagihan milik proyek itu', function () {
    $this->actingAs($this->client);

    $overview = $this->get(route('portal.overview'))->assertOk();
    $overview->assertSee('data-nav="projects"', false)
        ->assertDontSee('data-nav="invoices"', false);

    $this->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertSee('data-nav="projects"', false)
        ->assertSee('data-nav="overview"', false)
        ->assertSee('data-nav="negotiation"', false)
        ->assertSee('data-nav="invoices"', false)
        ->assertSee(route('portal.projects.invoices', $this->project->id), false)
        ->assertSee(route('portal.projects.negotiation', $this->project->id), false);
});

it('menu aktif ditandai aria-current di halaman tagihan dan quotation', function () {
    $this->actingAs($this->client);

    $html = $this->get(route('portal.projects.invoices', $this->project->id))->assertOk()->getContent();
    expect($html)->toMatch('#<a href="[^"]*/invoices"[^>]*data-nav="invoices"\s+aria-current="page"#');

    $html = $this->get(route('portal.projects.negotiation', $this->project->id))->assertOk()->getContent();
    expect($html)->toMatch('#<a href="[^"]*/negotiation"[^>]*data-nav="negotiation"\s+aria-current="page"#');
});

it('label menu mengikuti bahasa akun', function () {
    $client = User::factory()->client()->create(['locale' => 'en']);
    $project = Project::factory()->active()->for($client, 'client')->create();

    $this->actingAs($client)
        ->get(route('portal.projects.show', $project->id))
        ->assertOk()
        ->assertSee('Portal navigation', false)
        ->assertSee('Invoices');
});

it('halaman pengaturan akun klien juga memakai layout portal, sedangkan staf tetap memakai sidebar internal', function () {
    $this->actingAs($this->client)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-portal-layout', false);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('data-portal-layout', false);
});

it('menu proyek tidak pernah memuat route yang belum terdaftar dan bilah bawah maksimal lima item', function () {
    $items = [...PortalNavigation::global(), ...PortalNavigation::forProject($this->project->id)];

    expect($items)->not->toBeEmpty();
    foreach ($items as $item) {
        expect(Route::has($item['route']))->toBeTrue();
    }
    expect(PortalNavigation::bottom($this->project->id))->not->toBeEmpty()
        ->and(count(PortalNavigation::bottom($this->project->id)))->toBeLessThanOrEqual(5)
        ->and(collect(PortalNavigation::bottom(null))->pluck('key')->all())->toBe(['projects']);
});

it('target sentuh navigasi bawah minimal 44px dan konten tidak tertutup bilah bawah', function () {
    $html = $this->actingAs($this->client)->get(route('portal.overview'))->assertOk()->getContent();

    expect($html)->toContain('min-h-14')   // 56px
        ->and($html)->toContain('pb-28')   // ruang di bawah konten
        ->and($html)->toContain('min-w-0'); // konten tidak melebarkan halaman
});

// ------------------------------------------------------------------ T4-03 milestone + timeline

it('milestone menampilkan status, target progres, dan tenggat', function () {
    $this->project->milestones()->make([
        'title' => 'Desain halaman utama',
        'target_progress' => 40,
        'due_date' => '2026-12-15',
        'sort_order' => 0,
    ])->forceFill(['status' => 'in_progress'])->save();

    $this->actingAs($this->client)
        ->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertSee('Desain halaman utama')
        ->assertSee('Target progres 40%')
        ->assertSee('tenggat 15 Des 2026')
        ->assertSee('Berjalan');
});

it('timeline commit tanpa data menampilkan empty state yang jelas', function () {
    $this->actingAs($this->client)
        ->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertSee('data-empty="timeline"', false)
        ->assertSee('Belum ada pembaruan pengerjaan')
        ->assertSee('Pembaruan akan muncul di sini otomatis');
});

it('timeline menampilkan pesan, waktu, dan progres, tanpa hash atau email penulis', function () {
    ProjectGitCommit::factory()->for($this->project)->create([
        'commit_hash' => 'abc123deadbeef0000000000000000000000cafe',
        'commit_message' => 'Halaman checkout selesai',
        'progress_recorded' => 55,
        'author_email' => 'dev@quix.test',
        'pushed_at' => '2026-11-20 10:30:00',
    ]);

    $this->actingAs($this->client)
        ->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertDontSee('data-empty="timeline"', false)
        ->assertSee('Halaman checkout selesai')
        ->assertSee('progres 55%')
        ->assertDontSee('abc123deadbeef')
        ->assertDontSee('dev@quix.test');
});

it('timeline tidak menampilkan commit proyek lain dan dibatasi 10 entri terbaru', function () {
    $other = Project::factory()->active()->for(User::factory()->client()->create(), 'client')->create();
    ProjectGitCommit::factory()->for($other)->create(['commit_message' => 'Rahasia proyek orang lain']);

    foreach (range(1, 12) as $i) {
        ProjectGitCommit::factory()->for($this->project)->create([
            'commit_message' => sprintf('Pembaruan #%02d', $i),
            'pushed_at' => now()->subDays(20 - $i),
        ]);
    }

    $html = $this->actingAs($this->client)
        ->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertDontSee('Rahasia proyek orang lain')
        ->assertSee('Pembaruan #12')
        ->assertSee('Pembaruan #03')
        ->assertDontSee('Pembaruan #02')
        ->assertDontSee('Pembaruan #01')
        ->getContent();

    expect(substr_count($html, 'wire:key="portal-commit-'))->toBe(10);
});

it('kunci terjemahan portal identik di Indonesia dan Inggris', function () {
    $id = Arr::dot(require lang_path('id/portal.php'));
    $en = Arr::dot(require lang_path('en/portal.php'));

    expect(array_keys($id))->toEqualCanonicalizing(array_keys($en));
});
