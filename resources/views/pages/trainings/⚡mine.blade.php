<?php

use App\Actions\Training\DeleteTrainingRecord;
use App\Models\TrainingRecord;
use Flux\Flux;
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

    public ?int $deletingId = null;

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

    public function confirmDelete(int $recordId): void
    {
        $record = TrainingRecord::findOrFail($recordId);

        $this->authorize('delete', $record);

        $this->deletingId = $record->getKey();

        unset($this->deleting);

        Flux::modal('delete-training')->show();
    }

    #[Computed]
    public function deleting(): ?TrainingRecord
    {
        return $this->deletingId === null ? null : TrainingRecord::find($this->deletingId);
    }

    public function deleteRecord(DeleteTrainingRecord $delete): void
    {
        $record = TrainingRecord::findOrFail($this->deletingId);

        // Asked again here, not only on opening: an approver may have
        // decided on it while the modal sat open.
        $this->authorize('delete', $record);

        $delete->handle($record);

        $this->deletingId = null;

        unset($this->records, $this->deleting);

        Flux::modal('delete-training')->close();

        Flux::toast(variant: 'success', text: __('Training deleted.'));
    }
}; ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <x-page-heading icon="academic-cap">{{ __('My trainings') }}</x-page-heading>

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
                        {{-- A fixed width, not w-full: a table sizes a cell to
                             its content, so a long title would otherwise take
                             the row and push the buttons off the side. The
                             whole title is on hover and in the detail modal. --}}
                        <div class="w-56 truncate xl:w-72 2xl:w-96" title="{{ $record->title }}">
                            <button type="button" class="block w-full cursor-pointer truncate text-left text-[var(--color-accent-content)] hover:opacity-70" wire:click="$dispatch('show-training', { recordId: {{ $record->id }} })">
                                {{ $record->title }}
                            </button>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->inclusive_dates }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>
                        {{-- "Other" carries whatever the person wrote in. --}}
                        <div class="w-28 truncate" title="{{ $record->ld_type_label }}">{{ $record->ld_type_label }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-training-status :record="$record" />
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('update', $record)
                            <div class="flex gap-1">
                                <flux:button size="sm" variant="ghost"
                                    wire:click="$dispatch('edit-training', { recordId: {{ $record->id }} })">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $record->id }})">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
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

    <flux:modal name="delete-training" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete this training?') }}</flux:heading>

            @if ($this->deleting)
                <div class="space-y-1">
                    <flux:heading>{{ $this->deleting->title }}</flux:heading>
                    <flux:text>{{ $this->deleting->inclusive_dates }}</flux:text>
                </div>
            @endif

            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __('It is taken out of the approval queue and deleted for good. Nobody has decided on it yet, so no approval is lost.') }}
            </flux:callout>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="deleteRecord" variant="danger">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <livewire:pages::trainings.form-modal />

    <livewire:pages::trainings.detail-modal />
</div>
