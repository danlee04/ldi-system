@props(['rows', 'year', 'heading' => null])

<flux:card class="space-y-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <flux:heading size="lg">{{ $heading ?? __('Training coverage by division') }}</flux:heading>
        <flux:text size="sm">{{ $year }}</flux:text>
    </div>

    @forelse ($rows as $row)
        <div class="space-y-1">
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span>{{ $row['division'] }}</span>
                <span class="tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ $row['covered'] }}/{{ $row['employees'] }} — {{ $row['percentage'] }}%
                </span>
            </div>

            <flux:progress :value="$row['percentage']" />
        </div>
    @empty
        <flux:text size="sm">{{ __('No division has anybody in it yet.') }}</flux:text>
    @endforelse
</flux:card>
