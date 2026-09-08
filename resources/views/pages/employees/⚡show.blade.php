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
            <flux:table.column class="text-right">{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Conducted by') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->records as $record)
                <flux:table.row :key="$record->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-72 truncate" title="{{ $record->title }}">
                            <flux:link as="button" wire:click="$dispatch('show-training', { recordId: {{ $record->id }} })">
                                {{ $record->title }}
                            </flux:link>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $record->inclusive_dates }}
                    </flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-28 truncate" title="{{ $record->ld_type_label }}">
                            {{ $record->ld_type_label }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-48 truncate" title="{{ $record->conducted_by }}">
                            {{ $record->conducted_by }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell><x-training-status :record="$record" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No training on record.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <livewire:pages::trainings.detail-modal />
</div>
