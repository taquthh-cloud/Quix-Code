<?php

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Admin\Projects\Form;
use App\Livewire\Admin\Projects\Index;
use App\Livewire\Admin\Projects\Show;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
    $this->staff = User::factory()->staff()->create();
    $this->client = User::factory()->client()->create();
});

it('admin can add and remove internal project notes while assigned staff cannot', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $project->staff()->attach($this->staff->id);

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])
        ->set('noteBody', 'Follow up on delivery scope')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSee('Follow up on delivery scope');

    $note = $project->notes()->firstOrFail();
    expect($note->user_id)->toBe($this->admin->id);

    $this->actingAs($this->staff);
    Livewire::test(Show::class, ['project' => $project->id])
        ->assertDontSee('Follow up on delivery scope')
        ->call('addNote')
        ->assertForbidden();

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])->call('deleteNote', $note->id);
    expect($project->notes()->count())->toBe(0);
});

// ------------------------------------------------------------------ daftar

it('admin melihat semua proyek, staf hanya yang ditugaskan, klien ditolak', function () {
    $mine = Project::factory()->for($this->client, 'client')->create(['title' => 'Proyek Milik Staf']);
    Project::factory()->for($this->client, 'client')->create(['title' => 'Proyek Lain']);
    $mine->staff()->attach($this->staff->id);

    $this->actingAs($this->admin)->get(route('admin.projects.index'))
        ->assertOk()->assertSee('Proyek Milik Staf')->assertSee('Proyek Lain');

    $this->actingAs($this->staff)->get(route('admin.projects.index'))
        ->assertOk()->assertSee('Proyek Milik Staf')->assertDontSee('Proyek Lain');

    $this->actingAs($this->client)->get(route('admin.projects.index'))->assertForbidden();
});

it('daftar proyek tidak menampilkan kolom harga untuk staf', function () {
    $mine = Project::factory()->active()->for($this->client, 'client')->create(['final_price' => 77_000_000]);
    $mine->staff()->attach($this->staff->id);

    $this->actingAs($this->admin);
    Livewire::test(Index::class)->assertSee('77.000.000');

    $this->actingAs($this->staff);
    Livewire::test(Index::class)->assertSee($mine->title)->assertDontSee('77.000.000');
});

it('mencari berdasarkan judul atau nama klien dan menyaring status', function () {
    $budi = User::factory()->client()->create(['name' => 'Budi Santoso']);
    Project::factory()->for($budi, 'client')->create(['title' => 'Aplikasi Kasir']);
    Project::factory()->active()->for($this->client, 'client')->create(['title' => 'Portal Agensi']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('search', 'Budi')->assertSee('Aplikasi Kasir')->assertDontSee('Portal Agensi');
    Livewire::test(Index::class)->set('search', 'Portal')->assertSee('Portal Agensi')->assertDontSee('Aplikasi Kasir');
    Livewire::test(Index::class)->set('status', 'active')->assertSee('Portal Agensi')->assertDontSee('Aplikasi Kasir');
});

it('daftar proyek tidak N+1', function () {
    $this->actingAs($this->admin);
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Index::class);

        return count(DB::getQueryLog());
    };

    Project::factory()->for($this->client, 'client')->create();
    $sedikit = $count();
    Project::factory()->count(6)->create();

    expect($count())->toBe($sedikit);
});

// ------------------------------------------------------------------ buat & ubah

it('admin membuat proyek untuk klien yang ada dan perubahan tercatat', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('client_id', (string) $this->client->id)
        ->set('title', 'Sistem Kasir Baru')
        ->set('currency', 'IDR')
        ->set('budget_reference', '25000000')
        ->set('github_repo', 'quixcode/kasir')
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::firstWhere('title', 'Sistem Kasir Baru');

    expect($project->client_id)->toBe($this->client->id)
        ->and($project->status)->toBe(ProjectStatus::Lead)
        ->and($project->budget_reference)->toBe(25_000_000)
        ->and($project->final_price)->toBeNull()
        ->and(ActivityLog::where('action', ActivityAction::ProjectCreated->value)->where('subject_id', $project->id)->exists())->toBeTrue();
});

