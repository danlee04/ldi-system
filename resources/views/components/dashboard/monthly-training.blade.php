@props(['months', 'peak', 'divisions', 'years', 'month', 'year'])

@php
    // A month asked for on its own would be a single bar, so picking one
    // turns the chart on its side: the bars become the divisions that were
    // in that month. The heading says which of the two you are looking at.
    $drilled = $month !== null;

    $heading = $drilled
        ? __('Training completed in :month', [
            'month' => \Carbon\CarbonImmutable::create($year, $month, 1)->format('F Y'),
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
                <flux:select.option value="">{{ __('All months') }}</flux:select.option>
                @foreach (range(1, 12) as $number)
                    <flux:select.option :value="$number">
                        {{ \Carbon\CarbonImmutable::create($year, $number, 1)->format('F') }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select size="sm" class="w-28" wire:model.live="chartYear">
                @foreach ($years as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
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
                {{-- Two series on one scale, never two scales: attendances
                     outnumber plans many times over, and that gap is the
                     truth of the year rather than a fault in the drawing.
                     The bars are plain divs — nothing here needs a charting
                     library, and a div prints. --}}
                <div class="flex h-64 items-end gap-1">
                    @foreach ($months as $row)
                        <div class="flex h-full flex-1 flex-col justify-end gap-1">
                            <div class="flex flex-1 items-end gap-px">
                                <div class="flex h-full flex-1 flex-col justify-end gap-1"
                                    title="{{ $row['label'] }} — {{ __('completed') }}: {{ $row['attendances'] }}">
                                    <div class="text-center text-xs tabular-nums text-zinc-600 dark:text-zinc-300">
                                        {{ $row['attendances'] > 0 ? $row['attendances'] : '' }}
                                    </div>

                                    <div class="rounded-t bg-(--color-chart-1)"
                                        style="height: {{ max(2, round($row['attendances'] / $peak * 100)) }}%"></div>
                                </div>

                                @unless ($drilled)
                                    <div class="flex h-full flex-1 flex-col justify-end gap-1"
                                        title="{{ $row['label'] }} — {{ __('LDI trainings') }}: {{ $row['plans'] }}">
                                        <div class="text-center text-xs tabular-nums text-zinc-600 dark:text-zinc-300">
                                            {{ $row['plans'] > 0 ? $row['plans'] : '' }}
                                        </div>

                                        <div class="rounded-t bg-(--color-brand-accent)"
                                            style="height: {{ max(2, round($row['plans'] / $peak * 100)) }}%"></div>
                                    </div>
                                @endunless
                            </div>
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

    @unless ($drilled)
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-zinc-200 pt-3 dark:border-white/10">
            @foreach ([
                ['label' => __('Training completed'), 'colour' => 'var(--color-chart-1)'],
                ['label' => __('LDI trainings held'), 'colour' => 'var(--color-brand-accent)'],
            ] as $entry)
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-xs" aria-hidden="true" style="background: {{ $entry['colour'] }}"></span>
                    <flux:text size="sm">{{ $entry['label'] }}</flux:text>
                </div>
            @endforeach
        </div>
    @endunless
</flux:card>
