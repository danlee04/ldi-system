@props(['rows'])

<flux:table :paginate="$rows" pagination:class="print:hidden">
    <flux:table.columns>
        <flux:table.column>{{ __('Division') }}</flux:table.column>
        <flux:table.column align="end">{{ __('Attendances') }}</flux:table.column>
        <flux:table.column align="end">{{ __('Q1') }}</flux:table.column>
        <flux:table.column align="end">{{ __('Q2') }}</flux:table.column>
        <flux:table.column align="end">{{ __('Q3') }}</flux:table.column>
        <flux:table.column align="end">{{ __('Q4') }}</flux:table.column>
        <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @forelse ($rows as $row)
            <flux:table.row :key="$row['division']">
                <flux:table.cell>{{ $row['division'] }}</flux:table.cell>
                <flux:table.cell align="end" class="tabular-nums">{{ $row['attendances'] }}</flux:table.cell>
                @foreach ([1, 2, 3, 4] as $quarter)
                    <flux:table.cell align="end" class="tabular-nums">
                        {{ number_format($row['quarters'][$quarter], 2) }}
                    </flux:table.cell>
                @endforeach
                <flux:table.cell align="end" class="font-medium tabular-nums">
                    {{ number_format($row['total'], 2) }}
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="7">{{ __('No division is set up yet.') }}</flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>