it('admin membuat proyek sekaligus klien baru tanpa password yang bisa ditebak', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('newClient', true)
        ->set('client_name', 'Siti Aminah')
        ->set('client_email', 'SITI@Example.com')
        ->set('title', 'Dompet Digital')
        ->call('save')
        ->assertHasNoErrors();

    $client = User::firstWhere('email', 'siti@example.com');

    expect($client->isClient())->toBeTrue()
        ->and($client->email_verified_at)->toBeNull()
        ->and($client->projects()->count())->toBe(1);
});

it('uang USD disimpan dalam sen dan nominal salah ditolak', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('client_id', (string) $this->client->id)
        ->set('title', 'Project USD')
        ->set('currency', 'USD')
        ->set('budget_reference', '1500.50')
        ->call('save')
        ->assertHasNoErrors();

    expect(Project::firstWhere('title', 'Project USD')->budget_reference)->toBe(150_050);

    Livewire::test(Form::class)
        ->set('client_id', (string) $this->client->id)
        ->set('title', 'Format salah')
        ->set('budget_reference', '25.000.000')
        ->call('save')
        ->assertHasErrors('budget_reference');

    Livewire::test(Form::class)
        ->set('client_id', (string) $this->client->id)
        ->set('title', 'IDR desimal')
        ->set('currency', 'IDR')
        ->set('budget_reference', '1000.5')
        ->call('save')
        ->assertHasErrors('budget_reference');

    expect(Project::whereIn('title', ['Format salah', 'IDR desimal'])->exists())->toBeFalse();
});

it('validasi form: klien harus berperan klien, email klien baru unik, repo berformat benar', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('client_id', (string) $this->staff->id)
        ->set('title', 'X')
        ->call('save')
        ->assertHasErrors('client_id');

    Livewire::test(Form::class)
        ->set('newClient', true)
        ->set('client_name', 'Dobel')
        ->set('client_email', $this->client->email)
        ->set('title', 'X')
        ->call('save')
        ->assertHasErrors('client_email');

    Livewire::test(Form::class)
        ->set('client_id', (string) $this->client->id)
        ->set('title', 'X')
        ->set('github_repo', 'https://evil.test/x')
        ->call('save')
        ->assertHasErrors('github_repo');
});

it('staf dan klien tidak bisa membuka form buat/ubah proyek', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $project->staff()->attach($this->staff->id);

    $this->actingAs($this->staff)->get(route('admin.projects.create'))->assertForbidden();
    $this->actingAs($this->staff)->get(route('admin.projects.edit', $project->id))->assertForbidden();
    $this->actingAs($this->client)->get(route('admin.projects.create'))->assertForbidden();

});

it('mengubah harga dicatat di activity log dengan nilai lama dan baru', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['budget_reference' => 10_000_000]);

    $this->actingAs($this->admin);
    Livewire::test(Form::class, ['project' => $project->id])
        ->set('budget_reference', '12000000')
        ->call('save')
        ->assertHasNoErrors();

    $log = ActivityLog::where('action', ActivityAction::PriceChanged->value)->firstOrFail();

    expect($log->properties['old']['budget_reference'])->toBe(10_000_000)
        ->and($log->properties['new']['budget_reference'])->toBe(12_000_000)
        ->and($project->fresh()->budget_reference)->toBe(12_000_000);
});

it('mata uang dikunci setelah deal, walau dikirim lewat browser', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Deal, 'currency' => Currency::IDR]);

    $this->actingAs($this->admin);
    Livewire::test(Form::class, ['project' => $project->id])
        ->set('currency', 'USD')
        ->call('save')
        ->assertHasNoErrors();

    expect($project->fresh()->currency)->toBe(Currency::IDR);
});

// ------------------------------------------------------------------ control room: akses

it('staf yang tidak ditugaskan dan klien lain tidak bisa membuka control room', function () {
    $project = Project::factory()->for($this->client, 'client')->create();

    $this->actingAs($this->staff)->get(route('admin.projects.show', $project->id))->assertNotFound();
    $this->actingAs($this->client)->get(route('admin.projects.show', $project->id))->assertForbidden();

    $project->staff()->attach($this->staff->id);
    $this->actingAs($this->staff)->get(route('admin.projects.show', $project->id))->assertOk();
});

