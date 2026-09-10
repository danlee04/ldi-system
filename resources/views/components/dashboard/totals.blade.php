@props(['employees', 'plans', 'funding', 'spend', 'year'])

{{-- Each card wears the colour its subject wears elsewhere: trainings are
     the calendar's purple, money is green. The colour sits on the icon
     rather than the number, so the figure stays in reading ink. --}}
<div class="grid gap-4 lg:grid-cols-3">
    <flux:card class="space-y-3">
        <div class="flex items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-400/20 dark:text-blue-200">
                <flux:icon.users variant="mini" />
            </span>

            <flux:text size="sm">{{ __('Total employees') }}</flux:text>
        </div>

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
        <div class="flex items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-400/20 dark:text-purple-200">
                <flux:icon.academic-cap variant="mini" />
            </span>

            <flux:text size="sm">{{ __('Total LDI trainings in :year', ['year' => $year]) }}</flux:text>
        </div>

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

    {{-- The headline is what was spent. What was set aside sits under it,
         because the office is asked both questions about the same year. --}}
    <flux:card class="space-y-3">
        <div class="flex items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-700 dark:bg-green-400/20 dark:text-green-200">
                <flux:icon.banknotes variant="mini" />
            </span>

            <flux:text size="sm">{{ __('Total expenses in :year', ['year' => $year]) }}</flux:text>
        </div>

        <flux:heading size="xl" class="tabular-nums">{{ number_format($spend['total'], 2) }}</flux:heading>

        <div class="space-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span>{{ __('Registration') }}</span>
                <span class="tabular-nums">{{ number_format($spend['registration'], 2) }}</span>
            </div>

            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span>{{ __('Travel expenses') }}</span>
                <span class="tabular-nums">{{ number_format($spend['tev'], 2) }}</span>
            </div>

            @if ($spend['other'] > 0)
                <div class="flex items-baseline justify-between gap-2 text-sm">
                    <span>{{ __('Other expenses') }}</span>
                    <span class="tabular-nums">{{ number_format($spend['other'], 2) }}</span>
                </div>
            @endif
        </div>

        <div class="space-y-1 border-t border-zinc-200 pt-3 dark:border-white/10">
            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span>{{ __('Funded by HR') }}</span>
                <span class="tabular-nums">{{ number_format($funding['hr'], 2) }}</span>
            </div>

            <div class="flex items-baseline justify-between gap-2 text-sm">
                <span>{{ __('Funded by other sources') }}</span>
                <span class="tabular-nums">{{ number_format($funding['other'], 2) }}</span>
            </div>
        </div>
    </flux:card>
</div>
