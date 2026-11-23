@props(['title' => null])

{{-- Bawaan gelap (slate-950); pengguna boleh memilih terang lewat Settings > Appearance. --}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-800 dark:bg-midnight dark:text-slate-200">
        @php($navItems = \App\Support\Navigation::sidebarFor(auth()->user()))

        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <flux:sidebar.header>
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-md bg-growth font-heading text-lg font-bold text-white">Q</span>
                    <span class="font-heading text-base font-semibold text-slate-900 dark:text-slate-50">{{ config('app.name') }}</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="auth()->user()->isAdmin() ? __('admin.nav.group_admin') : __('admin.nav.group_work')" class="grid">
                    @foreach ($navItems as $item)
                        <flux:sidebar.item :icon="$item['icon']" :href="route($item['route'])" :current="request()->routeIs($item['match'])" wire:navigate>
                            {{ __($item['label']) }}
                        </flux:sidebar.item>
                    @endforeach
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-locale-switcher class="mx-2 mb-2 self-start" />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <x-locale-switcher />
            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
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
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
