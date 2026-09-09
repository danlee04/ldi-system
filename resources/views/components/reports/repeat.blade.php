@props(['rows', 'totals'])

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text size="sm">{{ __('Attendees') }}</flux:text>
            <flux:heading size="xl">{{ $totals['attendees'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('First-timers') }}</flux:text>
            <flux:heading size="xl">{{ $totals['first_timers'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Been before') }}</flux:text>
            <flux:heading size="xl">{{ $totals['repeats'] }}</flux:heading>
        </flux:card>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('This year') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Earlier') }}</flux:table.column>
            <flux:table.column>{{ __('Standing') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rows as $row)
                <flux:table.row :key="$row['employee']->id">
                    <flux:table.cell>{{ $row['employee']->division?->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-56 truncate" title="{{ $row['employee']->full_name }}">
                            {{ $row['employee']->listing_name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['attendances'] }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['earlier'] }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($row['first_timer'])
                            <flux:badge color="green">{{ __('First-timer') }}</flux:badge>
                        @else
                            <flux:badge color="zinc">{{ __('Repeat') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nobody attended anything in this year.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
