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

    {{-- The same month the calendar page draws, sized to the rail so it
         fits without a sideways scroll. A title is cut short here; the
         whole of it is on the calendar page and on hover. --}}
    <x-calendar.month :weeks="$calendar['weeks']" compact />

    @if ($calendar['legend'] !== [])
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
            @foreach ($calendar['legend'] as $entry)
                <div class="flex items-center gap-1.5">
                    <span class="size-3 rounded-sm {{ $entry['classes'] }}" aria-hidden="true"></span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $entry['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <flux:link :href="route('calendar')" wire:navigate class="text-sm">
        {{ __('Open the calendar') }}
    </flux:link>
</flux:card>
