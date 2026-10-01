@props(['rows', 'year'])

@php
    $colours = [
        'var(--color-chart-1)',
        'var(--color-chart-2)',
        'var(--color-chart-3)',
        'var(--color-zinc-400)',
        'var(--color-zinc-600)',
    ];

    $total = array_sum(array_column($rows, 'attendances'));

    $offset = 0;
    $slices = [];

    foreach ($rows as $index => $row) {
        $share = $total === 0 ? 0 : ($row['attendances'] / $total) * 100;

        $length = max($share - 0.8, 0);

        $slices[] = [
            'label' => $row['label'],
            'attendances' => $row['attendances'],
            'share' => $row['share'],
            'colour' => $colours[$index] ?? 'var(--color-zinc-500)',
            'length' => $length,
            'gap' => 100 - $length,
            'offset' => 100 - $offset,
        ];

        $offset += $share;
    }
@endphp

<flux:card class="flex h-full flex-col gap-5">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="lg" class="font-semibold">
                {{ __('Type of learning and development') }}
            </flux:heading>

            <flux:text size="sm" class="mt-1">
                {{ __('Distribution of completed training') }}
            </flux:text>
        </div>

        <span
            class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
            {{ $year }}
        </span>
    </div>

    @if ($rows === [])
        <div
            class="flex min-h-48 items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-white/10">
            <flux:text size="sm">
                {{ __('No approved training ended this year yet.') }}
            </flux:text>
        </div>
    @else
        <div class="grid items-center gap-6 sm:grid-cols-[180px_minmax(0,1fr)]">
            {{-- Donut --}}
            <div class="relative mx-auto">
                <svg viewBox="0 0 42 42" class="size-44 -rotate-90" role="img"
                    aria-label="{{ __('Attendances by type of learning and development') }}">
                    {{-- Track --}}
                    <circle cx="21" cy="21" r="15.915" fill="none" stroke="currentColor"
                        stroke-width="7" class="text-zinc-100 dark:text-white/10" />

                    @foreach ($slices as $slice)
                        <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="7"
                            stroke="{{ $slice['colour'] }}" stroke-linecap="round"
                            stroke-dasharray="{{ $slice['length'] }} {{ $slice['gap'] }}"
                            stroke-dashoffset="{{ $slice['offset'] }}" />
                    @endforeach
                </svg>

                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-bold tracking-tight tabular-nums">
                        {{ number_format($total) }}
                    </span>

                    <span class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('attendances') }}
                    </span>
                </div>
            </div>

            {{-- Legend --}}
            <div class="space-y-2">
                @foreach ($slices as $slice)
                    <div
                        class="flex items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2.5 dark:border-white/10">
                        <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $slice['colour'] }}"
                            aria-hidden="true"></span>

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium" title="{{ $slice['label'] }}">
                                {{ $slice['label'] }}
                            </div>

                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $slice['share'] }}%
                            </div>
                        </div>

                        <span class="text-sm font-semibold tabular-nums">
                            {{ number_format($slice['attendances']) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</flux:card>
