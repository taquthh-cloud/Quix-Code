<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('admin.team.title') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.team.subtitle') }}</p>
        </div>
        <flux:button icon="cog-6-tooth" :href="route('admin.settings')" wire:navigate>{{ __('admin.settings.title') }}</flux:button>
    </div>

    <x-card :title="__('admin.team.create_title')">
        <form wire:submit="createUser" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <flux:input wire:model="name" :label="__('admin.team.name')" required autocomplete="name" />
            <flux:input wire:model="email" type="email" :label="__('admin.team.email')" required autocomplete="email" />
            <flux:select wire:model="role" :label="__('admin.team.role')">
                @foreach ($roles as $userRole)
                    <flux:select.option :value="$userRole->value">{{ $userRole->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex items-end"><flux:button type="submit" variant="primary" class="w-full">{{ __('admin.team.create') }}</flux:button></div>
            <flux:error name="name" />
            <flux:error name="email" />
            <flux:error name="role" />
        </form>
        <p class="mt-3 text-xs text-slate-600 dark:text-slate-400">{{ __('admin.team.invite_hint') }}</p>
    </x-card>

    <x-card :title="__('admin.team.directory')">
        <div class="mb-4 flex justify-end">
            <flux:button size="sm" variant="ghost" :href="route('admin.exports', ['type' => 'clients', 'q' => $search])">{{ __('admin.search_page.export_clients') }}</flux:button>
        </div>
        <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_14rem]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('admin.team.search')" :aria-label="__('admin.team.search')" />
            <flux:select wire:model.live="roleFilter" :aria-label="__('admin.team.role_filter')">
                <flux:select.option value="">{{ __('admin.team.all_roles') }}</flux:select.option>
                @foreach ($filterRoles as $userRole)
                    <flux:select.option :value="$userRole->value">{{ $userRole->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-600 dark:border-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2">{{ __('admin.team.person') }}</th>
                        <th class="px-3 py-2">{{ __('admin.team.role') }}</th>
                        <th class="px-3 py-2">{{ __('admin.team.state') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('admin.team.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="px-3 py-3">
                                <span class="font-medium text-slate-900 dark:text-slate-50">{{ $user->name }}</span>
                                <span class="block text-xs text-slate-600 dark:text-slate-400">{{ $user->email }}</span>
                            </td>
                            <td class="px-3 py-3">
                                @if ($user->isAdmin())
                                    <span class="inline-flex rounded-full bg-violet-100 px-2 py-1 text-xs font-medium text-violet-800 dark:bg-violet-500/15 dark:text-violet-300">{{ $user->role->label() }}</span>
                                @else
                                    <label class="sr-only" for="role-{{ $user->id }}">{{ __('admin.team.role_for', ['name' => $user->name]) }}</label>
                                    <select id="role-{{ $user->id }}" wire:change="changeRole({{ $user->id }}, $event.target.value)" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900">
                                        @foreach ($roles as $userRole)
                                            <option value="{{ $userRole->value }}" @selected($user->role === $userRole)>{{ $userRole->label() }}</option>
                                        @endforeach
                                    </select>
                                    <flux:error :name="'roleChanges.'.$user->id" />
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-col items-start gap-1.5">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-1 text-xs font-medium',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $user->is_active,
                                        'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => ! $user->is_active,
                                    ])>{{ $user->is_active ? __('admin.team.active') : __('admin.team.inactive') }}</span>
                                    @if ($user->isAdmin())
                                        <span class="text-xs text-slate-600 dark:text-slate-400">
                                            {{ $user->is_available_for_projects ? __('admin.team.available_for_projects') : __('admin.team.unavailable_for_projects') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($user->isAdmin())
                                        @if ((int) auth()->id() === $user->id)
                                            <flux:button size="sm" variant="ghost" wire:click="toggleProjectAvailability">
                                                {{ $user->is_available_for_projects ? __('admin.team.set_unavailable_for_projects') : __('admin.team.set_available_for_projects') }}
                                            </flux:button>
                                        @else
                                            <span class="self-center text-xs text-slate-600 dark:text-slate-400">{{ __('admin.team.admin_control') }}</span>
                                        @endif
                                    @else
                                        @if ($user->is_active)
                                            <flux:button size="sm" variant="ghost" wire:click="resendInvite({{ $user->id }})">{{ __('admin.team.resend_invite') }}</flux:button>
                                            @if ($user->isClient())
                                                <div class="flex flex-wrap items-end justify-end gap-2">
                                                    <flux:input wire:model="previewReasons.{{ $user->id }}" :label="__('admin.team.preview_reason')" />
                                                    <flux:button size="sm" variant="ghost" wire:click="previewClient({{ $user->id }})">{{ __('admin.team.client_preview') }}</flux:button>
                                                </div>
                                                <flux:error :name="'previewReasons.'.$user->id" />
                                            @endif
                                        @endif
                                        <flux:button size="sm" variant="ghost" wire:click="resetTwoFactor({{ $user->id }})" wire:confirm="{{ __('admin.team.confirm_two_factor_reset') }}">{{ __('admin.team.reset_two_factor') }}</flux:button>
                                        <flux:button size="sm" :variant="$user->is_active ? 'danger' : 'primary'" wire:click="toggleActive({{ $user->id }})" wire:confirm="{{ $user->is_active ? __('admin.team.confirm_deactivate') : __('admin.team.confirm_activate') }}">
                                            {{ $user->is_active ? __('admin.team.deactivate') : __('admin.team.activate') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-600 dark:text-slate-400">{{ __('admin.team.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </x-card>
</div>
