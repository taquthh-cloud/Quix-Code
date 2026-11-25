<?php

use App\Enums\FileVisibility;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use App\Support\InvoiceNumber;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;

it('allocates invoice numbers from the year sequence', function () {
    expect(InvoiceNumber::next(Carbon::create(2026, 4, 10)))->toBe('QC/2026/0001')
        ->and(InvoiceNumber::next(Carbon::create(2026, 4, 10)))->toBe('QC/2026/0002')
        ->and(InvoiceNumber::next(Carbon::create(2027, 1, 1)))->toBe('QC/2027/0001');
});

it('prevents deleting an invoice and requires a reason to void it', function () {
    $invoice = Invoice::factory()->create();

    expect(fn () => $invoice->delete())->toThrow(LogicException::class)
        ->and(fn () => $invoice->void())->toThrow(InvalidArgumentException::class);
});

it('requires an explicit reason for a project cancellation and records the transition', function () {
    $client = User::factory()->client()->create();
    $admin = User::factory()->admin()->withTwoFactor()->create();
    $project = Project::factory()->for($client, 'client')->create();

    expect(fn () => $project->transitionTo(ProjectStatus::Cancelled, $admin))
        ->toThrow(InvalidArgumentException::class);

    $project->transitionTo(ProjectStatus::Cancelled, $admin, 'Client cancelled the engagement.');

    expect($project->fresh()->status)->toBe(ProjectStatus::Cancelled)
        ->and(ProjectStatusHistory::query()->where('project_id', $project->id)->value('reason'))
        ->toBe('Client cancelled the engagement.');
});

it('cannot move a deal to awaiting dp while its down payment invoice is a draft', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->for($client, 'client')->create(['status' => ProjectStatus::Deal]);
    $invoice = Invoice::factory()->for($project)->create([
        'type' => InvoiceType::Dp,
        'status' => InvoiceStatus::Draft,
    ]);

    expect(fn () => $project->transitionTo(ProjectStatus::AwaitingDp))
        ->toThrow(InvalidArgumentException::class);

    $invoice->forceFill(['status' => InvoiceStatus::Sent, 'issued_at' => now()])->save();
    $project->transitionTo(ProjectStatus::AwaitingDp);

    expect($project->fresh()->status)->toBe(ProjectStatus::AwaitingDp);
});

it('cannot move a deal to awaiting dp with an unpaid down payment invoice that was never issued', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->for($client, 'client')->create(['status' => ProjectStatus::Deal]);
    $invoice = Invoice::factory()->for($project)->create([
        'type' => InvoiceType::Dp,
        'status' => InvoiceStatus::Unpaid,
        'issued_at' => null,
    ]);

    expect(fn () => $project->transitionTo(ProjectStatus::AwaitingDp))
        ->toThrow(InvalidArgumentException::class);

    $invoice->forceFill(['issued_at' => now()])->save();
    $project->transitionTo(ProjectStatus::AwaitingDp);

    expect($project->fresh()->status)->toBe(ProjectStatus::AwaitingDp);
});

it('cannot activate a project until the down payment invoice is fully paid', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->for($client, 'client')->create(['status' => ProjectStatus::AwaitingDp]);
    Invoice::factory()->for($project)->create(['type' => InvoiceType::Dp]);

    expect(fn () => $project->transitionTo(ProjectStatus::Active))
        ->toThrow(InvalidArgumentException::class);
});

it('deactivating staff releases their project assignments and records the change', function () {
    $client = User::factory()->client()->create();
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for($client, 'client')->create();
    $project->staff()->attach($staff->id);

    $staff->deactivate();

    expect($staff->fresh()->is_active)->toBeFalse()
        ->and($staff->fresh()->assignedProjects()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', 'user_deactivated')->count())->toBe(1);
});

it('allows a client to download only client-visible files in their own project', function () {
    $owner = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $project = Project::factory()->for($owner, 'client')->create(['status' => ProjectStatus::Handover]);
    Invoice::factory()->final()->paid()->for($project)->create();
    $file = ProjectFile::query()->create([
        'project_id' => $project->id,
        'uploaded_by' => $owner->id,
        'disk' => 'private',
        'path' => 'projects/private.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
        'visibility' => FileVisibility::Client,
    ]);

    expect($owner->can('view', $file))->toBeTrue()
        ->and($other->can('view', $file))->toBeFalse();
});
