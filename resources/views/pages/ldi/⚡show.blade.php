<?php

use App\Actions\Training\AddLdiAttendees;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\TrainingRecord;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('LDI training')] class extends Component {
    public LdiTraining $plan;

    /** @var array<int, int> */
    public array $selected = [];

    public string $employeeSearch = '';

    public ?int $costRecordId = null;

    public ?float $registration_fee = null;

    public ?float $tev = null;

    public ?float $expenses = null;

    public ?float $cpd_units = null;

    public ?int $removingId = null;

    public function mount(LdiTraining $plan): void
    {
        $this->authorize('view', $plan);

        $this->plan = $plan;
    }

    /**
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function attendees(): Collection
    {
        return $this->plan->trainingRecords()
            ->with('employee.section')
            ->get()
            ->sortBy(fn (TrainingRecord $record): string => $record->employee->last_name)
            ->values();
    }

    #[Computed]
    public function actualSpend(): float
    {
        return $this->attendees->sum(fn (TrainingRecord $record): float => (float) $record->registration_fee
            + (float) $record->tev
            + (float) $record->expenses);
    }

    /**
     * Employees who are not on this plan yet.
     *
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function candidates(): Collection
    {
        return Employee::query()
            ->active()
            ->whereNotIn('id', $this->plan->trainingRecords()->pluck('employee_id'))
            ->when($this->employeeSearch !== '', function (Builder $query): void {
                $term = '%'.$this->employeeSearch.'%';

                $query->where(fn (Builder $match) => $match->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_number', 'like', $term));
            })
            ->with('section')
            ->orderBy('last_name')
            ->limit(50)
            ->get();
    }

    public function openAttendees(): void
    {
        $this->authorize('update', $this->plan);

        $this->reset('selected', 'employeeSearch');

        Flux::modal('add-attendees')->show();
    }

    public function addAttendees(): void
    {
        $this->authorize('update', $this->plan);

        $added = app(AddLdiAttendees::class)->handle(
            $this->plan,
            array_map('intval', $this->selected),
            auth()->user(),
        );

        $this->reset('selected', 'employeeSearch');

        unset($this->attendees, $this->candidates, $this->actualSpend);

        Flux::modal('add-attendees')->close();

        Flux::toast(
            variant: $added > 0 ? 'success' : 'warning',
            text: $added > 0
                ? trans_choice(':count attendee recorded.|:count attendees recorded.', $added, ['count' => $added])
                : __('Nobody new was added.'),
        );
    }

    public function editCost(int $recordId): void
    {
        $this->authorize('update', $this->plan);

        $record = $this->plan->trainingRecords()->findOrFail($recordId);

        $this->resetValidation();

        $this->costRecordId = $record->getKey();
        $this->registration_fee = $record->registration_fee === null ? null : (float) $record->registration_fee;
        $this->tev = $record->tev === null ? null : (float) $record->tev;
        $this->expenses = $record->expenses === null ? null : (float) $record->expenses;
        $this->cpd_units = $record->cpd_units;

        Flux::modal('attendee-cost')->show();
    }

    public function saveCost(): void
    {
        $this->authorize('update', $this->plan);

        $validated = $this->validate([
            'registration_fee' => ['nullable', 'numeric', 'min:0'],
            'tev' => ['nullable', 'numeric', 'min:0'],
            'expenses' => ['nullable', 'numeric', 'min:0'],
            'cpd_units' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->plan->trainingRecords()->findOrFail($this->costRecordId)->update($validated);

        $this->reset('costRecordId', 'registration_fee', 'tev', 'expenses', 'cpd_units');

        unset($this->attendees, $this->actualSpend);

        Flux::modal('attendee-cost')->close();

        Flux::toast(variant: 'success', text: __('Costs updated.'));
    }

    public function confirmRemove(int $recordId): void
    {
        $this->authorize('update', $this->plan);

        $this->removingId = $this->plan->trainingRecords()->findOrFail($recordId)->getKey();

        unset($this->removing);

        Flux::modal('remove-attendee')->show();
    }

    #[Computed]
    public function removing(): ?TrainingRecord
    {
        return $this->removingId === null
            ? null
            : $this->plan->trainingRecords()->with('employee')->find($this->removingId);
    }

    public function removeAttendee(): void
    {
        $this->authorize('update', $this->plan);

        $this->plan->trainingRecords()->findOrFail($this->removingId)->delete();

        $this->removingId = null;

        unset($this->attendees, $this->candidates, $this->actualSpend, $this->removing);

        Flux::modal('remove-attendee')->close();

        Flux::toast(variant: 'success', text: __('Attendee removed.'));
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-3">
        <flux:button size="sm" variant="ghost" icon="chevron-left"
            :href="route('ldi.index')" wire:navigate>
            {{ __('LDI trainings') }}
        </flux:button>

        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $plan->title }}</flux:heading>
                <flux:text>
                    {{ $plan->development_partner }}
                    @if ($plan->type_of_training)
                        — {{ $plan->type_of_training }}
                    @endif
                </flux:text>
            </div>

            <flux:button variant="primary" wire:click="openAttendees">{{ __('Add attendees') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:card>
            <flux:text size="sm">{{ __('Attendees') }}</flux:text>
            <flux:heading size="xl">
                {{ $this->attendees->count() }}
                @if ($plan->target_attendees)
                    <span class="text-zinc-400">/ {{ $plan->target_attendees }}</span>
                @endif
            </flux:heading>
            @if ($plan->target_attendees && $this->attendees->count() < $plan->target_attendees)
                <flux:text size="sm">
                    {{ __(':count short of target', ['count' => $plan->target_attendees - $this->attendees->count()]) }}
                </flux:text>
            @endif
        </flux:card>

        <flux:card>
            <flux:text size="sm">{{ __('Spent against budget') }}</flux:text>
            <flux:heading size="xl">
                {{ number_format($this->actualSpend, 2) }}
                @if ($plan->budget !== null)
                    <span class="text-zinc-400">/ {{ number_format((float) $plan->budget, 2) }}</span>
                @endif
            </flux:heading>
            @if ($plan->budget !== null)
                <flux:text size="sm">
                    @if ($this->actualSpend > (float) $plan->budget)
                        {{ __('Over by :amount', ['amount' => number_format($this->actualSpend - (float) $plan->budget, 2)]) }}
                    @else
                        {{ __(':amount left', ['amount' => number_format((float) $plan->budget - $this->actualSpend, 2)]) }}
                    @endif
                    @if ($plan->budget_source)
                        · {{ $plan->budget_source }}
                    @endif
                </flux:text>
            @endif
        </flux:card>
    </div>

    <flux:card class="grid gap-4 md:grid-cols-4">
        <div>
            <flux:text size="sm">{{ __('Inclusive dates') }}</flux:text>
            <flux:heading size="lg">
                {{ $plan->inclusive_dates }}
            </flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Number of hours') }}</flux:text>
            <flux:heading size="lg">{{ $plan->hours }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Type of LD') }}</flux:text>
            <flux:heading size="lg">{{ $plan->ld_type_label }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Location') }}</flux:text>
            <flux:heading size="lg">{{ $plan->location ?? '—' }}</flux:heading>
        </div>
    </flux:card>

    <flux:heading size="lg">{{ __('Attendees') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Registration') }}</flux:table.column>
            <flux:table.column>{{ __('Travel') }}</flux:table.column>
            <flux:table.column>{{ __('Other') }}</flux:table.column>
            <flux:table.column>{{ __('CPD') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->attendees as $record)
                <flux:table.row :key="$record->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-52 truncate" title="{{ $record->employee->full_name }}">
                            <button type="button" class="block w-full cursor-pointer truncate text-left text-[var(--color-accent-content)] hover:opacity-70" wire:click="$dispatch('show-training', { recordId: {{ $record->id }} })">
                                {{ $record->employee->full_name }}
                            </button>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-36 truncate" title="{{ $record->employee->section?->name }}">
                            {{ $record->employee->section?->name ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->registration_fee ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $record->tev ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $record->expenses ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $record->cpd_units ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:button size="sm" variant="ghost" wire:click="editCost({{ $record->id }})">
                                {{ __('Costs') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="confirmRemove({{ $record->id }})">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('Nobody has been recorded as attending yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="add-attendees" class="md:w-5xl">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Add attendees') }}</flux:heading>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-3">
                    <flux:text size="sm">
                        {{ __('Everyone picked here gets an approved training record for this plan.') }}
                    </flux:text>

                    @if ($plan->target_attendees)
                        <flux:text size="sm">
                            {{ __('Target is :target; :count recorded so far.', [
                                'target' => $plan->target_attendees,
                                'count' => $this->attendees->count(),
                            ]) }}
                        </flux:text>
                    @endif

                    <flux:input size="sm" wire:model.live.debounce.300ms="employeeSearch"
                        :placeholder="__('Search name or number')" />
                </div>

                <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                    @forelse ($this->candidates as $employee)
                        <flux:checkbox wire:model="selected" :value="$employee->id"
                            :label="$employee->full_name"
                            :description="$employee->section?->name ?? $employee->employee_number" />
                    @empty
                        <flux:text size="sm">{{ __('Nobody left to add.') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="addAttendees" variant="primary">{{ __('Add selected') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="attendee-cost" class="md:w-5xl">
        <form wire:submit="saveCost" class="space-y-6">
            <flux:heading size="lg">{{ __('Costs for this attendee') }}</flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="registration_fee" :label="__('Registration fee')" type="number" step="0.01" min="0" />
                <flux:input wire:model="tev" :label="__('Travel expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="expenses" :label="__('Other expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="cpd_units" :label="__('CPD units')" type="number" step="0.1" min="0" />
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="remove-attendee" class="md:w-5xl">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Remove this attendee?') }}</flux:heading>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-3">
                    @if ($this->removing)
                        <div>
                            <flux:text size="sm">{{ __('Name') }}</flux:text>
                            <flux:heading>{{ $this->removing->employee->full_name }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('Section') }}</flux:text>
                            <flux:heading>{{ $this->removing->employee->section?->name ?? '—' }}</flux:heading>
                        </div>
                    @endif
                </div>

                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('Their training record for this plan is deleted, and it leaves their training history.') }}
                </flux:callout>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="removeAttendee" variant="danger">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <livewire:pages::trainings.detail-modal />
</div>
