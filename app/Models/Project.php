<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Support\ActivityLogger;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class Project extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /** Kolom harga yang tidak boleh dimuat untuk staf. */
    public const PRICE_COLUMNS = ['budget_reference', 'final_price'];

    /** @var list<string>|null */
    protected static ?array $columnCache = null;

    /** status & progress_percent sengaja tidak fillable: diubah lewat method/aksi terotorisasi. */
    protected $fillable = [
        'client_id', 'title', 'description', 'github_repo', 'webhook_secret', 'lead_source',
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
            'utm' => 'array',
            'handover_at' => 'datetime',
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

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class)->orderByDesc('created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class)->orderByDesc('created_at');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
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

    /** Pindah status dengan lock, prasyarat bisnis, dan riwayat append-only. */
    public function transitionTo(ProjectStatus $to, ?User $actor = null, ?string $reason = null): static
    {
        DB::transaction(function () use ($to, $actor, $reason): void {
            $project = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $from = $project->status;

            if ($from === ProjectStatus::OnHold) {
                if ($project->on_hold_from !== $to->value) {
                    throw new InvalidArgumentException('Proyek hanya dapat kembali ke status sebelum dijeda.');
                }
            } else {
                $from->transitionTo($to);
            }

            $this->assertTransitionPreconditions($project, $from, $to, $reason);

            if ($to === ProjectStatus::OnHold) {
                $project->on_hold_from = $from->value;
                $project->on_hold_reason = trim((string) $reason);
            } elseif ($from === ProjectStatus::OnHold) {
                $project->on_hold_from = null;
            }

            if ($to === ProjectStatus::Cancelled) {
                $project->cancelled_reason = trim((string) $reason);
            }

            $project->status = $to;
            $project->save();

            ProjectStatusHistory::query()->create([
                'project_id' => $project->id,
                'from' => $from->value,
                'to' => $to->value,
                'actor_id' => $actor?->id ?? auth()->id(),
                'reason' => $reason,
            ]);

            $this->setRawAttributes($project->getAttributes(), true);
        });

        return $this;
    }

    private function assertTransitionPreconditions(
        self $project,
        ProjectStatus $from,
        ProjectStatus $to,
        ?string $reason,
    ): void {
        if (in_array($to, [ProjectStatus::OnHold, ProjectStatus::Cancelled], true) && blank($reason)) {
            throw new InvalidArgumentException('Alasan wajib diisi untuk menjeda atau membatalkan proyek.');
        }

        $allowed = match ([$from, $to]) {
            [ProjectStatus::Lead, ProjectStatus::Quoted] => $project->latestQuotation()
                ->where('status', QuotationStatus::Sent->value)
                ->whereHas('items')
                ->exists(),
            [ProjectStatus::Quoted, ProjectStatus::Negotiating] => $project->latestQuotation()
                ->where('status', QuotationStatus::Sent->value)
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()))
                ->exists(),
            [ProjectStatus::Quoted, ProjectStatus::Deal],
            [ProjectStatus::Negotiating, ProjectStatus::Deal] => $project->latestQuotation()
                ->where('status', QuotationStatus::Accepted->value)
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()))
                ->exists(),
            [ProjectStatus::Deal, ProjectStatus::AwaitingDp] => $project->invoices()
                ->where('type', InvoiceType::Dp->value)
                ->whereNotNull('issued_at')
                ->whereIn('status', [
                    InvoiceStatus::Sent->value,
                    InvoiceStatus::Unpaid->value,
                    InvoiceStatus::AwaitingVerification->value,
                    InvoiceStatus::Paid->value,
                ])
                ->exists(),
            [ProjectStatus::AwaitingDp, ProjectStatus::Active] => $project->invoices()
                ->where('type', InvoiceType::Dp->value)
                ->where('status', InvoiceStatus::Paid->value)
                ->whereColumn('paid_amount', '>=', 'amount')
                ->exists(),
            [ProjectStatus::Active, ProjectStatus::AwaitingFinal] => (int) $project->progress_percent === 100
                && $project->milestones()->exists()
                && ! $project->milestones()->where('status', '!=', MilestoneStatus::Done->value)->exists(),
            [ProjectStatus::AwaitingFinal, ProjectStatus::Handover] => $project->invoices()
                ->where('type', InvoiceType::Final->value)
                ->where('status', InvoiceStatus::Paid->value)
                ->whereColumn('paid_amount', '>=', 'amount')
                ->exists(),
            default => true,
        };

        if (! $allowed) {
            throw new InvalidArgumentException('Prasyarat transisi status proyek belum terpenuhi.');
        }
    }

    /** Mata uang dikunci begitu harga disepakati (status deal ke atas), Bagian 7 blueprint. */
    public function isCurrencyLocked(): bool
    {
        return ! in_array($this->status, [ProjectStatus::Lead, ProjectStatus::Quoted, ProjectStatus::Negotiating], true);
    }

    /** Tandai selesai semua milestone yang ambang progresnya sudah tercapai dan catat perubahan. */
    public function completeReachedMilestones(): int
    {
        return DB::transaction(function (): int {
            $milestones = ProjectMilestone::query()
                ->where('project_id', $this->getKey())
                ->where('status', '!=', MilestoneStatus::Done->value)
                ->where('target_progress', '<=', (int) $this->progress_percent)
                ->lockForUpdate()
                ->get();

            foreach ($milestones as $milestone) {
                $from = $milestone->status;
                $milestone->forceFill([
                    'status' => MilestoneStatus::Done,
                    'completed_at' => now(),
                ])->save();
                ActivityLogger::changed(ActivityAction::MilestoneStatusChanged, $milestone, $from, MilestoneStatus::Done);
            }

            return $milestones->count();
        });
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
