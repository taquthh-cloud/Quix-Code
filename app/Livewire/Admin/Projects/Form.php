<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Form buat/ubah proyek. Hanya admin (ProjectPolicy::create/update), dicek ulang di setiap aksi. */
#[Layout('layouts::admin')]
class Form extends Component
{
    /** Terkunci: tidak bisa diganti dari browser. Null = proyek baru. */
    #[Locked]
    public ?string $projectId = null;

    public string $client_id = '';

    public bool $newClient = false;

    public string $client_name = '';

    public string $client_email = '';

    public string $client_whatsapp = '';

    public string $lead_source = 'manual';

    public string $title = '';

    public string $description = '';

    public string $currency = 'IDR';

    public string $budget_reference = '';

    public string $final_price = '';

    public string $github_repo = '';

    public function mount(?string $project = null): void
    {
        if ($project === null) {
            $this->authorize('create', Project::class);

            return;
        }

        // visibleTo: staf yang tidak ditugaskan mendapat 404, bukan petunjuk bahwa proyek itu ada.
        $model = Project::query()->visibleTo(auth()->user())->findOrFail($project);
        $this->authorize('update', $model);

        $this->projectId = $model->id;
        $this->lead_source = $model->lead_source ?: 'manual';
        $this->client_id = (string) $model->client_id;
        $this->title = $model->title;
        $this->description = (string) $model->description;
        $this->currency = $model->currency->value;
        $this->budget_reference = $model->budgetReferenceMoney()?->toMajorString() ?? '';
        $this->final_price = $model->finalPriceMoney()?->toMajorString() ?? '';
        $this->github_repo = (string) $model->github_repo;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $amount = ['nullable', 'regex:/^\d{1,13}(\.\d{1,2})?$/'];

        return [
            'client_id' => $this->newClient
                ? ['nullable']
                : ['required', Rule::exists('users', 'id')->where('role', UserRole::Client->value)],
            'client_name' => $this->newClient ? ['required', 'string', 'max:255'] : ['nullable'],
            'client_email' => $this->newClient
                ? ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')]
                : ['nullable'],
            'client_whatsapp' => $this->newClient && trim($this->client_whatsapp) !== ''
                ? ['string', 'max:20', Rule::unique('users', 'whatsapp')->where('role', UserRole::Client->value)]
                : ['nullable', 'string', 'max:20'],
            'lead_source' => $this->projectId === null
                ? ['required', Rule::in(['manual', 'referral', 'instagram', 'website', 'whatsapp', 'other'])]
                : ['nullable', Rule::in(['manual', 'referral', 'instagram', 'website', 'whatsapp', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'budget_reference' => $amount,
            'final_price' => $amount,
            'github_repo' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'budget_reference.regex' => __('projects.errors.invalid_amount'),
            'final_price.regex' => __('projects.errors.invalid_amount'),
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect(['client_id', 'client_name', 'client_email', 'client_whatsapp', 'lead_source', 'title', 'description',
            'currency', 'budget_reference', 'final_price', 'github_repo'])
            ->mapWithKeys(fn (string $f) => [$f => __("projects.fields.{$f}")])
            ->all();
    }

    public function save(): void
    {
        $project = $this->projectId ? Project::query()->findOrFail($this->projectId) : null;
        $project ? $this->authorize('update', $project) : $this->authorize('create', Project::class);

        $this->github_repo = trim($this->github_repo);
        $this->client_email = Str::lower(trim($this->client_email));
        $this->client_whatsapp = trim($this->client_whatsapp);
        $this->validate();

        // Mata uang proyek yang sudah deal tidak boleh diubah, apa pun yang dikirim browser.
        $currency = $project?->isCurrencyLocked() ? $project->currency : Currency::from($this->currency);

        try {
            $budget = $this->toMinor($this->budget_reference, $currency);
            $final = $this->toMinor($this->final_price, $currency);
        } catch (InvalidArgumentException) {
            $this->addError('budget_reference', __('projects.errors.invalid_amount'));

            return;
        }

        $attributes = [
            'title' => trim($this->title),
            'description' => trim($this->description) ?: null,
            'github_repo' => $this->github_repo ?: null,
            'currency' => $currency,
            'budget_reference' => $budget,
            'final_price' => $final,
        ];

        $saved = DB::transaction(function () use ($project, $attributes, $currency, $budget, $final) {
            if ($project === null) {
                $attributes['client_id'] = $this->resolveClientId();
                $attributes['lead_source'] = $this->lead_source;
                $project = Project::create($attributes);
                ActivityLogger::log(ActivityAction::ProjectCreated, $project, ['title' => $project->title]);

                return $project;
            }

            $project->fill($attributes);

            if ($project->isDirty(['budget_reference', 'final_price', 'currency'])) {
                ActivityLogger::log(ActivityAction::PriceChanged, $project, [
                    'old' => [
                        'currency' => $project->getOriginal('currency')?->value,
                        'budget_reference' => $project->getOriginal('budget_reference'),
                        'final_price' => $project->getOriginal('final_price'),
                    ],
                    'new' => [
                        'currency' => $currency->value,
                        'budget_reference' => $budget,
                        'final_price' => $final,
                    ],
                ]);
            }

            $project->save();

            return $project;
        });

        session()->flash('status', __($project ? 'projects.toast.updated' : 'projects.toast.created'));

        $this->redirectRoute('admin.projects.show', ['project' => $saved->id], navigate: true);
    }

    private function toMinor(string $value, Currency $currency): ?int
    {
        $value = trim($value);

        return $value === '' ? null : Money::fromMajor($value, $currency)->amount;
    }

    private function resolveClientId(): int
    {
        if (! $this->newClient) {
            return (int) $this->client_id;
        }

        // Akun lead tanpa password yang bisa ditebak; tautan atur-password menyusul (Tahap 6).
        $client = new User([
            'name' => trim($this->client_name),
            'email' => $this->client_email,
            'whatsapp' => trim($this->client_whatsapp) ?: null,
            'locale' => app()->getLocale(),
            'password' => Str::random(40),
        ]);
        $client->role = UserRole::Client;
        $client->save();

        return $client->id;
    }

    public function render(): View
    {
        $project = $this->projectId ? Project::query()->find($this->projectId) : null;

        return view('livewire.admin.projects.form', [
            'clients' => User::query()->where('role', UserRole::Client->value)->orderBy('name')->get(['id', 'name', 'email']),
            'currencies' => Currency::cases(),
            'editing' => $project !== null,
            'currencyLocked' => $project?->isCurrencyLocked() ?? false,
        ])->title($project ? __('projects.form.edit_title') : __('projects.form.create_title'));
    }
}
