@props([
    'months',
    'peak',
    'divisions',
    'years',
    'month',
    'year',
    'filterModel' => 'chartDivision',
    'filterAll' => null,
    'drillable' => true,
])

@php
    $drilled = $month !== null;

    $heading = $drilled
        ? __('Training completed in :month', [
            'month' => \Carbon\CarbonImmutable::create($year, $month, 1)->format('F Y'),
        ])
        : __('Training completed each month');

    $width = $drilled ? 'min-w-[42rem]' : 'min-w-[38rem]';

    $chartPeak = max(1, $peak);
@endphp

<flux:card class="space-y-5">
    {{-- Header --}}
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="lg" class="font-semibold">
                    {{ $heading }}
                </flux:heading>

                <span
                    class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    {{ $year }}
                </span>
            </div>

            <flux:text size="sm" class="mt-1">
                {{ $drilled ? __('Attendance by division') : __('Completed training and LDI activities') }}
            </flux:text>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-2">
            @if ($divisions->isNotEmpty())
                <flux:select size="sm" class="w-44" wire:model.live="{{ $filterModel }}">
                    <flux:select.option value="">
                        {{ $filterAll ?? __('All divisions') }}
                    </flux:select.option>

                    @foreach ($divisions as $division)
                        <flux:select.option :value="$division->id">
                            {{ $division->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if ($drillable)
                <flux:select size="sm" class="w-36" wire:model.live="chartMonth">
                    <flux:select.option value="">
                        {{ __('All months') }}
                    </flux:select.option>

                    @foreach (range(1, 12) as $number)
                        <flux:select.option :value="$number">
                            {{ \Carbon\CarbonImmutable::create($year, $number, 1)->format('F') }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select size="sm" class="w-28" wire:model.live="chartYear">
                @foreach ($years as $option)
                    <flux:select.option :value="$option">
                        {{ $option }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Chart --}}
    <div class="overflow-x-auto overscroll-x-contain" tabindex="0" role="region"
        aria-label="{{ $drilled
            ? __('Attendances by division in the month')
            : __('Attendances per month for :year', ['year' => $year]) }}">
        <div class="{{ $width }}">
            @if ($months === [])
                <div
                    class="flex min-h-64 items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-white/10">
                    <flux:text size="sm">
                        {{ __('Nothing was completed here.') }}
                    </flux:text>
                </div>
            @else
                <div class="relative h-72">
                    {{-- Horizontal grid --}}
                    <div class="pointer-events-none absolute inset-x-0 inset-y-0 flex flex-col justify-between">
                        @foreach (range(0, 4) as $grid)
                            <div class="border-t border-dashed border-zinc-200 dark:border-white/10"></div>
                        @endforeach
                    </div>

                    {{-- Bars --}}
                    <div class="relative flex h-full items-end gap-2">
                        @foreach ($months as $row)
                            @php
                                $attendanceHeight =
                                    $row['attendances'] > 0
                                        ? max(4, round(($row['attendances'] / $chartPeak) * 100))
                                        : 0;

                                $planHeight = $row['plans'] > 0 ? max(4, round(($row['plans'] / $chartPeak) * 100)) : 0;
                            @endphp

                            <div class="flex h-full min-w-0 flex-1 flex-col justify-end">
                                {{-- Values --}}
                                <div class="mb-2 flex items-end justify-center gap-1">
                                    @if ($row['attendances'] > 0)
                                        <span
                                            class="text-[10px] font-semibold tabular-nums text-zinc-600 dark:text-zinc-300">
                                            {{ $row['attendances'] }}
                                        </span>
                                    @endif

                                    @unless ($drilled)
                                        @if ($row['plans'] > 0)
                                            <span class="text-[10px] tabular-nums text-zinc-400 dark:text-zinc-500">
                                                / {{ $row['plans'] }}
                                            </span>
                                        @endif
                                    @endunless
                                </div>

                                {{-- Bars --}}
                                <div class="flex h-56 items-end justify-center gap-1">
                                    {{-- Training --}}
                                    <div class="w-full max-w-8 rounded-t-md bg-(--color-chart-1) transition-all duration-300 hover:opacity-80"
                                        style="height: {{ $attendanceHeight }}%"
                                        title="{{ $row['label'] }} — {{ __('completed') }}: {{ $row['attendances'] }}">
                                    </div>

                                    {{-- Plans --}}
                                    @unless ($drilled)
                                        <div class="w-full max-w-8 rounded-t-md bg-(--color-brand-accent) transition-all duration-300 hover:opacity-80"
                                            style="height: {{ $planHeight }}%"
                                            title="{{ $row['label'] }} — {{ __('LDI trainings') }}: {{ $row['plans'] }}">
                                        </div>
                                    @endunless
                                </div>

                                {{-- Label --}}
                                <div class="mt-3 truncate text-center text-xs font-medium text-zinc-500 dark:text-zinc-400"
                                    title="{{ $row['label'] }}">
                                    {{ $row['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Legend --}}
    @unless ($drilled)
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-zinc-200 pt-4 dark:border-white/10">
            <div class="flex items-center gap-2">
                <span class="size-2.5 rounded-full bg-(--color-chart-1)"></span>

                <flux:text size="sm">
                    {{ __('Training completed') }}
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                <span class="size-2.5 rounded-full bg-(--color-brand-accent)"></span>

                <flux:text size="sm">
                    {{ __('LDI trainings held') }}
                </flux:text>
            </div>
        </div>
    @endunless
</flux:card>
