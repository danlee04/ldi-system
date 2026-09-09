@props(['rows', 'year'])

<flux:card class="space-y-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <flux:heading size="lg">{{ __('Type of learning and development') }}</flux:heading>
        <flux:text size="sm">{{ $year }}</flux:text>
    </div>

    @if ($rows === [])
        <flux:text size="sm">{{ __('No approved training ended this year yet.') }}</flux:text>
    @else
        {{--
            Emphasis, not a categorical set: one type carries nearly all of
            it, and that imbalance is what the panel is for. Colouring four
            types four ways would bury it.
        --}}
        @foreach ($rows as $index => $row)
            <div class="space-y-1">
                <div class="flex items-baseline justify-between gap-2 text-sm">
                    <span>{{ $row['label'] }}</span>
                    <span class="tabular-nums text-zinc-500 dark:text-zinc-400">
                        {{ $row['attendances'] }} — {{ $row['share'] }}%
                    </span>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
                    <div @class([
                            'h-full rounded-full',
                            'bg-[var(--color-accent)]' => $index === 0,
                            'bg-zinc-400 dark:bg-zinc-500' => $index > 0,
                        ])
                        style="width: {{ max(1, $row['share']) }}%"></div>
                </div>
            </div>
        @endforeach
    @endif
</flux:card>
