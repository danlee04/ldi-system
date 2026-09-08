<?php

use App\Models\Employee;
use App\Models\TrainingRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Employee')] class extends Component {
    public Employee $employee;

    public function mount(Employee $employee): void
    {
        $this->authorize('view', $employee);

        $this->employee = $employee->load(['section.division', 'position']);
    }

    /**
     * The whole training history, newest first. This is what PDS page 4
     * will be built from in Phase 4.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function records(): Collection
    {
        return $this->employee->trainingRecords()->orderByDesc('date_end')->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ $employee->full_name }}</flux:heading>
        <flux:text>
            {{ $employee->position?->title ?? '—' }} · {{ $employee->section?->name ?? '—' }}
        </flux:text>
    </div>

    <flux:card class="grid gap-4 md:grid-cols-4">
        <div>
            <flux:text size="sm">{{ __('Employee no.') }}</flux:text>
            <flux:heading size="lg">{{ $employee->employee_number }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Division') }}</flux:text>
            <flux:heading size="lg">{{ $employee->section?->division?->name ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Employment status') }}</flux:text>
            <flux:heading size="lg">{{ $employee->employment_status->label() }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Date hired') }}</flux:text>
            <flux:heading size="lg">{{ $employee->date_hired?->format('d M Y') ?? '—' }}</flux:heading>
        </div>
    </flux:card>

    <flux:heading size="lg">{{ __('Training history') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Conducted by') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->records as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $record)" wire:navigate>
                            {{ $record->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>{{ $record->ld_type_label }}</flux:table.cell>
                    <flux:table.cell>{{ $record->conducted_by }}</flux:table.cell>
                    <flux:table.cell><x-training-status :record="$record" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No training on record.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
