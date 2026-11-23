<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Project extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /** Kolom harga yang tidak boleh dimuat untuk staf. */
    public const PRICE_COLUMNS = ['budget_reference', 'final_price'];

    /** @var list<string>|null */
    protected static ?array $columnCache = null;

    /** status & progress_percent sengaja tidak fillable: diubah lewat method/aksi terotorisasi. */
    protected $fillable = [
        'client_id', 'title', 'description', 'github_repo', 'webhook_secret',
        'currency', 'budget_reference', 'final_price',
    ];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'currency' => Currency::class,
            'progress_percent' => 'integer',
            'webhook_secret' => 'encrypted',
            'budget_reference' => 'integer',
            'final_price' => 'integer',
            'warranty_ends_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ---- Relasi ----

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_assignments', 'project_id', 'staff_id')
            ->using(ProjectAssignment::class)
            ->withPivot('task_role')
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderByDesc('version');
    }

    public function latestQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->latestOfMany('version');
    }

    public function orderChats(): HasMany
    {
        return $this->hasMany(OrderChat::class)->orderBy('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(RevisionTicket::class);
    }

    public function commits(): HasMany
    {
        return $this->hasMany(ProjectGitCommit::class)->orderByDesc('pushed_at');
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function handover(): HasOne
    {
        return $this->hasOne(ProjectHandover::class);
    }

    public function caseStudy(): HasOne
    {
        return $this->hasOne(CaseStudy::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    // ---- Scope akses (dasar untuk Policy & query Livewire) ----

    /** Admin: semua; staf: yang ditugaskan; klien: miliknya. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Staff => $query->whereIn(
                $query->qualifyColumn('id'),
                ProjectAssignment::query()->select('project_id')->where('staff_id', $user->id)
            )->withoutPrices(),
            UserRole::Client => $query->where($query->qualifyColumn('client_id'), $user->id),
        };
    }

    /**
     * Pilih semua kolom KECUALI harga, supaya harga tidak pernah dimuat dari database.
     * Atribut harga pada model hasilnya bernilai null (bukan error).
     */
    public function scopeWithoutPrices(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        static::$columnCache ??= Schema::getColumnListing($table);

        $columns = array_values(array_diff(static::$columnCache, self::PRICE_COLUMNS));

        return $query->select(array_map(fn (string $c) => "{$table}.{$c}", $columns));
    }

    /** Subquery id proyek yang boleh dilihat user (untuk membatasi tabel turunan). */
    public static function visibleIds(User $user): Builder
    {
        return static::query()->visibleTo($user)->select('projects.id');
    }

    // ---- Status & uang ----

    /** Pindah status dengan validasi urutan; lempar InvalidArgumentException bila lompat. */
    public function transitionTo(ProjectStatus $to): static
    {
        $this->status = $this->status->transitionTo($to);
        $this->save();

        return $this;
    }

    /** Mata uang dikunci begitu harga disepakati (status deal ke atas), Bagian 7 blueprint. */
    public function isCurrencyLocked(): bool
    {
        return ! in_array($this->status, [ProjectStatus::Lead, ProjectStatus::Quoted, ProjectStatus::Negotiating], true);
    }

    /** Tandai selesai semua milestone yang ambang progresnya sudah tercapai. Mengembalikan jumlah yang diubah. */
    public function completeReachedMilestones(): int
    {
        return ProjectMilestone::query()
            ->where('project_id', $this->getKey())
            ->where('status', '!=', MilestoneStatus::Done->value)
            ->where('target_progress', '<=', (int) $this->progress_percent)
            ->update(['status' => MilestoneStatus::Done->value, 'completed_at' => now()]);
    }

    /** Set progres manual (0-100, dibatasi) lalu selesaikan milestone yang ambangnya tercapai. */
    public function setProgress(int $percent): static
    {
        $this->progress_percent = max(0, min(100, $percent));
        $this->save();
        $this->completeReachedMilestones();

        return $this;
    }

    public function budgetReferenceMoney(): ?Money
    {
        return $this->budget_reference === null ? null : Money::of($this->budget_reference, $this->currency);
    }

    public function finalPriceMoney(): ?Money
    {
        return $this->final_price === null ? null : Money::of($this->final_price, $this->currency);
    }
}
