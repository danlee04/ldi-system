@props(['calendar'])

<flux:card class="overflow-hidden p-0">
    {{-- Header --}}
    <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-white/10">
        <div>
            <flux:heading size="lg" class="font-semibold">
                {{ $calendar['month']->format('F Y') }}
            </flux:heading>

            <flux:text size="sm" class="mt-0.5">
                {{ __('Schedule overview') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left" square wire:click="previousMonth"
                :aria-label="__('Previous month')" />

            <flux:button size="sm" variant="ghost" icon="chevron-right" square wire:click="nextMonth"
                :aria-label="__('Next month')" />
        </div>
    </div>

    {{-- Calendar --}}
    <div class="px-5 py-4">
        <x-calendar.month :weeks="$calendar['weeks']" compact />
    </div>

    {{-- Legend --}}
    @if ($calendar['legend'] !== [])
        <div class="border-t border-zinc-200 px-5 py-3 dark:border-white/10">
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($calendar['legend'] as $entry)
                    <div class="flex items-center gap-2">
                        <span class="size-2.5 rounded-full {{ $entry['classes'] }}" aria-hidden="true"></span>

                        <span class="text-xs text-zinc-600 dark:text-zinc-300">
                            {{ $entry['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="border-t border-zinc-200 px-5 py-3 dark:border-white/10">
        <flux:link :href="route('calendar')" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium">
            {{ __('Open the calendar') }}

            <flux:icon name="arrow-up-right" variant="micro" />
        </flux:link>
    </div>
</flux:card>
