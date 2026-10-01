@props(['stats', 'year', 'plansLabel' => null])

@php
    $rows = collect($stats['statuses'])
        ->map(
            fn(int $count, string $status): array => [
                'icon' => 'identification',
                'label' => $status,
                'value' => number_format($count),
            ],
        )
        ->values()
        ->push([
            'icon' => 'calendar-days',
            'label' => $plansLabel ?? __('LDI plans in :year', ['year' => $year]),
            'value' => number_format($stats['plans']),
        ])
        ->merge($stats['extra'] ?? []);
@endphp

<flux:card class="space-y-5">
    <div>
        <flux:heading size="lg" class="font-semibold">
            {{ __('Quick stats') }}
        </flux:heading>

        <flux:text size="sm" class="mt-1">
            {{ __('At-a-glance workforce information') }}
        </flux:text>
    </div>

    <div class="grid gap-2">
        @foreach ($rows as $row)
            <div class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3 py-3 dark:border-white/10">
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon :icon="$row['icon']" variant="micro" />
                </span>

                <span class="min-w-0 flex-1 truncate text-sm font-medium" title="{{ $row['label'] }}">
                    {{ $row['label'] }}
                </span>

                <span class="text-base font-semibold tabular-nums">
                    {{ $row['value'] }}
                </span>
            </div>
        @endforeach
    </div>
</flux:card>
