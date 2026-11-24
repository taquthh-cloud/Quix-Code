<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\UserRole;
use App\Support\ActivityLogger;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $whatsapp
 * @property bool $whatsapp_opt_in
 * @property string $locale
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'whatsapp', 'whatsapp_opt_in', 'locale'])]
#[Hidden(['password', 'remember_token'])]


class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable, PasskeyAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'preferred_currency' => Currency::class,
            'whatsapp_opt_in' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_assignments', 'staff_id', 'project_id')
            ->using(ProjectAssignment::class)
            ->withPivot('task_role')
            ->withTimestamps();
    }

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return null;
        }

        return DB::transaction(function (): ?bool {
            if ($this->is_active) {
                $this->deactivate();
            }

            return parent::delete();
        });
    }

    public function deactivate(?User $actor = null): void
    {
        DB::transaction(function () use ($actor): void {
            $user = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if (! $user->is_active) {
                return;
            }

            $user->is_active = false;
            $user->save();

            if ($user->isStaff()) {
                $projectIds = $user->assignedProjects()->pluck('projects.id');
                $user->assignedProjects()->detach();
                ProjectMilestone::query()
                    ->whereIn('project_id', $projectIds)
                    ->where('assigned_to', $user->id)
                    ->update(['assigned_to' => null]);
            }

            ActivityLogger::log(ActivityAction::UserDeactivated, $user, [], $actor);
            $this->setRawAttributes($user->getAttributes(), true);
        });
    }

    public function reactivate(?User $actor = null): void
    {
        DB::transaction(function () use ($actor): void {
            $user = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($user->is_active) {
                return;
            }

            $user->is_active = true;
            $user->save();
            ActivityLogger::log(ActivityAction::UserReactivated, $user, [], $actor);
            $this->setRawAttributes($user->getAttributes(), true);
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
