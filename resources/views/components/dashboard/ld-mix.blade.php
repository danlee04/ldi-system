@props(['rows', 'year'])

@php
    // Fixed slots, never cycled: the biggest type is always the first
    // colour, whether or not a smaller one drops out of the year.
    //
    // Three hues and then greys, because a fourth and fifth hue do not
    // survive the check. Every candidate purple collapses into the blue
    // under deuteranopia (deltaE 3.9 against a floor of 8) and every yellow
    // collapses into the orange, so a five-hue set fails all-pairs however
    // it is stepped. The slices are sorted largest first, which puts the
    // greys on the smallest ones, and every slice is named in the legend
    // beside its count — the colour ties a name to a slice and is never
    // the only thing saying which is which.
    $colours = [
        'var(--color-chart-1)',
        'var(--color-chart-2)',
        'var(--color-chart-3)',
        'var(--color-zinc-400)',
        'var(--color-zinc-600)',
    ];

    $total = array_sum(array_column($rows, 'attendances'));

    // A circle of radius 15.915 has a circumference of 100, so a slice can
    // be drawn straight from its percentage.
    $offset = 0;

    $slices = [];

    foreach ($rows as $index => $row) {
        $share = $total === 0 ? 0 : $row['attendances'] / $total * 100;

        $slices[] = [
            'label' => $row['label'],
            'attendances' => $row['attendances'],
            'share' => $row['share'],
            'colour' => $colours[$index] ?? 'var(--color-zinc-500)',
            // A hair off each slice leaves the surface showing between
            // them, so two neighbours never read as one.
            'length' => max($share - 0.7, 0),
            'gap' => 100 - max($share - 0.7, 0),
            'offset' => 100 - $offset,
        ];

        $offset += $share;
    }
@endphp

{{-- The card fills its half of the row so it ends level with the coverage
     panel beside it. The ring takes whatever height is spare and stays in
     the middle of it; the names stay put at the foot. --}}
<flux:card class="flex h-full flex-col gap-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <flux:heading size="lg">{{ __('Type of learning and development') }}</flux:heading>
        <flux:text size="sm">{{ $year }}</flux:text>
    </div>

    @if ($rows === [])
        <flux:text size="sm">{{ __('No approved training ended this year yet.') }}</flux:text>
    @else
        {{-- The ring on the left, the names on the right. The card is half of
             a column that also carries the right rail, so the ring is kept
             small: at any more than this the names lose the width they need
             and "Supervisory" is cut to "S…". --}}
        <div class="flex flex-1 items-center gap-4">
            <div class="relative shrink-0">
                <svg viewBox="0 0 42 42" class="size-36 -rotate-90" role="img"
                    aria-label="{{ __('Attendances by type of learning and development') }}">
                    @foreach ($slices as $slice)
                        <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="7"
                            stroke="{{ $slice['colour'] }}"
                            stroke-dasharray="{{ $slice['length'] }} {{ $slice['gap'] }}"
                            stroke-dashoffset="{{ $slice['offset'] }}" />
                    @endforeach
                </svg>

                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-xl font-semibold tabular-nums">{{ number_format($total) }}</span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('attendances') }}</span>
                </div>
            </div>

            {{-- The names carry the identity; the colour only ties a name to
                 its slice. The count and the share sit under the name rather
                 than beside it — this column is about 180px, and a name and
                 two figures on one line would cut the name. --}}
            <div class="min-w-0 flex-1 divide-y divide-zinc-200 dark:divide-white/10">
                @foreach ($slices as $slice)
                    <div class="flex items-center gap-2.5 py-1.5 text-sm first:pt-0 last:pb-0">
                        <span class="size-2.5 shrink-0 rounded-xs" aria-hidden="true"
                            style="background: {{ $slice['colour'] }}"></span>

                        <span class="min-w-0 flex-1 truncate" title="{{ $slice['label'] }}">{{ $slice['label'] }}</span>

                        <span class="shrink-0 tabular-nums text-zinc-600 dark:text-zinc-300">
                            {{ $slice['attendances'] }} · {{ $slice['share'] }}%
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</flux:card>
