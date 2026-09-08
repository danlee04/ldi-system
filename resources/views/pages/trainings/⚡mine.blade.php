<?php

use App\Models\TrainingRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My trainings')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, TrainingRecord>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return TrainingRecord::query()
            ->where('employee_id', auth()->user()->employee?->getKey())
            ->orderByDesc('date_end')
            ->paginate(20);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('My trainings') }}</flux:heading>

        <flux:button :href="route('trainings.create')" variant="primary" wire:navigate>
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
                    <flux:table.cell>
                        <x-training-status :record="$record" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No trainings recorded yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
