@props([
    'icon' => 'inbox',
    'tone' => 'zinc',
    'heading' => null,
])

@php
    [$box, $plate, $headingInk, $bodyInk] = match ($tone) {
        'emerald' => [
            'border-emerald-200 bg-emerald-50/60 dark:border-emerald-400/20 dark:bg-emerald-400/5',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
            'text-emerald-900 dark:text-emerald-300',
            'text-emerald-700/80 dark:text-emerald-400/80',
        ],
        default => [
            'border-dashed border-zinc-300 dark:border-white/10',
            'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400',
            'text-zinc-800 dark:text-zinc-100',
            'text-zinc-500 dark:text-zinc-400',
        ],
    };
@endphp

{{-- What a panel shows instead of a list. Emerald says the empty list is
     the good outcome — nobody untrained, nothing lapsing. Zinc says it is
     merely empty, and its dashed border is the other half of that: a space
     waiting to be filled rather than a result. --}}
<div class="flex items-center gap-3 rounded-xl border px-4 py-4 {{ $box }}">
    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $plate }}">
        <flux:icon :name="$icon" variant="mini" />
    </span>

    <div class="min-w-0">
        @if ($heading)
            <div class="text-sm font-medium {{ $headingInk }}">{{ $heading }}</div>
            <div class="text-xs {{ $bodyInk }}">{{ $slot }}</div>
        @else
            <flux:text size="sm">{{ $slot }}</flux:text>
        @endif
    </div>
</div>
