@props(['cards', 'columns' => 4])

@php
    // The team and agency rows carry four; the personal one carries three,
    // and a three-wide grid keeps them the same size as everywhere else
    // rather than leaving a hole where a fourth would sit.
    $grid = $columns === 3
        ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3'
        : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4';

    $tones = [
        'blue' => [
            'icon' => 'bg-blue-50 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400',
            'value' => 'text-zinc-950 dark:text-white',
        ],
        'teal' => [
            'icon' => 'bg-teal-50 text-teal-700 dark:bg-teal-400/10 dark:text-teal-400',
            'value' => 'text-zinc-950 dark:text-white',
        ],
        'violet' => [
            'icon' => 'bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-400',
            'value' => 'text-zinc-950 dark:text-white',
        ],
        'pink' => [
            'icon' => 'bg-pink-50 text-pink-600 dark:bg-pink-400/10 dark:text-pink-400',
            'value' => 'text-zinc-950 dark:text-white',
        ],
        'amber' => [
            'icon' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
            'value' => 'text-zinc-950 dark:text-white',
        ],
        'emerald' => [
            'icon' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
            'value' => 'text-zinc-950 dark:text-white',
        ],
    ];
@endphp

<div class="{{ $grid }}">
    @foreach ($cards as $card)
        @php
            $tone = $tones[$card['tone'] ?? 'blue'] ?? $tones['blue'];
        @endphp

        <div
            class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4">
                {{-- Icon --}}
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $tone['icon'] }}">
                    <flux:icon :icon="$card['icon']" variant="mini" class="size-5" />
                </span>

                @if (isset($card['href']))
                    <a href="{{ $card['href'] }}" wire:navigate
                        class="flex size-8 items-center justify-center rounded-lg text-zinc-400 opacity-0 transition-all group-hover:opacity-100 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10 dark:hover:text-zinc-200"
                        aria-label="{{ $card['label'] }}">
                        <flux:icon name="arrow-up-right" variant="micro" />
                    </a>
                @endif
            </div>

            <div class="mt-5">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    {{ $card['label'] }}
                </div>

                <div class="mt-1 text-3xl font-bold tracking-tight tabular-nums {{ $tone['value'] }}">
                    {{ $card['value'] }}
                </div>

                @if (isset($card['href']))
                    <a href="{{ $card['href'] }}" wire:navigate
                        class="mt-2 block text-xs font-medium text-zinc-500 underline decoration-zinc-300 underline-offset-4 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:decoration-zinc-600 dark:hover:text-white">
                        {{ $card['support'] }}
                    </a>
                @else
                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $card['support'] }}
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
