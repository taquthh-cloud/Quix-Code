<x-layouts::app :title="__('portal.title')">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('portal.title') }}</flux:heading>
        <flux:text>{{ __('portal.placeholder', ['name' => auth()->user()->name]) }}</flux:text>
    </div>
</x-layouts::app>
