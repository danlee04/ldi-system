@props([
    'weeks',
    /** Sized for the dashboard rail rather than the page: tighter gaps,
        smaller boxes and type, so seven days fit a third of a screen. */
    'compact' => false,
    /** Livewire method names, passed as strings, so the same month serves
        a page that can open an activity and one that can only show it. */
    'onShow' => null,
    'onAdd' => null,
])

{{-- The month both calendars draw.

     One grid per week, and the day boxes span every row of it. That is
     what lets a bar lie across the days it runs — the day number
     included — instead of sitting as a strip underneath them. --}}
<div @class([
    'select-none',
    'space-y-2' => $compact,
    'space-y-3' => !$compact,
])>
    <div @class([
        'grid grid-cols-7',
        'gap-px' => $compact,
        'gap-1' => !$compact,
    ])>
        {{-- Saturday and Sunday in red, the way an office calendar marks
             the days nobody is in. --}}
        @foreach ([__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')] as $index => $weekday)
            <div @class([
                'flex items-center justify-center',
                'text-[10px] font-medium' => $compact,
                'text-xs font-medium' => !$compact,
                'text-red-500 dark:text-red-400' => $index === 0 || $index === 6,
                'text-zinc-500 dark:text-zinc-400' => $index !== 0 && $index !== 6,
            ])>
                {{ $weekday }}
            </div>
        @endforeach
    </div>

    @foreach ($weeks as $week)
        <div @class([
            'grid grid-cols-7 overflow-hidden rounded-lg',
            'gap-px bg-zinc-200/70 dark:bg-white/10' => $compact,
            'gap-px bg-zinc-200 dark:bg-white/10' => !$compact,
        ]) style="grid-template-rows: auto repeat({{ max($week['lanes'], 1) }}, auto);">

            {{-- The boxes are laid first and span the whole height of the
                 week, so everything after them draws on top. What shows
                 between them is the grid's own background, which is the
                 ruling — there are no borders here. --}}
            @foreach ($week['days'] as $index => $cell)
                <div style="grid-column: {{ $index + 1 }}; grid-row: 1 / -1;" @class([
                    'relative min-w-0 bg-white dark:bg-zinc-950',

                    'min-h-14' => $compact,
                    'min-h-24' => !$compact,

                    // Days that belong to the month before or after this one.
                    'bg-zinc-50/70 dark:bg-white/[0.015]' => $cell['day'] === null,

                    'bg-red-50/30 dark:bg-red-500/[0.025]' =>
                        $cell['day'] !== null &&
                        $cell['date']->isWeekend() &&
                        !$cell['date']->isToday(),

                    // Today is a wash, not a fill: a bar sits on top of it,
                    // and a solid box behind one would swallow it.
                    'bg-[color-mix(in_srgb,var(--color-accent)_4%,transparent)]' =>
                        $cell['day'] !== null && $cell['date']->isToday(),
                ])></div>
            @endforeach

            @foreach ($week['days'] as $index => $cell)
                @if ($cell['day'] !== null)
                    <div @class([
                        'group relative z-10 flex items-start justify-between',
                        'min-w-0',
                    
                        'px-1 py-1' => $compact,
                        'px-1.5 py-1.5' => !$compact,
                    ]) style="grid-column: {{ $index + 1 }}; grid-row: 1;">
                        <span @class([
                            'flex shrink-0 items-center justify-center rounded-full font-medium tabular-nums',

                            'size-5 text-[10px]' => $compact,
                            'size-7 text-xs' => !$compact,

                            // Only today wears the disc, so the eye finds it
                            // without reading a single number. Foreground, not
                            // content: content is the accent itself, which on
                            // the accent disc is blue on blue.
                            'bg-[var(--color-accent)] text-[var(--color-accent-foreground)] font-semibold shadow-sm' => $cell[
                                'date'
                            ]->isToday(),

                            'text-red-500 dark:text-red-400' =>
                                !$cell['date']->isToday() && $cell['date']->isWeekend(),

                            'text-zinc-700 dark:text-zinc-200' =>
                                !$cell['date']->isToday() && !$cell['date']->isWeekend(),
                        ])>
                            {{ $cell['day'] }}
                        </span>

                        @if ($onAdd)
                            {{-- A plain button: a flux:button here would be
                                 larger than the cell it sits in, and it needs
                                 cursor-pointer because a bare one gets none.

                                 It waits for hover or focus. A plus sign on all
                                 thirty days would compete with what is actually
                                 booked, which is what the month is read for. --}}
                            <button type="button" wire:click="{{ $onAdd }}('{{ $cell['date']->toDateString() }}')"
                                class="
                                    flex size-5 cursor-pointer items-center justify-center
                                    rounded-full
                                    text-zinc-400
                                    opacity-0
                                    transition
                                    hover:bg-zinc-100
                                    hover:text-zinc-700
                                    group-hover:opacity-100
                                    focus-visible:opacity-100
                                    dark:text-zinc-500
                                    dark:hover:bg-white/10
                                    dark:hover:text-zinc-200
                                "
                                aria-label="{{ __('Add an activity on :date', [
                                    'date' => $cell['date']->format('F j'),
                                ]) }}">
                                <span @class([
                                    'text-sm leading-none' => $compact,
                                    'text-base leading-none' => !$compact,
                                ])>
                                    +
                                </span>
                            </button>
                        @endif
                    </div>
                @endif
            @endforeach

            @foreach ($week['bars'] as $bar)
                @php
                    $barClasses = collect([
                        'group/event relative z-20 block w-full min-w-0 truncate text-center',
                        'cursor-pointer transition-all duration-150',

                        'rounded-sm' => $compact,
                        'rounded-md' => !$compact,

                        $compact ? 'px-1 py-0.5 text-[11px] leading-tight' : 'px-2 py-1 text-xs leading-tight',

                        // The colour the activity type carries, written out in
                        // full by the enum — Tailwind cannot see a class a
                        // template pieces together.
                        $bar['classes'],

                        // Square where the run carries on past this week, round
                        // where it truly starts and ends, so a training that
                        // crosses a Saturday still reads as one thing.
                        $bar['opensBefore'] ? '' : ($compact ? 'rounded-s-none' : 'rounded-s-md'),

                        $bar['runsOn'] ? '' : ($compact ? 'rounded-e-none' : 'rounded-e-md'),

                        'hover:brightness-95 hover:shadow-sm dark:hover:brightness-110',
                    ])
                        ->filter()
                        ->join(' ');

                    // Says the activity began earlier, for a bar whose first
                    // day here is not the day it actually started.
                    $label = ($bar['opensBefore'] ? '‹ ' : '') . $bar['title'];

                    // A gap at the two ends of the run, so a bar does not sit
                    // flush against the ruling or against the next activity.
                    // Only at the true ends: padding where the run carries on
                    // into the next week would saw it in half.
                    $ends = collect([
                        $bar['opensBefore'] ? '' : ($compact ? 'ps-0.5' : 'ps-1'),
                        $bar['runsOn'] ? '' : ($compact ? 'pe-0.5' : 'pe-1'),
                    ])->filter()->join(' ');
                @endphp

                <div wire:key="{{ $loop->parent->index }}-{{ $bar['key'] }}" class="min-w-0 {{ $ends }}"
                    style="
                        grid-column: {{ $bar['column'] }} / span {{ $bar['span'] }};
                        grid-row: {{ $bar['lane'] + 2 }};
                    ">
                    @if ($onShow)
                        <button type="button"
                            wire:click="{{ $onShow }}('{{ $bar['kind'] }}', {{ $bar['id'] }})"
                            class="{{ $barClasses }}" title="{{ $bar['title'] }}">
                            <span class="block truncate">
                                {{ $label }}
                            </span>
                        </button>
                    @else
                        <div class="{{ $barClasses }}" title="{{ $bar['title'] }}">
                            <span class="block truncate">
                                {{ $label }}
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
