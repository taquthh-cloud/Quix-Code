<?php

namespace App\Livewire\Admin\Quotations;

use App\Enums\Currency;
use App\Enums\QuotationStatus;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\QuotationService;
use App\Support\Money;
use App\Support\QuotationCalculator;
use DomainException;
use Flux\Flux;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Quotation Builder (Bagian 4 & 5): itemisasi (jam × rate) + operasional + margin, per versi.
 * Hanya admin. Hanya draf yang bisa diubah; versi terkirim dibaca saja dan dibuatkan versi baru.
 *
 * Nilai uang dimasukkan dalam satuan utama (string) dan diubah ke satuan terkecil tanpa float.
 */
#[Layout('layouts::admin')]
class Builder extends Component
{
    public const MAX_ITEMS = 50;

    #[Locked]
    public string $projectId;

    /** Null = versi baru yang belum disimpan. Terkunci agar tidak bisa dipalsukan dari browser. */
    #[Locked]
    public ?string $quotationId = null;

    /** @var list<array{title: string, description: string, hours: string, rate: string}> */
    public array $items = [];

    public string $operational_cost = '0';

    public string $margin_percent = '20';

    public string $dp_percent = '50';

    public string $valid_until = '';

    public function mount(string $project, ?string $quotation = null): void
    {
        $model = Project::query()->visibleTo(auth()->user())->findOrFail($project);
        $this->projectId = $model->id;

        if ($quotation === null) {
            $this->authorize('create', [Quotation::class, $model]);
            abort_if($model->isCurrencyLocked(), 403);

            // Satu draf aktif per proyek: lanjutkan yang ada.
            $draft = $model->quotations()->where('status', QuotationStatus::Draft->value)->first();

            if ($draft !== null) {
                $this->redirectRoute('admin.quotations.edit', ['project' => $model->id, 'quotation' => $draft->id], navigate: true);

                return;
            }

            $this->items = [$this->blankItem()];
            $this->valid_until = now()->addDays(14)->toDateString();

            return;
        }

        $record = Quotation::query()->where('project_id', $model->id)->with('items')->findOrFail($quotation);
        $this->authorize('view', $record);

        $this->quotationId = $record->id;
        $this->items = $record->items->map(fn ($item) => [
            'title' => $item->title,
            'description' => (string) $item->description,
            'hours' => rtrim(rtrim((string) $item->hours, '0'), '.') ?: '0',
            'rate' => Money::of($item->rate, $record->currency)->toMajorString(),
        ])->all();
        $this->operational_cost = Money::of($record->operational_cost, $record->currency)->toMajorString();
        $this->margin_percent = rtrim(rtrim((string) $record->margin_percent, '0'), '.') ?: '0';
        $this->dp_percent = (string) $record->dp_percent;
        $this->valid_until = $record->valid_until?->format('Y-m-d') ?? '';
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->visibleTo(auth()->user())->findOrFail($this->projectId);
    }

    #[Computed]
    public function quotation(): ?Quotation
    {
        return $this->quotationId
            ? Quotation::query()->where('project_id', $this->projectId)->findOrFail($this->quotationId)
            : null;
    }

    private function isEditable(): bool
    {
        return $this->quotation === null || $this->quotation->status === QuotationStatus::Draft;
    }

    /** @return array<string, string> */
    private function blankItem(): array
    {
        return ['title' => '', 'description' => '', 'hours' => '', 'rate' => ''];
    }

    // ------------------------------------------------------------------ baris item

    public function addItem(): void
    {
        $this->authorizeEdit();

        if (count($this->items) < self::MAX_ITEMS) {
            $this->items[] = $this->blankItem();
        }
    }

    public function removeItem(int $index): void
    {
        $this->authorizeEdit();

        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function moveItem(int $index, string $direction): void
    {
        $this->authorizeEdit();

        $swap = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($this->items[$index], $this->items[$swap])) {
            return;
        }

        [$this->items[$index], $this->items[$swap]] = [$this->items[$swap], $this->items[$index]];
    }

    // ------------------------------------------------------------------ simpan & kirim

