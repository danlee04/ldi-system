@props(['rows', 'totals'])

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text size="sm">{{ __('Active employees') }}</flux:text>
            <flux:heading size="xl">{{ $totals['employees'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Reached') }}</flux:text>
            <flux:heading size="xl">{{ $totals['covered'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Coverage') }}</flux:text>
            <flux:heading size="xl">{{ $totals['percentage'] }}%</flux:heading>
        </flux:card>
    </div>

    <flux:table :paginate="$rows" pagination:class="print:hidden">
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Employees') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('With training') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Without') }}</flux:table.column>
            <flux:table.column>{{ __('Coverage') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rows as $row)
                <flux:table.row :key="$row['division']">
                    <flux:table.cell>{{ $row['division'] }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['employees'] }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['covered'] }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">
                        {{ $row['employees'] - $row['covered'] }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex w-40 items-center gap-2">
                            <flux:progress :value="$row['percentage']" class="flex-1" />
                            <span class="tabular-nums">{{ $row['percentage'] }}%</span>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No division has anybody in it yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