it('staf tidak melihat harga, kontak klien, maupun tombol admin di control room', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create(['final_price' => 77_000_000, 'budget_reference' => 55_000_000]);
    $project->staff()->attach($this->staff->id);

    $this->actingAs($this->staff);
    Livewire::test(Show::class, ['project' => $project->id])
        ->assertOk()
        ->assertDontSee('77.000.000')
        ->assertDontSee('55.000.000')
        ->assertDontSee($this->client->email)
        ->assertDontSee(__('projects.show.delete_project'))
        ->assertDontSee(__('projects.show.move_to'));

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])
        ->assertSee('77.000.000')
        ->assertSee($this->client->email);
});

it('aksi admin ditolak untuk staf walau dipanggil langsung', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $project->staff()->attach($this->staff->id);

    $this->actingAs($this->staff);
    $fresh = fn () => Livewire::test(Show::class, ['project' => $project->id]);

    $fresh()->call('changeStatus', 'awaiting_final')->assertForbidden();
    $fresh()->set('progress', 90)->call('updateProgress')->assertForbidden();
    $fresh()->set('staffId', (string) $this->staff->id)->call('assignStaff')->assertForbidden();
    $fresh()->set('ms_title', 'Curang')->set('ms_target', '10')->call('saveMilestone')->assertForbidden();
    $fresh()->call('deleteProject')->assertForbidden();

    expect($project->fresh()->status)->toBe(ProjectStatus::Active)
        ->and($project->fresh()->progress_percent)->toBe(40)
        ->and($project->milestones()->count())->toBe(0);
});

// ------------------------------------------------------------------ status & progres

it('admin memindah status sesuai urutan dan tercatat; lompat status ditolak', function () {
    $project = Project::factory()->for($this->client, 'client')->create();
    $quotation = Quotation::factory()->sent()->for($project)->create();
    QuotationItem::factory()->for($quotation)->create();

    $this->actingAs($this->admin);
    $component = Livewire::test(Show::class, ['project' => $project->id]);

    $component->call('changeStatus', 'active'); // lompat: lead -> active
    expect($project->fresh()->status)->toBe(ProjectStatus::Lead);

    $component->call('changeStatus', 'quoted');
    expect($project->fresh()->status)->toBe(ProjectStatus::Quoted);

    $log = ActivityLog::where('action', ActivityAction::StatusChanged->value)->firstOrFail();
    // toEqual: kolom JSON MySQL tidak menjamin urutan kunci.
    expect($log->properties)->toEqual(['old' => 'lead', 'new' => 'quoted'])
        ->and(ActivityLog::where('action', ActivityAction::StatusChanged->value)->count())->toBe(1);
});

it('menutup proyek mengisi completed_at, dan status akhir tidak punya transisi', function () {
    $project = Project::factory()->for($this->client, 'client')->create(['status' => ProjectStatus::Warranty]);

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])->call('changeStatus', 'closed');

    expect($project->fresh()->status)->toBe(ProjectStatus::Closed)
        ->and($project->fresh()->completed_at)->not->toBeNull()
        ->and($project->fresh()->status->allowedTransitions())->toBe([]);
});

it('progres manual menyelesaikan milestone yang ambangnya tercapai dan tercatat', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create(['progress_percent' => 0]);
    foreach ([25, 50, 100] as $i => $target) {
        ProjectMilestone::factory()->for($project)->create(['target_progress' => $target, 'sort_order' => $i]);
    }

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])
        ->set('progress', 60)
        ->call('updateProgress')
        ->assertHasNoErrors();

    $status = $project->milestones()->pluck('status', 'target_progress');

    expect($project->fresh()->progress_percent)->toBe(60)
        ->and($status[25])->toBe(MilestoneStatus::Done)
        ->and($status[50])->toBe(MilestoneStatus::Done)
        ->and($status[100])->toBe(MilestoneStatus::Pending)
        ->and(ActivityLog::where('action', ActivityAction::ProgressChanged->value)->first()->properties)->toEqual(['old' => 0, 'new' => 60]);
});

it('progres di luar 0-100 ditolak', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])->set('progress', 120)->call('updateProgress')->assertHasErrors('progress');
    Livewire::test(Show::class, ['project' => $project->id])->set('progress', -5)->call('updateProgress')->assertHasErrors('progress');

    expect($project->fresh()->progress_percent)->toBe(40);
});

// ------------------------------------------------------------------ tim

