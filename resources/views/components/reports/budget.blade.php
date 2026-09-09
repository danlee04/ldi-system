@props(['rows'])

<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Budget source') }}</flux:table.column>
        <flux:table.column class="text-right">{{ __('Plans') }}</flux:table.column>
        <flux:table.column class="text-right">{{ __('Cap') }}</flux:table.column>
        <flux:table.column class="text-right">{{ __('Committed') }}</flux:table.column>
        <flux:table.column class="text-right">{{ __('Spent') }}</flux:table.column>
        <flux:table.column class="text-right">{{ __('Unspent') }}</flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @forelse ($rows as $row)
            <flux:table.row :key="$row['source']">
                <flux:table.cell>{{ $row['source'] }}</flux:table.cell>
                <flux:table.cell class="text-right tabular-nums">{{ $row['plans'] }}</flux:table.cell>
                <flux:table.cell class="text-right tabular-nums">
                    {{ $row['cap'] === null ? '—' : number_format($row['cap'], 2) }}
                </flux:table.cell>
                <flux:table.cell class="text-right tabular-nums">{{ number_format($row['committed'], 2) }}</flux:table.cell>
                <flux:table.cell class="text-right tabular-nums">{{ number_format($row['spent'], 2) }}</flux:table.cell>
                <flux:table.cell class="text-right tabular-nums">
                    {{ number_format($row['committed'] - $row['spent'], 2) }}
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="6">
                    {{ __('No plan carried a budget source in this year. Set a cap under Setup to track one.') }}
                </flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>
