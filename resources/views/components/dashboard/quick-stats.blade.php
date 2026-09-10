@props(['stats', 'year'])

@php
    // Headcount by the terms people are employed on, then the year's plans.
    // Each line wears the icon of what it counts, so the rail can be read
    // down its left edge without reading the words.
    $rows = collect($stats['statuses'])
        ->map(fn (int $count, string $status): array => [
            'icon' => 'identification',
            'label' => $status,
            'value' => number_format($count),
        ])
        ->values()
        ->push([
            'icon' => 'calendar-days',
            'label' => __('LDI plans in :year', ['year' => $year]),
            'value' => number_format($stats['plans']),
        ]);
@endphp

<flux:card class="space-y-4">
    <flux:heading size="lg">{{ __('Quick stats') }}</flux:heading>

    <div class="divide-y divide-zinc-200 dark:divide-white/10">
        @foreach ($rows as $row)
            <div class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-blue-200">
                    <flux:icon :icon="$row['icon']" variant="micro" />
                </span>

                <span class="min-w-0 flex-1 truncate text-sm" title="{{ $row['label'] }}">{{ $row['label'] }}</span>

                <span class="text-sm tabular-nums">{{ $row['value'] }}</span>
            </div>
        @endforeach
    </div>
</flux:card>
