@props(['employees', 'plans', 'funding', 'spend', 'year'])

{{-- One colour across the three, the Center's own. Three different
     hues were decorating rather than telling: the label beside each icon
     already says what the card counts. The colour sits on the icon and
     never on the number, so the figure stays in reading ink. --}}
<div class="grid gap-4 lg:grid-cols-3">
    <flux:card class="space-y-3">
        <div class="flex items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-blue-200">
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
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-blue-200">
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
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-blue-200">
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
