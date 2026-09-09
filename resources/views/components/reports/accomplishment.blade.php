@props(['rows', 'totals'])

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card>
            <flux:text size="sm">{{ __('Plans conducted') }}</flux:text>
            <flux:heading size="xl">{{ $totals['plans'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Attendances') }}</flux:text>
            <flux:heading size="xl">{{ $totals['attendees'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Hours') }}</flux:text>
            <flux:heading size="xl">{{ number_format($totals['hours']) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Spent') }}</flux:text>
            <flux:heading size="xl">{{ number_format($totals['spent'], 2) }}</flux:heading>
        </flux:card>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Facilitator') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Target') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Attended') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Spent') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($rows as $row)
                <flux:table.row :key="$row['plan']->id">
                    <flux:table.cell>
                        <div class="w-64 truncate" title="{{ $row['plan']->title }}">{{ $row['plan']->title }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $row['plan']->inclusive_dates }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['plan']->hours }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-48 truncate" title="{{ $row['plan']->facilitator }}">
                            {{ $row['plan']->facilitator }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">
                        {{ $row['plan']->target_attendees ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $row['attendees'] }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ number_format($row['spent'], 2) }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No plan started in this period.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