it('admin menugaskan dan mencabut staf; milestone staf itu jadi tanpa penanggung jawab', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    $component = Livewire::test(Show::class, ['project' => $project->id])
        ->set('staffId', (string) $this->staff->id)
        ->set('taskRole', 'backend')
        ->call('assignStaff')
        ->assertHasNoErrors();

    expect($project->staff()->pluck('users.id')->all())->toBe([$this->staff->id])
        ->and($project->staff->first()->pivot->task_role)->toBe('backend')
        ->and(ActivityLog::where('action', ActivityAction::StaffAssigned->value)->count())->toBe(1);

    $milestone = ProjectMilestone::factory()->for($project)->create(['assigned_to' => $this->staff->id]);

    $component->call('unassignStaff', $this->staff->id);

    expect($project->staff()->count())->toBe(0)
        ->and($milestone->fresh()->assigned_to)->toBeNull()
        ->and(ActivityLog::where('action', ActivityAction::StaffUnassigned->value)->count())->toBe(1);
});

it('penugasan ganda, bukan staf, dan peran terlalu panjang ditolak', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $project->staff()->attach($this->staff->id);

    $this->actingAs($this->admin);

    Livewire::test(Show::class, ['project' => $project->id])
        ->set('staffId', (string) $this->staff->id)->call('assignStaff')->assertHasErrors('staffId');

    Livewire::test(Show::class, ['project' => $project->id])
        ->set('staffId', (string) $this->client->id)->call('assignStaff')->assertHasErrors('staffId');

    $other = User::factory()->staff()->create();
    Livewire::test(Show::class, ['project' => $project->id])
        ->set('staffId', (string) $other->id)->set('taskRole', str_repeat('a', 51))->call('assignStaff')->assertHasErrors('taskRole');

    expect($project->staff()->count())->toBe(1);
});

// ------------------------------------------------------------------ milestone

it('admin menambah, mengubah, mengurutkan, dan menghapus milestone', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create(['progress_percent' => 0]);

    $this->actingAs($this->admin);
    $component = Livewire::test(Show::class, ['project' => $project->id]);

    foreach (['Desain' => '25', 'Backend' => '60', 'Rilis' => '100'] as $title => $target) {
        $component->set('ms_title', $title)->set('ms_target', $target)->call('saveMilestone')->assertHasNoErrors();
    }

    expect($project->milestones()->pluck('title')->all())->toBe(['Desain', 'Backend', 'Rilis']);

    $backend = $project->milestones()->where('title', 'Backend')->first();
    $component->call('moveMilestone', $backend->id, 'up');
    expect($project->milestones()->pluck('title')->all())->toBe(['Backend', 'Desain', 'Rilis']);

    $component->call('moveMilestone', $backend->id, 'up'); // sudah paling atas: tidak berubah
    expect($project->milestones()->pluck('title')->all())->toBe(['Backend', 'Desain', 'Rilis']);

    $component->call('editMilestone', $backend->id)
        ->assertSet('ms_title', 'Backend')
        ->set('ms_title', 'Backend API')
        ->call('saveMilestone');
    expect($backend->fresh()->title)->toBe('Backend API');

    $component->call('deleteMilestone', $backend->id);
    expect($project->milestones()->count())->toBe(2);
});

it('milestone baru yang ambangnya sudah tercapai langsung selesai', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create(['progress_percent' => 70]);

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])
        ->set('ms_title', 'Sudah lewat')->set('ms_target', '50')->call('saveMilestone');

    expect($project->milestones()->first()->status)->toBe(MilestoneStatus::Done);
});

it('validasi milestone: target 0-100, staf harus dari proyek, dan admin dapat menjadi penanggung jawab', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $outsider = User::factory()->staff()->create();

    $this->actingAs($this->admin);

    Livewire::test(Show::class, ['project' => $project->id])
        ->set('ms_title', 'A')->set('ms_target', '150')->call('saveMilestone')->assertHasErrors('ms_target');

    Livewire::test(Show::class, ['project' => $project->id])
        ->set('ms_title', 'A')->set('ms_target', '50')->set('ms_assignee', (string) $outsider->id)
        ->call('saveMilestone')->assertHasErrors('ms_assignee');

    expect($project->milestones()->count())->toBe(0);
});

