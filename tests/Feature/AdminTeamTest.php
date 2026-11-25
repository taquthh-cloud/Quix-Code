<?php

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Livewire\Admin\Team\ClientPreview;
use App\Livewire\Admin\Team\Index;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('id');
    $this->admin = User::factory()->admin()->withTwoFactor()->create();
});

it('admin creates staff accounts with a random password and sends a password setup link', function () {
    Notification::fake();

    $this->actingAs($this->admin);
    Livewire::test(Index::class)
        ->set('name', 'Rani Putri')
        ->set('email', 'rani@example.test')
        ->set('role', 'staff')
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'rani@example.test')->firstOrFail();
    expect($user->name)->toBe('Rani Putri')
        ->and($user->role)->toBe(UserRole::Staff)
        ->and($user->is_active)->toBeTrue()
        ->and($user->password)->not->toBe('')
        ->and(ActivityLog::where('subject_id', (string) $user->id)->where('action', ActivityAction::UserCreated->value)->exists())->toBeTrue();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('rejects admin role, invalid email, and duplicate email when creating users', function () {
    User::factory()->client()->create(['email' => 'existing@example.test']);

    $this->actingAs($this->admin);
    Livewire::test(Index::class)
        ->set('name', 'New Person')
        ->set('email', 'existing@example.test')
        ->set('role', 'admin')
        ->call('createUser')
        ->assertHasErrors(['email', 'role']);

    expect(User::where('name', 'New Person')->exists())->toBeFalse();
});

it('admin can deactivate a staff member and assignments are detached', function () {
    $client = User::factory()->client()->create();
    $staff = User::factory()->staff()->create();
    $project = Project::factory()->for($client, 'client')->create();
    $project->staff()->attach($staff->id);
    $milestone = ProjectMilestone::factory()->for($project)->create(['assigned_to' => $staff->id]);

    $this->actingAs($this->admin);
    Livewire::test(Index::class)->call('toggleActive', $staff->id)->assertHasNoErrors();

    expect($staff->fresh()->is_active)->toBeFalse()
        ->and($project->staff()->whereKey($staff->id)->exists())->toBeFalse()
        ->and($milestone->fresh()->assigned_to)->toBeNull()
        ->and(ActivityLog::where('subject_id', (string) $staff->id)->where('action', ActivityAction::UserDeactivated->value)->exists())->toBeTrue();
});

it('admin can change roles only when project ownership and assignments are empty', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($this->admin);
    Livewire::test(Index::class)
        ->call('changeRole', $staff->id, 'client')
        ->assertHasNoErrors();

    expect($staff->fresh()->role)->toBe(UserRole::Client)
        ->and(ActivityLog::where('subject_id', (string) $staff->id)
            ->where('action', ActivityAction::UserRoleChanged->value)
            ->exists())->toBeTrue();

    $project = Project::factory()->for($staff->fresh(), 'client')->create();
    Livewire::test(Index::class)
        ->call('changeRole', $staff->id, 'staff')
        ->assertHasErrors("roleChanges.{$staff->id}");

    expect($project->fresh()->client_id)->toBe($staff->id)
        ->and($staff->fresh()->role)->toBe(UserRole::Client);
});

it('admin can reset a user two-factor enrollment and the action is audited', function () {
    $staff = User::factory()->staff()->withTwoFactor()->create();

    $this->actingAs($this->admin);
    Livewire::test(Index::class)->call('resetTwoFactor', $staff->id)->assertHasNoErrors();

    expect($staff->fresh()->two_factor_secret)->toBeNull()
        ->and($staff->fresh()->two_factor_recovery_codes)->toBeNull()
        ->and($staff->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and(ActivityLog::where('subject_id', (string) $staff->id)
            ->where('action', ActivityAction::UserTwoFactorReset->value)
            ->exists())->toBeTrue();
});

it('admin starts an audited read-only client preview with a required reason', function () {
    $client = User::factory()->client()->create();
    $project = Project::factory()->for($client, 'client')->create();

    $this->actingAs($this->admin);
    Livewire::test(Index::class)
        ->set("previewReasons.{$client->id}", 'Support request review')
        ->call('previewClient', $client->id)
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.team.preview', ['client' => $client->id]));

    expect(ActivityLog::where('subject_id', (string) $client->id)
        ->where('action', ActivityAction::ClientPreviewStarted->value)
        ->exists())->toBeTrue();

    Livewire::test(ClientPreview::class, ['client' => $client->id])
        ->assertSee('Support request review')
        ->assertSee($project->title)
        ->call('endPreview')
        ->assertRedirect(route('admin.team.index'));
});

it('admins can pause project assignments without deactivating their account', function () {
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSee($this->admin->name)
        ->assertSee($this->admin->email)
        ->assertSee($this->admin->role->label())
        ->assertSee(__('admin.team.set_unavailable_for_projects'))
        ->assertSee(__('admin.team.admin_control'))
        ->call('changeRole', $this->admin->id, 'staff')->assertNotFound();

    Livewire::test(Index::class)->call('toggleActive', $this->admin->id)->assertNotFound();
    Livewire::test(Index::class)->call('toggleProjectAvailability');

    expect($this->admin->fresh()->role)->toBe(UserRole::Admin)
        ->and($this->admin->fresh()->is_active)->toBeTrue()
        ->and($this->admin->fresh()->is_available_for_projects)->toBeFalse()
        ->and(ActivityLog::where('subject_id', (string) $this->admin->id)
            ->where('action', ActivityAction::AdminProjectAvailabilityChanged->value)
            ->exists())->toBeTrue();

    $this->actingAs($otherAdmin);
    Livewire::test(Index::class)->call('toggleProjectAvailability');
    expect($otherAdmin->fresh()->is_available_for_projects)->toBeFalse()
        ->and($this->admin->fresh()->is_available_for_projects)->toBeFalse();
});

it('only admins can open the team directory', function () {
    $staff = User::factory()->staff()->create();
    $client = User::factory()->client()->create();

    $this->actingAs($staff)->get(route('admin.team.index'))->assertForbidden();
    $this->actingAs($client)->get(route('admin.team.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.team.index'))->assertOk();
});
