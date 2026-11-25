{{-- Klien memakai layout portal (terang, ramah ponsel); admin dan staf memakai sidebar bawaan. --}}
@if (auth()->user()?->isClient())
    <x-layouts::portal :title="$title ?? null">
        {{ $slot }}
    </x-layouts::portal>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif
