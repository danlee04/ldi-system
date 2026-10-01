@props([
    'heading',
    'subtitle' => null,
    'count' => null,
    'countTone' => 'amber',
    'actionLabel' => null,
    'actionHref' => null,
])

@php
    // Written out in full, never pieced together: Tailwind scans the source
    // for whole class names and cannot resolve an interpolated one.
    $countClasses = match ($countTone) {
        'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'zinc' => 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-300',
        default => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
    };
@endphp

{{-- The shell every list panel on the dashboard wears: a card, a heading
     with its one line of explanation, and either a count or a way out to
     the page that holds the rest. The body is the slot.

     A link wraps under the heading on a phone; a count never does, because
     it is two or three characters and belongs beside what it counts. --}}
<flux:card {{ $attributes->class('space-y-5') }}>
    <div @class([
        'flex gap-4',
        'flex-col gap-3 sm:flex-row sm:items-start sm:justify-between' => $actionHref !== null,
        'items-start justify-between' => $actionHref === null,
    ])>
        <div class="min-w-0">
            <flux:heading size="lg" class="font-semibold">{{ $heading }}</flux:heading>

            @if ($subtitle)
                <flux:text size="sm" class="mt-1">{{ $subtitle }}</flux:text>
            @endif
        </div>

        @if ($actionHref)
            <flux:link :href="$actionHref" wire:navigate class="shrink-0">
                {{ $actionLabel }}
                <flux:icon name="arrow-up-right" variant="micro" />
            </flux:link>
        @elseif ($count !== null)
            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $countClasses }}">
                {{ $count }}
            </span>
        @endif
    </div>

    {{ $slot }}
</flux:card>
