<?php

namespace App\Livewire\Admin\Team;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts::admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    public string $name = '';

    public string $email = '';

    public string $role = 'client';

    /** @var array<int, string> */
    public array $previewReasons = [];

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function createUser(): void
    {
        $this->authorize('admin');
        $this->email = strtolower(trim($this->email));
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([UserRole::Staff->value, UserRole::Client->value])],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => Str::random(64),
            ]);
            $user->forceFill([
                'role' => UserRole::from($data['role']),
                'is_active' => true,
            ])->save();

            ActivityLogger::log(ActivityAction::UserCreated, $user, ['role' => $user->role->value]);

            return $user;
        });

        $this->reset('name', 'email', 'role');
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (Throwable $exception) {
            report($exception);
            $status = Password::RESET_THROTTLED;
        }

        Flux::toast(
            variant: $status === Password::RESET_LINK_SENT ? 'success' : 'warning',
            text: __($status === Password::RESET_LINK_SENT
                ? 'admin.team.invite_sent'
                : 'admin.team.invite_not_sent'),
        );
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->authorize('admin');
        $data = validator(['role' => $role], [
            'role' => ['required', Rule::in([UserRole::Staff->value, UserRole::Client->value])],
        ])->validate();
        $user = User::query()
            ->whereIn('role', [UserRole::Staff->value, UserRole::Client->value])
            ->findOrFail($userId);
        $targetRole = UserRole::from($data['role']);
        if ($user->role === $targetRole) {
            return;
        }

        $hasOwnedProjects = $user->isClient() && $user->projects()->exists();
        $hasAssignedProjects = $user->isStaff() && $user->assignedProjects()->exists();
        if ($hasOwnedProjects || $hasAssignedProjects) {
            $this->addError("roleChanges.{$userId}", __('admin.team.role_conflict'));

            return;
        }

        $oldRole = $user->role;
        $user->forceFill(['role' => $targetRole])->save();
        ActivityLogger::changed(ActivityAction::UserRoleChanged, $user, $oldRole, $targetRole);
        Flux::toast(variant: 'success', text: __('admin.team.role_changed'));
    }

    public function resetTwoFactor(int $userId): void
    {
        $this->authorize('admin');
        $user = User::query()
            ->whereIn('role', [UserRole::Staff->value, UserRole::Client->value])
            ->findOrFail($userId);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        ActivityLogger::log(ActivityAction::UserTwoFactorReset, $user);
        Flux::toast(variant: 'success', text: __('admin.team.two_factor_reset'));
    }

    public function toggleProjectAvailability(): void
    {
        $user = auth()->user();
        abort_unless($user?->isAdmin(), 403);

        $wasAvailable = $user->is_available_for_projects;
        $user->forceFill(['is_available_for_projects' => ! $wasAvailable])->save();
        ActivityLogger::changed(
            ActivityAction::AdminProjectAvailabilityChanged,
            $user,
            $wasAvailable,
            ! $wasAvailable,
            $user,
        );

        Flux::toast(variant: 'success', text: __('admin.team.project_availability_changed'));
    }

    public function toggleActive(int $userId): void
    {
        $this->authorize('admin');
        $user = User::query()
            ->whereIn('role', [UserRole::Staff->value, UserRole::Client->value])
            ->findOrFail($userId);

        if ($user->is_active) {
            $user->deactivate(auth()->user());
        } else {
            $user->reactivate(auth()->user());
        }

        Flux::toast(variant: 'success', text: __($user->is_active
            ? 'admin.team.activated'
            : 'admin.team.deactivated'));
    }

    public function previewClient(int $userId): void
    {
        $this->authorize('admin');
        $client = User::query()
            ->where('role', UserRole::Client->value)
            ->where('is_active', true)
            ->findOrFail($userId);
        $field = "previewReasons.{$userId}";
        $this->previewReasons[$userId] = trim((string) ($this->previewReasons[$userId] ?? ''));
        $this->validate([$field => ['required', 'string', 'min:8', 'max:500']]);
        $reason = $this->previewReasons[$userId];

        session()->put('admin.client_preview', [
            'user_id' => $client->id,
            'reason' => $reason,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
        ActivityLogger::log(ActivityAction::ClientPreviewStarted, $client, ['reason' => $reason]);
        $this->redirectRoute('admin.team.preview', ['client' => $client->id], navigate: true);
    }

    public function resendInvite(int $userId): void
    {
        $this->authorize('admin');
        $user = User::query()
            ->whereIn('role', [UserRole::Staff->value, UserRole::Client->value])
            ->where('is_active', true)
            ->findOrFail($userId);

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (Throwable $exception) {
            report($exception);
            $status = Password::RESET_THROTTLED;
        }

        Flux::toast(
            variant: $status === Password::RESET_LINK_SENT ? 'success' : 'warning',
            text: __($status === Password::RESET_LINK_SENT
                ? 'admin.team.invite_sent'
                : 'admin.team.invite_not_sent'),
        );
    }

    public function render(): View
    {
        $this->authorize('admin');
        $term = trim($this->search);

        $users = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Staff->value, UserRole::Client->value])
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(fn ($search) => $search
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->orderBy('role')->orderBy('name')->paginate(15);

        return view('livewire.admin.team.index', [
            'users' => $users,
            'roles' => [UserRole::Client, UserRole::Staff],
            'filterRoles' => UserRole::cases(),
        ])->title(__('admin.team.title'));
    }
}
