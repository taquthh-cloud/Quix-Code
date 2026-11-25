@props(['title' => null])

{{--
    T4-01. Layout portal klien: terang (sidebar putih + konten slate-50), dirancang dari ponsel dulu.
    Ponsel: bilah atas ringkas + navigasi bawah (target sentuh >= 44px). Desktop: sidebar kiri.
    Pengguna tetap boleh memilih gelap lewat Pengaturan > Tampilan.
--}}
@php
    $user = auth()->user();
    $routeProject = request()->route('project');
    $projectId = $routeProject instanceof \Illuminate\Database\Eloquent\Model
        ? (string) $routeProject->getKey()
        : (is_scalar($routeProject) ? (string) $routeProject : null);
    $globalItems = \App\Support\PortalNavigation::global();
    $projectItems = $projectId !== null ? \App\Support\PortalNavigation::forProject($projectId) : [];
    $bottomItems = \App\Support\PortalNavigation::bottom($projectId);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['appearance' => 'light'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-200" data-portal-layout>
        <a href="#portal-main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:text-slate-900">{{ __('portal.nav.skip') }}</a>

        <div class="lg:grid lg:min-h-screen lg:grid-cols-[16rem_minmax(0,1fr)]">
            {{-- Sidebar desktop --}}
            <aside class="sticky top-0 hidden h-screen flex-col gap-6 border-e border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 lg:flex" aria-label="{{ __('portal.nav.label') }}">
                <a href="{{ route('portal.overview') }}" wire:navigate class="flex items-center gap-2 px-2 py-1">
                    <span class="flex size-8 items-center justify-center rounded-md bg-growth font-heading text-lg font-bold text-white">Q</span>
                    <span class="font-heading text-base font-semibold text-slate-900 dark:text-slate-50">{{ config('app.name') }}</span>
                </a>

                <nav class="flex flex-col gap-1" data-portal-nav="sidebar">
                    @foreach ([...$globalItems, ...$projectItems] as $item)
                        @php($current = request()->routeIs($item['match']))
                        <a href="{{ route($item['route'], $item['params']) }}" wire:navigate data-nav="{{ $item['key'] }}"
                           @if ($current) aria-current="page" @endif
                           @class([
                               'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium',
                               'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $current,
                               'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => ! $current,
                           ])>
                            <flux:icon :name="$item['icon']" class="size-5 shrink-0" />
                            <span class="truncate">{{ __($item['label']) }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="mt-auto flex flex-col gap-3">
                    <x-locale-switcher class="self-start" />
                    <flux:dropdown position="top" align="start">
                        <flux:profile :name="$user->name" :initials="$user->initials()" icon-trailing="chevrons-up-down" class="w-full" data-test="sidebar-menu-button" />
                        <flux:menu>
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                            <flux:menu.separator />
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </aside>

            <div class="flex min-w-0 flex-col">
                {{-- Bilah atas ponsel --}}
                <header class="sticky top-0 z-30 flex min-h-14 items-center justify-between gap-2 border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900 lg:hidden">
                    <a href="{{ route('portal.overview') }}" wire:navigate class="flex min-h-11 items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-md bg-growth font-heading text-lg font-bold text-white">Q</span>
                        <span class="font-heading text-base font-semibold text-slate-900 dark:text-slate-50">{{ config('app.name') }}</span>
                    </a>
                    <div class="flex items-center gap-2">
                        <x-locale-switcher />
                        <flux:dropdown position="bottom" align="end">
                            <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" class="min-h-11" aria-label="{{ $user->name }}" />
                            <flux:menu>
                                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                                        {{ __('Log out') }}
                                    </flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </header>

                <main id="portal-main" class="mx-auto w-full min-w-0 max-w-5xl flex-1 px-4 py-6 pb-28 lg:px-8 lg:py-8 lg:pb-10">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Navigasi bawah ponsel --}}
        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-slate-800 dark:bg-slate-900 lg:hidden"
             aria-label="{{ __('portal.nav.label') }}" data-portal-nav="bottom">
            <ul class="mx-auto flex max-w-xl">
                @foreach ($bottomItems as $item)
                    @php($current = request()->routeIs($item['match']))
                    <li class="min-w-0 flex-1">
                        <a href="{{ route($item['route'], $item['params']) }}" wire:navigate data-nav="{{ $item['key'] }}"
                           @if ($current) aria-current="page" @endif
                           @class([
                               'flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 text-[0.7rem] font-medium leading-tight',
                               'text-emerald-700 dark:text-emerald-300' => $current,
                               'text-slate-600 dark:text-slate-400' => ! $current,
                           ])>
                            <flux:icon :name="$item['icon']" class="size-6 shrink-0" />
                            <span class="max-w-full truncate">{{ __($item['label']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
