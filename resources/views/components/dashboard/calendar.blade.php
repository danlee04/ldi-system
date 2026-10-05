@props(['calendar'])

<flux:card class="overflow-hidden p-0">
    {{-- Header. The way out to the calendar sits up here, where every other
         dashboard panel keeps its link, and opens the month on show. The
         arrows stay at the far end, so stepping through months never moves
         them out from under the pointer. --}}
    <div
        class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
        <div class="min-w-0">
            <flux:heading size="lg" class="font-semibold">
                {{ $calendar['month']->format('F Y') }}
            </flux:heading>

            <flux:text size="sm" class="mt-0.5">
                {{ __('Schedule overview') }}
            </flux:text>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <flux:link :href="route('calendar', ['month' => $calendar['month']->format('Y-m')])" wire:navigate
                class="inline-flex items-center gap-1 text-sm font-medium">
                {{ __('Open the calendar') }}

                <flux:icon name="arrow-up-right" variant="micro" />
            </flux:link>

            <div class="flex items-center gap-1">
                <flux:button size="sm" variant="ghost" icon="chevron-left" square wire:click="previousMonth"
                    :aria-label="__('Previous month')" />

                <flux:button size="sm" variant="ghost" icon="chevron-right" square wire:click="nextMonth"
                    :aria-label="__('Next month')" />
            </div>
        </div>
    </div>

    <div class="space-y-4 px-5 py-4">
        {{-- Legend. Every kind, whether or not the month uses it, so the card
             keeps its height from one month to the next. --}}
        <div class="flex flex-wrap gap-x-3 gap-y-1.5">
            @foreach ($calendar['legend'] as $entry)
                <div class="flex items-center gap-1.5">
                    <span class="size-3 rounded-sm {{ $entry['classes'] }}" aria-hidden="true"></span>

                    <span class="text-xs text-zinc-600 dark:text-zinc-300">
                        {{ $entry['label'] }}
                    </span>
                </div>
            @endforeach
        </div>

        <x-calendar.month :weeks="$calendar['weeks']" compact />
    </div>
</flux:card>
