<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('admin.settings.title') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.settings.subtitle') }}</p>
        </div>
        <flux:button :href="route('admin.team.index')" wire:navigate>{{ __('admin.settings.back_to_team') }}</flux:button>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <x-card :title="__('admin.settings.company')">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="companyName" :label="__('admin.settings.company_name')" autocomplete="organization" />
                <flux:input wire:model="companyNpwp" :label="__('admin.settings.npwp')" />
                <flux:textarea wire:model="companyAddress" :label="__('admin.settings.company_address')" rows="3" class="md:col-span-2" />
            </div>
        </x-card>

        <x-card :title="__('admin.settings.bank')">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="bankName" :label="__('admin.settings.bank_name')" />
                <flux:input wire:model="bankAccountName" :label="__('admin.settings.account_name')" />
                <flux:input wire:model="bankAccountNumber" :label="__('admin.settings.account_number')" inputmode="numeric" autocomplete="off" />
            </div>
        </x-card>

        <x-card :title="__('admin.settings.defaults')">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="taxRatePercent" type="number" min="0" max="100" step="0.01" :label="__('admin.settings.tax_rate')" />
                <flux:input wire:model="defaultRateIdr" type="number" min="0" step="1" :label="__('admin.settings.default_rate_idr')" />
                <flux:input wire:model="defaultRateUsd" type="number" min="0" step="0.01" :label="__('admin.settings.default_rate_usd')" />
                <flux:error name="taxRatePercent" />
                <flux:error name="defaultRateIdr" />
                <flux:error name="defaultRateUsd" />
            </div>
        </x-card>

        <x-card :title="__('admin.settings.templates')">
            <div class="grid gap-5 xl:grid-cols-2">
                <flux:textarea wire:model="invoiceFooterId" :label="__('admin.settings.invoice_footer_id')" rows="4" />
                <flux:textarea wire:model="invoiceFooterEn" :label="__('admin.settings.invoice_footer_en')" rows="4" />
                <flux:textarea wire:model="termsTemplateId" :label="__('admin.settings.terms_id')" rows="6" />
                <flux:textarea wire:model="termsTemplateEn" :label="__('admin.settings.terms_en')" rows="6" />
            </div>
        </x-card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('admin.settings.save') }}</flux:button>
        </div>
    </form>
</div>
