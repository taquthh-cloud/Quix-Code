@php
    use App\Enums\InvoiceStatus;
    use App\Enums\ProjectStatus;

    $rows = [
        ['Sistem Kasir Toko Demo', ProjectStatus::Active, 65],
        ['Portal Klien Cafe', ProjectStatus::AwaitingDp, 0],
        ['Aplikasi Inventori', ProjectStatus::Handover, 100],
    ];
@endphp

<x-layouts::app :title="__('ui.demo.title')">
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('ui.demo.title') }}</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('ui.demo.subtitle') }}</p>
            </div>
            <x-locale-switcher />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <x-card :title="__('ui.demo.badges')">
                <div class="flex flex-wrap gap-2">
                    @foreach (ProjectStatus::cases() as $status)
                        <x-badge-status :status="$status" />
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (InvoiceStatus::cases() as $status)
                        <x-badge-status :status="$status" />
                    @endforeach
                </div>
            </x-card>

            <x-card :title="__('ui.demo.progress')">
                <div class="flex flex-col gap-4">
                    <x-progress-bar :value="25" size="sm" :label="__('ui.demo.small')" />
                    <x-progress-bar :value="65" :label="__('ui.demo.medium')" />
                    <x-progress-bar :value="100" size="lg" :label="__('ui.demo.large')" />
                </div>
            </x-card>
        </div>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('ui.demo.project') }}</th>
                    <th>{{ __('ui.demo.status') }}</th>
                    <th class="min-w-40">{{ __('ui.progress') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as [$title, $status, $progress])
                <tr>
                    <td class="font-medium">{{ $title }}</td>
                    <td><x-badge-status :status="$status" /></td>
                    <td><x-progress-bar :value="$progress" size="sm" /></td>
                </tr>
            @endforeach
        </x-table>

        <div class="grid gap-4 md:grid-cols-2">
            <x-table :empty="true">
                <x-slot:head>
                    <tr><th>{{ __('ui.demo.project') }}</th></tr>
                </x-slot:head>
            </x-table>

            <x-card :title="__('ui.demo.modal')">
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'demo')"
                        class="rounded-lg bg-growth px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    {{ __('ui.demo.open_modal') }}
                </button>
            </x-card>
        </div>
    </div>

    <x-modal name="demo" :title="__('ui.demo.modal')">
        <p>{{ __('ui.demo.modal_body') }}</p>
        <x-slot:footer>
            <button type="button" x-data x-on:click="$dispatch('close-modal', 'demo')"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                {{ __('ui.close') }}
            </button>
        </x-slot:footer>
    </x-modal>
</x-layouts::app>
