@props(['stats', 'year'])

<flux:card class="space-y-3">
    <flux:heading size="lg">{{ __('Quick stats') }}</flux:heading>

    <div class="flex items-baseline justify-between gap-2">
        <flux:text size="sm">{{ __('Active employees') }}</flux:text>
        <flux:heading class="tabular-nums">{{ $stats['active'] }}</flux:heading>
    </div>

    <div class="space-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
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
