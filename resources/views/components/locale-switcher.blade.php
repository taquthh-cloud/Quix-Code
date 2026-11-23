<div {{ $attributes->class('inline-flex overflow-hidden rounded-lg border border-slate-300 dark:border-slate-700') }} role="group" aria-label="{{ __('ui.language') }}">
    @foreach (['id' => 'ID', 'en' => 'EN'] as $code => $short)
        <form method="POST" action="{{ route('locale.update', $code) }}">
            @csrf
            <button type="submit" @if (app()->getLocale() === $code) aria-current="true" @endif
                    @class([
                        'px-3 py-1.5 text-xs font-semibold',
                        'bg-growth text-white' => app()->getLocale() === $code,
                        'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800' => app()->getLocale() !== $code,
                    ])>
                {{ $short }}
            </button>
        </form>
    @endforeach
</div>
