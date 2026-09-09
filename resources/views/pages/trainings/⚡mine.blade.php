<?php

use App\Models\TrainingRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * An employee's own training records.
 *
 * The form itself lives in pages::trainings.form-modal — HR reaches the
 * same form from an employee's profile, and one form means one set of
 * fields and one set of rules.
 */
new #[Title('My trainings')] class extends Component {
    use WithPagination;

    /**
     * Approvals are eager loaded because the policy asks whether the
     * record has been acted on for every row.
     *
     * @return LengthAwarePaginator<int, TrainingRecord>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return TrainingRecord::query()
            ->where('employee_id', auth()->user()->employee?->getKey())
            ->with('approvals')
            ->orderByDesc('date_end')
            ->paginate(20);
    }

    #[On('training-saved')]
    public function refresh(): void
    {
        unset($this->records);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('My trainings') }}</flux:heading>

        <flux:button variant="primary" wire:click="$dispatch('add-training')">
            {{ __('Record a training') }}
        </flux:button>
    </div>

    @if (auth()->user()->employee === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Your account is not linked to an employee record yet. Ask HR to link it before recording a training.') }}
        </flux:callout>
    @endif

    <flux:table :paginate="$this->records">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->records as $record)
                <flux:table.row :key="$record->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <button type="button" class="block w-full cursor-pointer truncate text-left text-[var(--color-accent-content)] hover:opacity-70" wire:click="$dispatch('show-training', { recordId: {{ $record->id }} })">
                            {{ $record->title }}
                        </button>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->inclusive_dates }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>{{ $record->ld_type_label }}</flux:table.cell>
                    <flux:table.cell>
                        <x-training-status :record="$record" />
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $record)
                            <flux:button size="sm" variant="ghost"
                                wire:click="$dispatch('edit-training', { recordId: {{ $record->id }} })">
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No trainings recorded yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <livewire:pages::trainings.form-modal />

    <livewire:pages::trainings.detail-modal />
</div>
