<?php

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\TicketStatus;
use App\Http\Middleware\SetLocale;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RevisionTicket;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();
});

it('locale middleware tolerates authenticated users loaded without the locale column', function () {
    $partialAdmin = User::query()->select('id')->findOrFail($this->admin->id);
    $request = Request::create('/');
    $request->setUserResolver(fn () => $partialAdmin);
    // App::setLocale() menimpa config('app.locale'), jadi default dipatok dulu dan tidak diubah ke 'en'.
    app()->setLocale('id');

    app(SetLocale::class)->handle($request, fn () => response('ok'));

    expect(app()->getLocale())->toBe('id');
});

// ------------------------------------------------------------------ navigasi

it('menu admin mencakup semua area, menu staf tanpa tagihan dan prospek', function () {
    $adminKeys = collect(Navigation::itemsFor($this->admin))->pluck('key');
    $staffKeys = collect(Navigation::itemsFor($this->staff))->pluck('key');

    expect($adminKeys)->toContain('dashboard', 'leads', 'projects', 'kanban', 'billing', 'portfolio', 'team', 'activity')
        ->and($staffKeys->all())->toBe(['dashboard', 'projects', 'kanban'])
        ->and(Navigation::itemsFor($this->client))->toBe([]);
});

it('sidebar hanya menampilkan menu yang route-nya sudah ada', function () {
    $keys = collect(Navigation::sidebarFor($this->admin))->pluck('key');

    expect($keys)->toContain('dashboard');
    foreach (Navigation::sidebarFor($this->admin) as $item) {
        expect(Route::has($item['route']))->toBeTrue();
    }
});

// ------------------------------------------------------------------ command center

it('admin melihat command center dengan ringkasan lengkap', function () {
    Project::factory()->count(2)->for($this->client, 'client')->create(); // lead
    Project::factory()->active()->for($this->client, 'client')->create();
    Invoice::factory()->for(Project::factory()->active()->for($this->client, 'client'))->create(['amount' => 5_000_000]);
    Invoice::factory()->for(Project::factory()->active()->for($this->client, 'client'))
        ->create(['status' => InvoiceStatus::AwaitingVerification]);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Command Center')
        ->assertSee('Tagihan belum dibayar')
        ->assertSee('Rp 5.000.000')
        ->assertViewHas('stats', fn ($s) => $s['leads'] === 2 && $s['active'] === 3 && $s['awaiting_verification'] === 1);
});

it('staf hanya melihat angka proyek yang ditugaskan dan tidak melihat tagihan', function () {
    $mine = Project::factory()->active()->for($this->client, 'client')->create(['final_price' => 77_000_000]);
    Project::factory()->active()->for($this->client, 'client')->create();
    $mine->staff()->attach($this->staff->id);
    Invoice::factory()->for($mine)->create();
    RevisionTicket::factory()->for($mine)->create(['status' => TicketStatus::Open]);

    $this->actingAs($this->staff)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Tagihan belum dibayar')
        ->assertDontSee('77.000.000')
        ->assertViewHas('stats', fn ($s) => $s['active'] === 1
            && $s['open_tickets'] === 1
            && $s['unpaid'] === null);
});

it('klien tidak bisa membuka command center', function () {
    $this->actingAs($this->client)->get(route('admin.dashboard'))->assertForbidden();
});

it('jumlah query dashboard tidak bertambah saat proyek bertambah (tanpa N+1)', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        return count(DB::getQueryLog());
    };

    Project::factory()->for($this->client, 'client')->create();
    $countQueries(); // pemanasan: query sekali-jalan (cache/skema) tidak dihitung
    $sedikit = $countQueries();

    Project::factory()->count(8)->create(); // masing-masing dengan klien sendiri
    $banyak = $countQueries();

    expect($banyak)->toBe($sedikit);
});

// ------------------------------------------------------------------ activity logger

it('mencatat aksi dengan pelaku, subjek, dan properti', function () {
    $project = Project::factory()->for($this->client, 'client')->create();

    $log = ActivityLogger::changed(ActivityAction::StatusChanged, $project, ProjectStatus::Lead, ProjectStatus::Quoted, $this->admin);

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->subject_type)->toBe(Project::class)
        ->and($log->subject_id)->toBe($project->id)
        ->and($log->action)->toBe('status_changed')
        ->and($log->properties)->toBe(['old' => 'lead', 'new' => 'quoted'])
        ->and($log->subject->is($project))->toBeTrue();
});

it('memakai user yang sedang login dan mencatat aksi sistem bila tidak ada', function () {
    $project = Project::factory()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    expect(ActivityLogger::log(ActivityAction::PriceChanged, $project)->user_id)->toBe($this->admin->id);

    auth()->forgetUser();
    $system = ActivityLogger::log(ActivityAction::InvoicePaid, $project, ['via' => 'webhook']);
    expect($system->user_id)->toBeNull()
        ->and($system->properties)->toBe(['via' => 'webhook'])
        ->and(ActivityLog::count())->toBe(2);
});

it('setiap aksi punya label dalam dua bahasa', function () {
    foreach (['id', 'en'] as $locale) {
        app()->setLocale($locale);
        foreach (ActivityAction::cases() as $action) {
            expect($action->label())->not->toStartWith('activity.');
        }
    }
});