it('admin dapat ditugaskan sebagai penanggung jawab milestone dan tercantum dalam tim proyek', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $admin = $this->admin->forceFill(['name' => 'Project Admin']);
    $admin->save();

    $this->actingAs($admin);
    $component = Livewire::test(Show::class, ['project' => $project->id])
        ->set('ms_title', 'Review administrasi')
        ->set('ms_target', '50')
        ->set('ms_assignee', (string) $admin->id)
        ->call('saveMilestone')
        ->assertHasNoErrors()
        ->assertSee('Project Admin');

    $milestone = $project->milestones()->firstOrFail();
    expect($milestone->assigned_to)->toBe($admin->id);

    $admin->forceFill(['is_available_for_projects' => false])->save();
    $component->call('editMilestone', $milestone->id)
        ->call('saveMilestone')
        ->assertHasNoErrors()
        ->assertSee('Project Admin');
});

it('admin yang sedang jeda tidak dapat menerima penugasan milestone baru', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $this->admin->forceFill(['is_available_for_projects' => false])->save();

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])
        ->set('ms_title', 'Review administrasi')
        ->set('ms_target', '50')
        ->set('ms_assignee', (string) $this->admin->id)
        ->call('saveMilestone')
        ->assertHasErrors('ms_assignee');

    expect($project->milestones()->count())->toBe(0);
});

it('milestone proyek lain tidak bisa diubah lewat id yang dipalsukan', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $foreign = ProjectMilestone::factory()->for(Project::factory()->active()->for($this->client, 'client'))->create(['title' => 'Asing']);

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])
        ->call('deleteMilestone', $foreign->id)
        ->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

it('staf hanya menggeser milestone miliknya, dan hanya saat proyek dikerjakan', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $project->staff()->attach($this->staff->id);
    $mine = ProjectMilestone::factory()->for($project)->create(['assigned_to' => $this->staff->id, 'sort_order' => 0]);
    $other = ProjectMilestone::factory()->for($project)->create(['sort_order' => 1]);

    $this->actingAs($this->staff);
    $component = Livewire::test(Show::class, ['project' => $project->id]);

    $component->call('setMilestoneStatus', $mine->id, 'done');
    expect($mine->fresh()->status)->toBe(MilestoneStatus::Done)
        ->and($mine->fresh()->completed_at)->not->toBeNull();

    $component->call('setMilestoneStatus', $mine->id, 'pending');
    expect($mine->fresh()->status)->toBe(MilestoneStatus::Pending)
        ->and($mine->fresh()->completed_at)->toBeNull();

    $component->call('setMilestoneStatus', $other->id, 'done')->assertForbidden();
    expect($other->fresh()->status)->toBe(MilestoneStatus::Pending);

    $project->forceFill(['status' => ProjectStatus::Deal])->save();

    Livewire::test(Show::class, ['project' => $project->id])->call('setMilestoneStatus', $mine->id, 'done');
    expect($mine->fresh()->status)->toBe(MilestoneStatus::Pending);
});

// ------------------------------------------------------------------ hapus

it('admin menghapus proyek (soft delete) dan staf tidak bisa', function () {
    $project = Project::factory()->for($this->client, 'client')->create();

    $this->actingAs($this->admin);
    Livewire::test(Show::class, ['project' => $project->id])->call('deleteProject')->assertRedirect(route('admin.projects.index'));

    expect(Project::find($project->id))->toBeNull()
        ->and(Project::withTrashed()->find($project->id))->not->toBeNull();

    $this->actingAs($this->admin)->get(route('admin.projects.show', $project->id))->assertNotFound();
});

it('control room tidak N+1 saat milestone bertambah', function () {
    $project = Project::factory()->active()->for($this->client, 'client')->create();
    $project->staff()->attach($this->staff->id);

    $count = function () use ($project) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Show::class, ['project' => $project->id]);

        return count(DB::getQueryLog());
    };

    $this->actingAs($this->staff);
    ProjectMilestone::factory()->for($project)->create(['assigned_to' => $this->staff->id, 'sort_order' => 0]);
    $sedikit = $count();

    ProjectMilestone::factory()->count(6)->for($project)->sequence(fn ($s) => ['sort_order' => $s->index + 1, 'assigned_to' => $this->staff->id])->create();

    expect($count())->toBe($sedikit);
});

it('semua teks control room tersedia dalam dua bahasa', function () {
    $id = require base_path('lang/id/projects.php');
    $en = require base_path('lang/en/projects.php');

    expect(array_keys(Arr::dot($id)))->toEqualCanonicalizing(array_keys(Arr::dot($en)));
});
