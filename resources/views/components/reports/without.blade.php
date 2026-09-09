@props(['rows', 'totals', 'year'])

<div class="space-y-6">
    <flux:callout icon="exclamation-triangle" variant="warning">
        {{ __(':without of :active active employees finished no approved training in :year. :never have never attended anything at all.', [
            'without' => $totals['without'],
            'active' => $totals['active'],
            'year' => $year,
            'never' => $totals['never'],
        ]) }}
    </flux:callout>

    <flux:table :paginate="$rows" pagination:class="print:hidden">
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Employee no.') }}</flux:table.column>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Position') }}</flux:table.column>
            <flux:table.column>{{ __('Last training') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rows as $row)
                @php($employee = $row['employee'])

                <flux:table.row :key="$employee->id">
                    <flux:table.cell>{{ $employee->division?->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-48 truncate" title="{{ $employee->section?->name }}">
                            {{ $employee->section?->name ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->employee_number }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-52 truncate" title="{{ $employee->full_name }}">
                            {{ $employee->listing_name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-48 truncate" title="{{ $employee->position?->title }}">
                            {{ $employee->position?->title ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @if ($row['last_training'] === null)
                            <flux:badge color="red">{{ __('Never') }}</flux:badge>
                        @else
                            {{ $row['last_training']->format('d M Y') }}
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">
                        {{ __('Everybody in this filter has training on record for the year.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
