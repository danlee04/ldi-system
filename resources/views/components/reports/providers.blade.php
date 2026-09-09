@props(['rows', 'totals'])

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card>
            <flux:text size="sm">{{ __('Providers') }}</flux:text>
            <flux:heading size="xl">{{ $totals['providers'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Attendances') }}</flux:text>
            <flux:heading size="xl">{{ $totals['attendances'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Total cost') }}</flux:text>
            <flux:heading size="xl">{{ number_format($totals['cost'], 2) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Per participant') }}</flux:text>
            <flux:heading size="xl">{{ number_format($totals['per_participant'], 2) }}</flux:heading>
        </flux:card>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Provider') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Attendances') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Employees') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Hours') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Total cost') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Per participant') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rows as $row)
                <flux:table.row :key="$row['provider']">
                    <flux:table.cell>
                        <div class="w-80 truncate" title="{{ $row['provider'] }}">{{ $row['provider'] }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['attendances'] }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['employees'] }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ number_format($row['hours']) }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ number_format($row['cost'], 2) }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">
                        {{ number_format($row['per_participant'], 2) }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No approved training ended in this year.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
