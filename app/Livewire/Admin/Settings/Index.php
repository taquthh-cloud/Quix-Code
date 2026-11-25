<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class Index extends Component
{
    public string $companyName = '';

    public string $companyAddress = '';

    public string $companyNpwp = '';

    public string $bankName = '';

    public string $bankAccountName = '';

    public string $bankAccountNumber = '';

    public string $taxRatePercent = '0';

    public string $defaultRateIdr = '';

    public string $defaultRateUsd = '';

    public string $invoiceFooterId = '';

    public string $invoiceFooterEn = '';

    public string $termsTemplateId = '';

    public string $termsTemplateEn = '';

    public function mount(): void
    {
        $this->authorize('admin');
        $this->companyName = (string) $this->settingValue('company.name', '');
        $this->companyAddress = (string) $this->settingValue('company.address', '');
        $this->companyNpwp = (string) $this->settingValue('company.npwp', '');
        $this->bankName = (string) $this->settingValue('bank.name', '');
        $this->bankAccountName = (string) $this->settingValue('bank.account_name', '');
        $this->bankAccountNumber = (string) $this->settingValue('bank.account_number', '');
        $this->taxRatePercent = $this->basisPointsToPercent((int) $this->settingValue('tax.rate_bp', 0));
        $this->defaultRateIdr = Money::of((int) $this->settingValue('rate.default_idr_minor', 0), Currency::IDR)->toMajorString();
        $this->defaultRateUsd = Money::of((int) $this->settingValue('rate.default_usd_minor', 0), Currency::USD)->toMajorString();
        $this->invoiceFooterId = (string) $this->settingValue('invoice.footer.id', '');
        $this->invoiceFooterEn = (string) $this->settingValue('invoice.footer.en', '');
        $this->termsTemplateId = (string) $this->settingValue('quotation.terms.id', '');
        $this->termsTemplateEn = (string) $this->settingValue('quotation.terms.en', '');
    }

    public function save(): void
    {
        $this->authorize('admin');
        $data = $this->validate([
            'companyName' => ['nullable', 'string', 'max:180'],
            'companyAddress' => ['nullable', 'string', 'max:1000'],
            'companyNpwp' => ['nullable', 'string', 'max:32'],
            'bankName' => ['nullable', 'string', 'max:120'],
            'bankAccountName' => ['nullable', 'string', 'max:160'],
            'bankAccountNumber' => ['nullable', 'string', 'max:80'],
            'taxRatePercent' => ['required', 'regex:/^\\d{1,3}(?:\\.\\d{1,2})?$/', 'numeric', 'between:0,100'],
            'defaultRateIdr' => ['nullable', 'regex:/^\\d{1,16}$/'],
            'defaultRateUsd' => ['nullable', 'regex:/^\\d{1,16}(?:\\.\\d{1,2})?$/'],
            'invoiceFooterId' => ['nullable', 'string', 'max:5000'],
            'invoiceFooterEn' => ['nullable', 'string', 'max:5000'],
            'termsTemplateId' => ['nullable', 'string', 'max:5000'],
            'termsTemplateEn' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $rateIdr = Money::fromMajor($data['defaultRateIdr'] ?: '0', Currency::IDR)->amount;
            $rateUsd = Money::fromMajor($data['defaultRateUsd'] ?: '0', Currency::USD)->amount;
        } catch (\InvalidArgumentException) {
            $this->addError('defaultRateUsd', __('admin.settings.errors.invalid_rate'));

            return;
        }

        [$whole, $fraction] = array_pad(explode('.', $data['taxRatePercent'], 2), 2, '');
        $taxBasisPoints = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        $values = [
            'company.name' => ['value' => trim($data['companyName'] ?? '')],
            'company.address' => ['value' => trim($data['companyAddress'] ?? '')],
            'company.npwp' => ['value' => trim($data['companyNpwp'] ?? '')],
            'bank.name' => ['value' => trim($data['bankName'] ?? '')],
            'bank.account_name' => ['value' => trim($data['bankAccountName'] ?? '')],
            'bank.account_number' => ['value' => trim($data['bankAccountNumber'] ?? '')],
            'tax.rate_bp' => ['basis_points' => $taxBasisPoints],
            'rate.default_idr_minor' => $rateIdr,
            'rate.default_usd_minor' => $rateUsd,
            'invoice.footer.id' => ['value' => trim($data['invoiceFooterId'] ?? '')],
            'invoice.footer.en' => ['value' => trim($data['invoiceFooterEn'] ?? '')],
            'quotation.terms.id' => ['value' => trim($data['termsTemplateId'] ?? '')],
            'quotation.terms.en' => ['value' => trim($data['termsTemplateEn'] ?? '')],
        ];

        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $setting = Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => auth()->id()],
                );
                ActivityLogger::log(ActivityAction::SettingUpdated, $setting, ['key' => $key]);
            }
        });

        Flux::toast(variant: 'success', text: __('admin.settings.saved'));
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $setting = Setting::query()->where('key', $key)->value('value');
        if ($setting === null) {
            return $default;
        }

        if (is_string($setting)) {
            $setting = json_decode($setting, true);
        }

        if (! is_array($setting)) {
            return $setting;
        }

        return $setting['value'] ?? $setting['basis_points'] ?? $setting['minor_units'] ?? $default;
    }

    private function basisPointsToPercent(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $fraction = $basisPoints % 100;

        return $fraction === 0 ? (string) $whole : $whole.'.'.rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0');
    }

    public function render(): View
    {
        $this->authorize('admin');

        return view('livewire.admin.settings.index')->title(__('admin.settings.title'));
    }
}
