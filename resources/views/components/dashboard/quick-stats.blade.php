@props(['stats', 'year'])

<flux:card class="space-y-3">
    <flux:heading size="lg">{{ __('Quick stats') }}</flux:heading>

    {{-- The headcount leads its own card now, so this holds the cut that
         card does not: who is permanent and who is not. --}}
    <div class="space-y-1">
OLD_PLACEHOLDER
        @foreach ($stats['statuses'] as $status => $count)
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span class="truncate">{{ $status }}</span>
                <span class="tabular-nums">{{ $count }}</span>
            </div>
        @endforeach
    </div>

    <div class="flex items-baseline justify-between gap-2 border-t border-zinc-200 pt-3 text-sm dark:border-white/10">
        <span>{{ __('LDI plans in :year', ['year' => $year]) }}</span>
        <span class="tabular-nums">{{ $stats['plans'] }}</span>
    </div>
</flux:card>
