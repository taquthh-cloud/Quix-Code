<?php

use App\Enums\ActivityAction;
use App\Enums\MilestoneStatus;
use App\Livewire\Portal\ProjectOverview;
use App\Models\ActivityLog;
use App\Models\MilestoneAcceptance;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->client = User::factory()->client()->create(['name' => 'Budi Santoso']);
    $this->project = Project::factory()->active()->for($this->client, 'client')->create();
    $this->done = ProjectMilestone::factory()->for($this->project)->create([
        'title' => 'Desain selesai',
        'status' => MilestoneStatus::Done,
        'sort_order' => 0,
    ]);
});

function acceptCount(): int
{
    return MilestoneAcceptance::query()->count();
}

it('klien pemilik menerima milestone selesai: waktu, IP, dan pelaku tercatat, plus jejak audit', function () {
    $this->actingAs($this->client);

    Livewire::test(ProjectOverview::class, ['project' => $this->project->id])
        ->call('acceptMilestone', $this->done->id)
        ->assertHasNoErrors()
        ->assertSee('Terima kasih')
        ->assertSee('Hasil diterima pada');

    $acceptance = MilestoneAcceptance::query()->firstOrFail();
    expect($acceptance->milestone_id)->toBe($this->done->id)
        ->and($acceptance->client_id)->toBe($this->client->id)
        ->and($acceptance->accepted_at)->not->toBeNull()
        ->and($acceptance->ip)->not->toBeNull();

    $log = ActivityLog::query()->where('action', ActivityAction::MilestoneAccepted->value)->firstOrFail();
    expect($log->user_id)->toBe($this->client->id)
        ->and($log->subject_id)->toBe((string) $this->done->id);
});

it('tombol Terima hasil hanya tampil untuk milestone selesai yang belum diterima', function () {
    $pending = ProjectMilestone::factory()->for($this->project)->create(['status' => MilestoneStatus::Pending, 'sort_order' => 1]);
    $running = ProjectMilestone::factory()->for($this->project)->create(['status' => MilestoneStatus::InProgress, 'sort_order' => 2]);

    $this->actingAs($this->client)
        ->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertSee('data-accept="'.$this->done->id.'"', false)
        ->assertDontSee('data-accept="'.$pending->id.'"', false)
        ->assertDontSee('data-accept="'.$running->id.'"', false);

    MilestoneAcceptance::factory()->create(['milestone_id' => $this->done->id, 'client_id' => $this->client->id]);

    $this->get(route('portal.projects.show', $this->project->id))
        ->assertOk()
        ->assertDontSee('data-accept="'.$this->done->id.'"', false)
        ->assertSee('data-accepted="'.$this->done->id.'"', false);
});

it('milestone yang belum selesai tidak bisa diterima, walau dipanggil langsung', function () {
    $pending = ProjectMilestone::factory()->for($this->project)->create(['status' => MilestoneStatus::InProgress, 'sort_order' => 1]);
    $this->actingAs($this->client);

    Livewire::test(ProjectOverview::class, ['project' => $this->project->id])
        ->call('acceptMilestone', $pending->id)
        ->assertForbidden();

    expect(acceptCount())->toBe(0);
});

it('id milestone proyek klien lain tidak bisa dipalsukan dan tidak menyimpan apa pun', function () {
    $other = User::factory()->client()->create();
    $foreign = ProjectMilestone::factory()
        ->for(Project::factory()->active()->for($other, 'client'))
        ->create(['status' => MilestoneStatus::Done]);

    $this->actingAs($this->client);

    Livewire::test(ProjectOverview::class, ['project' => $this->project->id])
        ->call('acceptMilestone', $foreign->id)
        ->assertNotFound();

    expect(acceptCount())->toBe(0);
});

it('klien lain tidak bisa membuka proyek ini untuk menerima milestone', function () {
    $this->actingAs(User::factory()->client()->create());

    $this->get(route('portal.projects.show', $this->project->id))->assertNotFound();
    expect(acceptCount())->toBe(0);
});

it('admin dan staf tidak pernah diizinkan menerima milestone (policy)', function () {
    $milestone = $this->done->fresh();

    foreach ([User::factory()->admin()->create(), User::factory()->staff()->create()] as $user) {
        expect(Gate::forUser($user)->allows('accept', $milestone))->toBeFalse();
    }

    expect(Gate::forUser($this->client)->allows('accept', $milestone))->toBeTrue();
});

it('klik ganda dan dua tab hanya menghasilkan satu penerimaan dan satu catatan audit', function () {
    $this->actingAs($this->client);

    $tabA = Livewire::test(ProjectOverview::class, ['project' => $this->project->id]);
    $tabB = Livewire::test(ProjectOverview::class, ['project' => $this->project->id]);

    $tabA->call('acceptMilestone', $this->done->id)->assertSee('Terima kasih');
    $tabB->call('acceptMilestone', $this->done->id)->assertHasNoErrors()->assertDontSee('Terima kasih');
    $tabA->call('acceptMilestone', $this->done->id)->assertHasNoErrors();

    expect(acceptCount())->toBe(1)
        ->and(ActivityLog::query()->where('action', ActivityAction::MilestoneAccepted->value)->count())->toBe(1);
});

it('admin melihat siapa yang menerima, lengkap dengan IP; staf melihat nama tanpa IP', function () {
    MilestoneAcceptance::factory()->create([
        'milestone_id' => $this->done->id,
        'client_id' => $this->client->id,
        'ip' => '203.0.113.9',
    ]);

    $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
        ->get(route('admin.projects.show', $this->project->id))
        ->assertOk()
        ->assertSee('Diterima oleh Budi Santoso')
        ->assertSee('203.0.113.9');

    $staff = User::factory()->staff()->create();
    $this->project->staff()->attach($staff->id);

    $this->actingAs($staff)
        ->get(route('admin.projects.show', $this->project->id))
        ->assertOk()
        ->assertSee('Diterima oleh Budi Santoso')
        ->assertDontSee('203.0.113.9');
});

it('jumlah query halaman proyek tidak bertambah saat milestone bertambah (tanpa N+1)', function () {
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->client)->get(route('portal.projects.show', $this->project->id))->assertOk();

        return count(DB::getQueryLog());
    };

    $countQueries(); // pemanasan
    $sedikit = $countQueries();

    foreach (range(1, 5) as $i) {
        $m = ProjectMilestone::factory()->for($this->project)->create(['status' => MilestoneStatus::Done, 'sort_order' => $i]);
        if ($i % 2 === 0) {
            MilestoneAcceptance::factory()->create(['milestone_id' => $m->id, 'client_id' => $this->client->id]);
        }
    }

    expect($countQueries())->toBe($sedikit);
});

it('teks sign-off milestone tersedia dalam dua bahasa dengan kunci yang sama', function () {
    foreach (['portal', 'projects', 'activity'] as $file) {
        $id = Arr::dot(require lang_path("id/{$file}.php"));
        $en = Arr::dot(require lang_path("en/{$file}.php"));

        expect(array_keys($id))->toEqualCanonicalizing(array_keys($en));
    }
});
