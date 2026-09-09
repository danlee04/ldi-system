@props(['calendar'])

<flux:card class="space-y-3">
    <div class="flex items-center justify-between gap-2">
        <flux:heading size="lg">{{ $calendar['month']->format('F Y') }}</flux:heading>

        <div class="flex gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left" square
                wire:click="previousMonth" :aria-label="__('Previous month')" />
            <flux:button size="sm" variant="ghost" icon="chevron-right" square
                wire:click="nextMonth" :aria-label="__('Next month')" />
        </div>
    </div>

    <div class="grid grid-cols-7 gap-1 text-center text-xs text-zinc-500 dark:text-zinc-400">
        @foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $weekday)
            <div>{{ $weekday }}</div>
        @endforeach
    </div>

    <div class="space-y-1">
        @foreach ($calendar['weeks'] as $week)
            <div class="grid grid-cols-7 gap-1">
                @foreach ($week as $cell)
                    @if ($cell['day'] === null)
                        <div></div>
                    @else
                        <div @class([
                                'flex aspect-square flex-col items-center justify-center rounded-md text-xs tabular-nums',
                                'bg-zinc-100 dark:bg-white/5' => $cell['date']->isToday(),
                            ])
                            @if ($cell['plans']->isNotEmpty())
                                title="{{ $cell['plans']->pluck('title')->join(', ') }}"
                            @endif>
                            <span>{{ $cell['day'] }}</span>

                            @if ($cell['plans']->isNotEmpty())
                                <span class="mt-0.5 size-1.5 rounded-full bg-[var(--color-accent)]"
                                    aria-hidden="true"></span>
                            @else
                                <span class="mt-0.5 size-1.5"></span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>

    @php
        $running = collect($calendar['weeks'])->flatten(1)->pluck('plans')->flatten()->unique('id');
    @endphp

    @if ($running->isEmpty())
        <flux:text size="sm">
            {{ __('No LDI training is planned for this month. Add one under LDI trainings.') }}
        </flux:text>
    @else
        <div class="space-y-1">
            @foreach ($running as $plan)
                <div class="text-sm">
                    <div class="truncate" title="{{ $plan->title }}">{{ $plan->title }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $plan->inclusive_dates }}</div>
                </div>
            @endforeach
        </div>
    @endif
</flux:card>
