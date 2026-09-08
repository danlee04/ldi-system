<?php

use App\Models\TrainingRecord;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Training record')] class extends Component {
    public TrainingRecord $record;

    public function mount(TrainingRecord $record): void
    {
        $this->authorize('view', $record);

        $this->record = $record->load(['employee.section.division', 'submittedBy', 'approvals.approver']);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">{{ $record->title }}</flux:heading>
            <flux:text>
                {{ $record->employee->full_name }} · {{ $record->employee->section?->name }}
            </flux:text>
        </div>

        <x-training-status :record="$record" />
    </div>

    <flux:card class="grid gap-4 md:grid-cols-3">
        <div>
            <flux:text size="sm">{{ __('Inclusive dates') }}</flux:text>
            <flux:heading size="lg">
                {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
            </flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Number of hours') }}</flux:text>
            <flux:heading size="lg">{{ $record->hours }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Type of LD') }}</flux:text>
            <flux:heading size="lg">{{ $record->ld_type_label }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Conducted by') }}</flux:text>
            <flux:heading size="lg">{{ $record->conducted_by }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Location') }}</flux:text>
            <flux:heading size="lg">{{ $record->location ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('CPD units') }}</flux:text>
            <flux:heading size="lg">{{ $record->cpd_units ?? '—' }}</flux:heading>
        </div>
    </flux:card>

    <flux:card class="grid gap-4 md:grid-cols-4">
        <div>
            <flux:text size="sm">{{ __('Registration fee') }}</flux:text>
            <flux:heading size="lg">{{ $record->registration_fee ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Travel expenses') }}</flux:text>
            <flux:heading size="lg">{{ $record->tev ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Other expenses') }}</flux:text>
            <flux:heading size="lg">{{ $record->expenses ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Submitted by') }}</flux:text>
            <flux:heading size="lg">{{ $record->submittedBy->name }}</flux:heading>
        </div>
    </flux:card>

    @if ($record->rejection_reason)
        <flux:callout variant="danger" icon="x-circle" :heading="__('Rejected')">
            {{ $record->rejection_reason }}
        </flux:callout>
    @endif

    <flux:heading size="lg">{{ __('Approval trail') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Level') }}</flux:table.column>
            <flux:table.column>{{ __('Decision') }}</flux:table.column>
            <flux:table.column>{{ __('By') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Remarks') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($record->approvals as $approval)
                <flux:table.row :key="$approval->id">
                    <flux:table.cell>{{ $approval->level->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->decision->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->approver->name }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->decided_at->format('d M Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->remarks ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nobody has acted on this yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
