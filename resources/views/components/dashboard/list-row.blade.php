@props([
    'icon',
    'tone' => 'zinc',
    'title',
    'titleTooltip' => null,
    'href' => null,
    'subtitle' => null,
    'subtitleTooltip' => null,
    'note' => null,
    'wrap' => false,
])

@php
    $plate = match ($tone) {
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
        'blue' => 'bg-blue-50 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400',
        'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        default => 'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400',
    };
@endphp

{{-- One line of a dashboard panel: an icon that says what kind of thing it
     is, the thing itself, what it belongs to, and on the right whatever
     the caller puts in the trailing slot — an age, a badge, a date.

     Titles truncate on the wrapper, never on the link inside it: flux:link
     is always `inline`, and an inline element ignores a width. Pass wrap
     for a title worth two lines, as a submission's own title is. --}}
<div @class([
    'group flex gap-3 rounded-xl border border-zinc-200 px-3 py-3 transition-colors hover:bg-zinc-50 dark:border-white/10 dark:hover:bg-white/[0.03]',
    'items-start' => $wrap,
    'items-center' => ! $wrap,
])>
    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $plate }}">
        <flux:icon :name="$icon" variant="micro" />
    </span>

    <div class="min-w-0 flex-1">
        <div @class(['text-sm font-medium', 'break-words' => $wrap, 'truncate' => ! $wrap])
            title="{{ $titleTooltip ?? $title }}">
            @if ($href)
                <flux:link :href="$href" wire:navigate>{{ $title }}</flux:link>
            @else
                {{ $title }}
            @endif
        </div>

        @if ($subtitle !== null)
            <div @class(['text-xs text-zinc-500 dark:text-zinc-400', 'mt-1' => $wrap, 'truncate' => ! $wrap])
                title="{{ $subtitleTooltip ?? $subtitle }}">
                {{ $subtitle }}
            </div>
        @endif

        @if ($note !== null)
            <div class="mt-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $note }}</div>
        @endif
    </div>

    @if (trim($slot) !== '')
        <div class="shrink-0 text-right">{{ $slot }}</div>
    @endif
</div>
