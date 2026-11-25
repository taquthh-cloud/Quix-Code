<x-card :title="__('chat.title')" :padded="false">
    <div wire:poll.{{ $pollSeconds }}s.visible x-data x-init="$nextTick(() => { $refs.log.scrollTop = $refs.log.scrollHeight })"
         x-on:chat-sent.window="$nextTick(() => { $refs.log.scrollTop = $refs.log.scrollHeight })">
        <div x-ref="log" class="flex max-h-96 min-h-48 flex-col gap-3 overflow-y-auto p-card" role="log" aria-live="polite">
            @forelse ($messages as $chat)
                @php($mine = (int) $chat->sender_id === (int) auth()->id())

                <div wire:key="chat-{{ $chat->id }}" @class(['flex flex-col gap-1', 'items-end' => $mine, 'items-start' => ! $mine])>
                    <span class="text-xs text-slate-600 dark:text-slate-400">
                        {{ $mine ? __('chat.you') : $chat->sender?->name }} · {{ $chat->created_at->diffForHumans() }}
                    </span>
                    <p @class([
                        'max-w-[85%] whitespace-pre-line break-words rounded-card px-3 py-2 text-sm',
                        'bg-emerald-700 text-white' => $mine,
                        'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-100' => ! $mine,
                    ])>{{ $chat->message }}</p>
                </div>
            @empty
                <x-empty-state :title="__('chat.empty')" :description="__('chat.empty_hint')" />
            @endforelse
        </div>

        @if ($canSend)
            <form wire:submit="send" class="flex flex-col gap-2 border-t border-slate-200 p-card dark:border-slate-800">
                <flux:textarea wire:model="message" rows="2" maxlength="{{ $maxLength }}"
                               :placeholder="__('chat.placeholder')" :aria-label="__('chat.fields.message')" />
                <flux:error name="message" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('chat.send') }}</flux:button>
                </div>
            </form>
        @else
            <p class="border-t border-slate-200 p-card text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400">{{ __('chat.closed') }}</p>
        @endif
    </div>
</x-card>
