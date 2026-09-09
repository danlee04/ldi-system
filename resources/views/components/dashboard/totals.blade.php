@props(['employees', 'plans', 'spend', 'year'])

<div class="grid gap-4 lg:grid-cols-3">
    <flux:card class="space-y-3">
        <flux:text size="sm">{{ __('Total employees') }}</flux:text>
        <flux:heading size="xl" class="tabular-nums">{{ $employees['total'] }}</flux:heading>

        <div class="space-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
            @foreach ($employees['rows'] as $row)
                <div class="flex items-baseline justify-between gap-2 text-sm">
                    <span class="truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <span class="tabular-nums">{{ $row['count'] }}</span>
                </div>
            @endforeach
        </div>
    </flux:card>

    <flux:card class="space-y-3">
        <flux:text size="sm">{{ __('Total LDI trainings in :year', ['year' => $year]) }}</flux:text>
        <flux:heading size="xl" class="tabular-nums">{{ $plans['total'] }}</flux:heading>

        <div class="space-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
            @forelse ($plans['rows'] as $row)
                <div class="flex items-baseline justify-between gap-2 text-sm">
                    <span class="truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <span class="tabular-nums">{{ $row['count'] }}</span>
                </div>
            @empty
                <flux:text size="sm">{{ __('No plan started this year.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    <flux:card class="space-y-3">
        <flux:text size="sm">{{ __('Total expenses in :year', ['year' => $year]) }}</flux:text>
        <flux:heading size="xl" class="tabular-nums">{{ number_format($spend['total'], 2) }}</flux:heading>

        <div class="space-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span>{{ __('HR source') }}</span>
                <span class="tabular-nums">{{ number_format($spend['hr'], 2) }}</span>
            </div>

            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span>{{ __('Other sources') }}</span>
                <span class="tabular-nums">{{ number_format($spend['other'], 2) }}</span>
            </div>
        </div>

        @if ($spend['rows'] !== [])
            <div class="space-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
                @foreach ($spend['rows'] as $row)
                    <div class="flex items-baseline justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                        <span class="tabular-nums">{{ number_format($row['amount'], 2) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
</div>
