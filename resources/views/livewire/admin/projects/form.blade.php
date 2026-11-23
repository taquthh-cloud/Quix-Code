<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">
            {{ $editing ? __('projects.form.edit_title') : __('projects.form.create_title') }}
        </h1>
    </div>

    <x-card>
        <form wire:submit="save" class="flex flex-col gap-5">
            {{-- Klien --}}
            @unless ($editing)
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('projects.form.client') }}</span>
                        <flux:switch wire:model.live="newClient" :label="__('projects.form.new_client')" align="left" />
                    </div>

                    @if ($newClient)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="client_name" :label="__('projects.form.client_name')" required />
                            <flux:input wire:model="client_email" type="email" :label="__('projects.form.client_email')" required />
                            <flux:input wire:model="client_whatsapp" :label="__('projects.form.client_whatsapp')" class="sm:col-span-2" />
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('projects.form.new_client_hint') }}</p>
                    @else
                        <flux:select wire:model="client_id" :aria-label="__('projects.form.client')">
                            <flux:select.option value="">{{ __('projects.form.select_client') }}</flux:select.option>
                            @foreach ($clients as $client)
                                <flux:select.option :value="(string) $client->id">{{ $client->name }} ({{ $client->email }})</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('client_id') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        @if ($clients->isEmpty())
                            <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('projects.form.no_clients') }}</p>
                        @endif
                    @endif
                </div>
            @endunless

            @unless ($editing)
                <flux:select wire:model="lead_source" :label="__('projects.form.lead_source')">
                    @foreach (['manual', 'referral', 'instagram', 'website', 'whatsapp', 'other'] as $source)
                        <flux:select.option :value="$source">{{ __('projects.lead_sources.'.$source) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="lead_source" />
            @endunless

            <flux:input wire:model="title" :label="__('projects.form.title')" required />
            <flux:textarea wire:model="description" :label="__('projects.form.description')" rows="4" />

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <flux:select wire:model="currency" :label="__('projects.form.currency')" :disabled="$currencyLocked">
                        @foreach ($currencies as $c)
                            <flux:select.option :value="$c->value">{{ $c->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($currencyLocked)
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ __('projects.form.currency_locked') }}</p>
                    @endif
                </div>
                <flux:input wire:model="budget_reference" inputmode="decimal" :label="__('projects.form.budget_reference')" />
                <flux:input wire:model="final_price" inputmode="decimal" :label="__('projects.form.final_price')" />
            </div>
            <p class="-mt-3 text-xs text-slate-600 dark:text-slate-400">{{ __('projects.form.amount_hint') }}</p>

            <div>
                <flux:input wire:model="github_repo" :label="__('projects.form.github_repo')" placeholder="owner/repo" />
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ __('projects.form.github_hint') }}</p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:button variant="ghost" :href="$editing ? route('admin.projects.show', $this->projectId) : route('admin.projects.index')" wire:navigate>
                    {{ __('ui.cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">{{ __('ui.save') }}</flux:button>
            </div>
        </form>
    </x-card>
</div>
