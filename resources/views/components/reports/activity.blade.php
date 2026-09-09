@props(['records', 'totals'])

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card>
            <flux:text size="sm">{{ __('Attendances') }}</flux:text>
            <flux:heading size="xl">{{ $totals['attendances'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Employees') }}</flux:text>
            <flux:heading size="xl">{{ $totals['employees'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Hours') }}</flux:text>
            <flux:heading size="xl">{{ number_format($totals['hours']) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Cost') }}</flux:text>
            <flux:heading size="xl">{{ number_format($totals['cost'], 2) }}</flux:heading>
        </flux:card>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Training') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Hours') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Cost') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($records as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>{{ $record->employee->division?->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-52 truncate" title="{{ $record->employee->full_name }}">
                            {{ $record->employee->listing_name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-64 truncate" title="{{ $record->title }}">{{ $record->title }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $record->inclusive_dates }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">
                        {{ number_format((float) $record->registration_fee + (float) $record->tev + (float) $record->expenses, 2) }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No training ended in this month.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
