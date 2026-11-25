<?php

use App\Enums\ProjectStatus;
use App\Models\CaseStudy;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('memakai ULID sebagai primary key proyek', function () {
    $project = Project::factory()->create();

    expect($project->id)->toBeString()->toHaveLength(26);
});

it('menghubungkan proyek dengan klien, staf, milestone, dan invoice', function () {
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->create();
    $project->staff()->attach($staff->id, ['task_role' => 'backend']);
    ProjectMilestone::factory()->for($project)->create();
    Invoice::factory()->for($project)->create();

    $project->refresh();

    expect($project->client->isClient())->toBeTrue()
        ->and($project->staff)->toHaveCount(1)
        ->and($project->staff->first()->pivot->task_role)->toBe('backend')
        ->and($project->milestones)->toHaveCount(1)
        ->and($project->invoices)->toHaveCount(1)
        ->and($staff->assignedProjects)->toHaveCount(1);
});

it('scope visibleTo membatasi proyek per peran', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    $staff = User::factory()->staff()->create();
    $clientA = User::factory()->client()->create();
    $clientB = User::factory()->client()->create();

    $projectA = Project::factory()->for($clientA, 'client')->create();
    $projectB = Project::factory()->for($clientB, 'client')->create();
    $projectA->staff()->attach($staff->id);

    expect(Project::visibleTo($admin)->count())->toBe(2)
        ->and(Project::visibleTo($staff)->pluck('id')->all())->toBe([$projectA->id])
        ->and(Project::visibleTo($clientA)->pluck('id')->all())->toBe([$projectA->id])
        ->and(Project::visibleTo($clientB)->pluck('id')->all())->toBe([$projectB->id]);
});

it('memindah status proyek hanya lewat transisi valid', function () {
    $project = Project::factory()->create();
    $quotation = Quotation::factory()->sent()->for($project)->create();
    QuotationItem::factory()->for($quotation)->create();

    $project->transitionTo(ProjectStatus::Quoted);
    expect($project->fresh()->status)->toBe(ProjectStatus::Quoted);

    expect(fn () => $project->transitionTo(ProjectStatus::Handover))
        ->toThrow(InvalidArgumentException::class);
});

it('mengenkripsi webhook_secret di database dan menyembunyikannya dari serialisasi', function () {
    $project = Project::factory()->create(['webhook_secret' => 'rahasia-hmac']);

    $raw = DB::table('projects')->where('id', $project->id)->value('webhook_secret');

    expect($raw)->not->toBe('rahasia-hmac')
        ->and($project->fresh()->webhook_secret)->toBe('rahasia-hmac')
        ->and($project->toArray())->not->toHaveKey('webhook_secret');
});

it('menampilkan uang invoice dengan mata uang proyek', function () {
    $invoice = Invoice::factory()->create(['amount' => 5_000_000]);

    expect($invoice->money()->format('id'))->toBe('Rp 5.000.000');
});

it('mengambil teks case study sesuai locale', function () {
    $study = CaseStudy::factory()->create([
        'title' => ['id' => 'Sistem Kasir', 'en' => 'POS System'],
    ]);

    expect($study->localized('title', 'id'))->toBe('Sistem Kasir')
        ->and($study->localized('title', 'en'))->toBe('POS System')
        ->and($study->localized('title', 'fr'))->toBe('Sistem Kasir');
});
