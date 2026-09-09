@props(['spend', 'year'])

@php
    $parts = [
        ['label' => __('Registration fees'), 'amount' => $spend['registration'], 'slot' => 1],
        ['label' => __('Travelling expenses'), 'amount' => $spend['tev'], 'slot' => 2],
        ['label' => __('Other expenses'), 'amount' => $spend['other'], 'slot' => 3],
    ];

    $total = max(0.01, $spend['total']);
@endphp

<flux:card class="space-y-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <flux:heading size="lg">{{ __('What the year cost') }}</flux:heading>
        <flux:text size="sm">{{ $year }}</flux:text>
    </div>

    <flux:heading size="xl" class="tabular-nums">{{ number_format($spend['total'], 2) }}</flux:heading>

    {{--
        Three parts of one whole. The gap between fills is what separates
        them at a glance, and every part is named and valued below — the
        third hue sits at 2.74:1 on the light surface, so the colour is
        never the only thing carrying a segment's identity.
    --}}
    <div class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full" role="img"
        aria-label="{{ __('Registration :a, travelling :b, other :c', [
            'a' => number_format($spend['registration'], 2),
            'b' => number_format($spend['tev'], 2),
            'c' => number_format($spend['other'], 2),
        ]) }}">
        @foreach ($parts as $part)
            @if ($part['amount'] > 0)
                <div class="h-full bg-[var(--color-chart-{{ $part['slot'] }})]"
                    style="width: {{ $part['amount'] / $total * 100 }}%"></div>
            @endif
        @endforeach
    </div>

    <div class="space-y-2">
        @foreach ($parts as $part)
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="flex min-w-0 items-center gap-2">
                    <span class="size-2.5 shrink-0 rounded-full bg-[var(--color-chart-{{ $part['slot'] }})]"
                        aria-hidden="true"></span>
                    <span class="truncate">{{ $part['label'] }}</span>
                </span>

                <span class="tabular-nums">{{ number_format($part['amount'], 2) }}</span>
            </div>
        @endforeach
    </div>
</flux:card>