    public function save(): void
    {
        $saved = $this->persist();

        if ($saved === null) {
            return;
        }

        if ($this->quotationId === null) {
            session()->flash('status', __('quotations.toast.saved'));
            $this->redirectRoute('admin.quotations.edit', ['project' => $this->projectId, 'quotation' => $saved->id], navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('quotations.toast.saved'));
    }

    public function saveAndSend(): void
    {
        $saved = $this->persist();

        if ($saved === null) {
            return;
        }

        $this->authorize('send', $saved);

        try {
            app(QuotationService::class)->send($saved, auth()->user());
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return;
        }

        session()->flash('status', __('quotations.toast.sent'));
        $this->redirectRoute('admin.projects.negotiation', ['project' => $this->projectId], navigate: true);
    }

    /** Buat draf versi berikutnya dari versi yang sedang dibuka (hanya saat sudah terkirim/ditolak). */
    public function newVersion(): void
    {
        $source = $this->quotation;
        abort_if($source === null, 404);
        $this->authorize('create', [Quotation::class, $this->project]);

        try {
            $draft = app(QuotationService::class)->newVersionFrom($source);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return;
        }

        $this->redirectRoute('admin.quotations.edit', ['project' => $this->projectId, 'quotation' => $draft->id], navigate: true);
    }

    private function authorizeEdit(): void
    {
        $quotation = $this->quotation;

        $quotation
            ? $this->authorize('update', $quotation)
            : $this->authorize('create', [Quotation::class, $this->project]);
    }

    /** Validasi, ubah ke satuan terkecil, dan simpan lewat service. Null bila ada galat. */
    private function persist(): ?Quotation
    {
        $this->authorizeEdit();

        $project = $this->project;
        $currency = $project->currency;

        $this->margin_percent = trim($this->margin_percent);
        $this->operational_cost = trim($this->operational_cost) === '' ? '0' : trim($this->operational_cost);

        $this->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.hours' => ['bail', 'required', 'regex:/^\d{1,4}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'items.*.rate' => ['bail', 'required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'operational_cost' => ['bail', 'required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'margin_percent' => ['bail', 'required', 'regex:/^\d{1,3}(\.\d{1,2})?$/', 'numeric', 'between:0,100'],
            'dp_percent' => ['required', 'in:'.implode(',', Quotation::DP_OPTIONS)],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'items.*.hours.regex' => __('quotations.errors.invalid_hours'),
            'items.*.rate.regex' => __('quotations.errors.invalid_amount'),
            'operational_cost.regex' => __('quotations.errors.invalid_amount'),
            'margin_percent.regex' => __('quotations.errors.invalid_percent'),
        ], $this->validationAttributes());

        $lines = [];
        $failed = false;

        foreach ($this->items as $i => $item) {
            try {
                $rate = Money::fromMajor($item['rate'], $currency)->amount;
            } catch (InvalidArgumentException) {
                $this->addError("items.{$i}.rate", __('quotations.errors.invalid_amount'));
                $failed = true;

                continue;
            }

            $lines[] = [
                'title' => trim($item['title']),
                'description' => trim($item['description']) ?: null,
                'hours' => $item['hours'],
                'rate' => $rate,
            ];
        }

        try {
            $operational = Money::fromMajor($this->operational_cost, $currency)->amount;
        } catch (InvalidArgumentException) {
            $this->addError('operational_cost', __('quotations.errors.invalid_amount'));
            $failed = true;
            $operational = 0;
        }

        if ($failed) {
            return null;
        }

        try {
            $saved = app(QuotationService::class)->saveDraft($project, $this->quotation, [
                'operational_cost' => $operational,
                'margin_percent' => $this->margin_percent,
                'dp_percent' => (int) $this->dp_percent,
                'valid_until' => $this->valid_until !== '' ? $this->valid_until : null,
            ], $lines);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return null;
        }

        unset($this->quotation);

        return $saved;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'items' => __('quotations.builder.items'),
            'items.*.title' => __('quotations.builder.item_title'),
            'items.*.description' => __('quotations.builder.item_description'),
            'items.*.hours' => __('quotations.builder.hours'),
            'items.*.rate' => __('quotations.builder.rate'),
            'operational_cost' => __('quotations.builder.operational'),
            'margin_percent' => __('quotations.builder.margin'),
            'dp_percent' => __('quotations.builder.dp'),
            'valid_until' => __('quotations.builder.valid_until'),
        ];
    }

    /**
     * Pratinjau total dari isian saat ini. Isian yang belum valid dihitung 0, tanpa galat:
     * validasi sebenarnya dilakukan saat simpan.
     *
     * @return array{amounts: list<int>, subtotal: int, operational: int, margin: int, total: int, dp: int}
     */
    #[Computed]
    public function totals(): array
    {
        $currency = $this->project->currency;

        $lines = array_map(fn (array $item) => [
            'hours' => preg_match('/^\d{1,4}(\.\d{1,2})?$/', (string) ($item['hours'] ?? '')) ? $item['hours'] : '0',
            'rate' => $this->tryMinor((string) ($item['rate'] ?? ''), $currency),
        ], array_values($this->items));

        $margin = preg_match('/^\d{1,3}(\.\d{1,2})?$/', trim($this->margin_percent)) ? min(100, (float) $this->margin_percent) : 0;

        $calc = QuotationCalculator::calculate(
            $currency,
            $lines,
            $this->tryMinor($this->operational_cost, $currency),
            (string) $margin,
        );

        $dp = in_array((int) $this->dp_percent, Quotation::DP_OPTIONS, true) ? (int) $this->dp_percent : 50;
        $calc['dp'] = Money::of($calc['total'], $currency)->percent($dp)->amount;

        return $calc;
    }

    private function tryMinor(string $value, Currency $currency): int
    {
        $value = trim($value);

        if (! preg_match('/^\d{1,12}(\.\d{1,2})?$/', $value)) {
            return 0;
        }

        try {
            return Money::fromMajor($value, $currency)->amount;
        } catch (InvalidArgumentException) {
            return 0;
        }
    }

    public function render(): View
    {
        $project = $this->project;
        $quotation = $this->quotation;

        return view('livewire.admin.quotations.builder', [
            'project' => $project->load('client:id,name'),
            'quotation' => $quotation,
            'editable' => $this->isEditable(),
            'dpOptions' => Quotation::DP_OPTIONS,
            'totals' => $this->totals,
        ])->title(__('quotations.builder.title'));
    }
}
