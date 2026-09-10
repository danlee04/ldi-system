@props([
    'weeks',
    /** Sized for the rail rather than the page: tighter gaps, smaller
        boxes and type, so seven days fit a third of a screen. */
    'compact' => false,
    /** Whether a bar can be clicked and a day can be added to. */
    'onShow' => null,
    'onAdd' => null,
])

{{-- The month both calendars draw.

     One grid per week. The day boxes span every row of it, so a bar can be
     laid across them and cover the whole width of each day it runs — the
     day number included — rather than sitting as a strip at the bottom. --}}
<div class="space-y-1">
    <div @class([
        'grid grid-cols-7 text-center text-zinc-600 dark:text-zinc-300',
        'gap-0.5 text-[10px]' => $compact,
        'gap-1 text-xs' => ! $compact,
    ])>
        @foreach ([__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')] as $index => $weekday)
            {{-- Saturday and Sunday in red, the way an office calendar
                 marks the days nobody is in. --}}
            <div @class(['text-red-600 dark:text-red-400' => $index === 0 || $index === 6])>{{ $weekday }}</div>
        @endforeach
    </div>

    @foreach ($weeks as $week)
        <div @class(['grid grid-cols-7', 'gap-0.5' => $compact, 'gap-1' => ! $compact])
            style="grid-template-rows: auto repeat({{ max($week['lanes'], 1) }}, auto);">
            @foreach ($week['days'] as $index => $cell)
                <div style="grid-column: {{ $index + 1 }}; grid-row: 1 / -1;"
                    @class([
                        'rounded-md',
                        'min-h-14' => $compact,
                        'min-h-24' => ! $compact,
                        'bg-zinc-50 dark:bg-white/2' => $cell['day'] === null,
                        'border border-zinc-200 dark:border-white/10' => $cell['day'] !== null && ! $cell['date']->isToday(),
                        'border border-(--color-accent) bg-brand-primary/12 dark:bg-brand-primary/25' => $cell['day'] !== null && $cell['date']->isToday(),
                    ])></div>
            @endforeach

            @foreach ($week['days'] as $index => $cell)
                @if ($cell['day'] !== null)
                    <div @class(['flex items-center justify-between pt-0.5', 'px-0.5' => $compact, 'px-1' => ! $compact])
                        style="grid-column: {{ $index + 1 }}; grid-row: 1;">
                        {{-- The whole box carries today, tinted rather than
                             filled: what sits in the box is a bar, and a
                             solid day behind it would swallow it. --}}
                        <span @class([
                            'tabular-nums',
                            'text-[10px]' => $compact,
                            'text-xs' => ! $compact,
                            'font-semibold text-(--color-accent-content)' => $cell['date']->isToday(),
                            'text-red-600 dark:text-red-400' => ! $cell['date']->isToday() && $cell['date']->isWeekend(),
                        ])>{{ $cell['day'] }}</span>

                        @if ($onAdd)
                            {{-- A plain button, because a flux:button here
                                 would be larger than the cell it sits in. --}}
                            <button type="button"
                                wire:click="{{ $onAdd }}('{{ $cell['date']->toDateString() }}')"
                                class="cursor-pointer px-1 text-xs leading-none text-zinc-500 hover:text-[var(--color-accent-content)] dark:text-zinc-400"
                                aria-label="{{ __('Add an activity on :date', ['date' => $cell['date']->format('F j')]) }}">+</button>
                        @endif
                    </div>
                @endif
            @endforeach

            @foreach ($week['bars'] as $bar)
                @php
                    $barClasses = collect([
                        'block w-full truncate text-left leading-tight',
                        $compact ? 'px-0.5 text-[10px]' : 'px-1.5 py-0.5 text-[11px]',
                        $bar['classes'],
                        $bar['opensBefore'] ? '' : 'rounded-s-md',
                        $bar['runsOn'] ? '' : 'rounded-e-md',
                    ])->filter()->join(' ');

                    $label = ($bar['opensBefore'] ? '◀ ' : '').$bar['title'];
                @endphp

                <div wire:key="{{ $loop->parent->index }}-{{ $bar['key'] }}"
                    style="grid-column: {{ $bar['column'] }} / span {{ $bar['span'] }}; grid-row: {{ $bar['lane'] + 2 }};">
                    @if ($onShow)
                        <button type="button"
                            wire:click="{{ $onShow }}('{{ $bar['kind'] }}', {{ $bar['id'] }})"
                            class="cursor-pointer {{ $barClasses }}"
                            title="{{ $bar['title'] }}">{{ $label }}</button>
                    @else
                        <div class="{{ $barClasses }}" title="{{ $bar['title'] }}">{{ $label }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
