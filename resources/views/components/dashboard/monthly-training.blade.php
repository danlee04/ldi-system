@props(['months', 'peak', 'year'])

<flux:card class="space-y-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <flux:heading size="lg">{{ __('Training completed each month') }}</flux:heading>
        <flux:text size="sm">{{ $year }}</flux:text>
    </div>

    {{--
        Twelve columns and a three-letter label each need about 32rem before
        the labels start colliding, so the chart scrolls inside itself on a
        phone rather than squeezing. A scrolling region has to be reachable
        by keyboard, which is what the tabindex and the name are for.
    --}}
    <div class="overflow-x-auto overscroll-x-contain" tabindex="0" role="region"
        aria-label="{{ __('Attendances per month for :year', ['year' => $year]) }}">
        <div class="min-w-[32rem] space-y-2">
            {{-- One series, so one hue and no legend — the heading names it.
                 The bars are plain divs: nothing here needs a charting
                 library, and a div prints. --}}
            <div class="flex h-40 items-end gap-1">
                @foreach ($months as $month)
                    <div class="flex h-full flex-1 flex-col justify-end gap-1"
                        title="{{ $month['label'] }}: {{ $month['attendances'] }}">
                        <div class="text-center text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                            {{ $month['attendances'] > 0 ? $month['attendances'] : '' }}
                        </div>

                        <div class="rounded-t bg-[var(--color-accent)]"
                            style="height: {{ max(2, round($month['attendances'] / $peak * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-1">
                @foreach ($months as $month)
                    <div class="flex-1 text-center text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $month['label'] }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</flux:card>
