@props(['records', 'totals'])

@php($report = app(App\Actions\Reports\ApprovalsAgingReport::class))

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text size="sm">{{ __('Awaiting a decision') }}</flux:text>
            <flux:heading size="xl">{{ $totals['pending'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('With no approver') }}</flux:text>
            <flux:heading size="xl">{{ $totals['unroutable'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Longest wait') }}</flux:text>
            <flux:heading size="xl">{{ $totals['longest'] }} {{ __('days') }}</flux:heading>
        </flux:card>
    </div>

    @if ($totals['unroutable'] > 0)
        <flux:callout icon="exclamation-triangle" variant="warning">
            {{ __(':count of these have nobody to decide them, because neither the section nor the division has a head designated. Set one under Setup.', [
                'count' => $totals['unroutable'],
            ]) }}
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Training') }}</flux:table.column>
            <flux:table.column>{{ __('Submitted') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Days waiting') }}</flux:table.column>
            <flux:table.column>{{ __('Waiting on') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($records as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>{{ $record->employee?->division?->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-52 truncate" title="{{ $record->employee?->full_name }}">
                            {{ $record->employee?->listing_name ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-64 truncate" title="{{ $record->title }}">{{ $record->title }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $record->created_at?->format('d M Y') ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">
                        {{ $report->daysWaiting($record) }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($record->current_level === null)
                            <flux:badge color="red">{{ __('Nobody') }}</flux:badge>
                        @else
                            <flux:badge color="amber">{{ $record->current_level->label() }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('Nothing is waiting for a decision.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
