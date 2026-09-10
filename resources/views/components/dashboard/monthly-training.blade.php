@props(['months', 'peak', 'divisions', 'month', 'year'])

@php
    // A month asked for on its own would be a single bar, so picking one
    // turns the chart on its side: the bars become the divisions that were
    // in that month. The heading says which of the two you are looking at.
    $drilled = $month !== null;

    $heading = $drilled
        ? __('Training completed in :month', [
            'month' => \Carbon\CarbonImmutable::create($year, $month, 1)->format('F'),
        ])
        : __('Training completed each month');

    // Twelve columns and a three-letter label each need about 32rem before
    // the labels collide; a division's name needs a good deal more.
    $width = $drilled ? 'min-w-[40rem]' : 'min-w-[32rem]';
@endphp

<flux:card class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="lg">{{ $heading }}</flux:heading>

        <div class="flex flex-wrap items-center gap-2">
            <flux:select size="sm" class="w-44" wire:model.live="chartDivision">
                <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
                @foreach ($divisions as $division)
                    <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select size="sm" class="w-36" wire:model.live="chartMonth">
                <flux:select.option value="">{{ __('Whole of :year', ['year' => $year]) }}</flux:select.option>
                @foreach (range(1, 12) as $number)
                    <flux:select.option :value="$number">
                        {{ \Carbon\CarbonImmutable::create($year, $number, 1)->format('F') }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{--
        The chart scrolls inside itself rather than squeezing. A scrolling
        region has to be reachable by keyboard, which is what the tabindex
        and the name are for.
    --}}
    <div class="overflow-x-auto overscroll-x-contain" tabindex="0" role="region"
        aria-label="{{ $drilled
            ? __('Attendances by division in the month')
            : __('Attendances per month for :year', ['year' => $year]) }}">
        <div class="{{ $width }} space-y-2">
            @if ($months === [])
                <flux:text size="sm">{{ __('Nothing was completed here.') }}</flux:text>
            @else
                {{-- One series, so one hue and no legend — the heading names
                     it. The bars are plain divs: nothing here needs a
                     charting library, and a div prints. --}}
                <div class="flex h-64 items-end gap-1">
                    @foreach ($months as $row)
                        <div class="flex h-full flex-1 flex-col justify-end gap-1"
                            title="{{ $row['label'] }}: {{ $row['attendances'] }}">
                            <div class="text-center text-xs tabular-nums text-zinc-600 dark:text-zinc-300">
                                {{ $row['attendances'] > 0 ? $row['attendances'] : '' }}
                            </div>

                            <div class="rounded-t bg-[var(--color-accent)]"
                                style="height: {{ max(2, round($row['attendances'] / $peak * 100)) }}%"></div>
                        </div>
                    @endforeach
                </div>

                <div class="flex gap-1">
                    @foreach ($months as $row)
                        <div class="min-w-0 flex-1 truncate text-center text-xs text-zinc-600 dark:text-zinc-300"
                            title="{{ $row['label'] }}">{{ $row['label'] }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</flux:card>
