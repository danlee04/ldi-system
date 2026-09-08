<?php

use App\Enums\LdType;
use App\Models\BudgetCap;
use App\Models\LdiTraining;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('LDI trainings')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $filterYear = null;

    public ?int $editingId = null;

    public string $title = '';

    public string $development_partner = '';

    public string $facilitator = '';

    public string $type_of_training = '';

    public string $training_communication = '';

    public ?float $cpd_units = null;

    public string $date_start = '';

    public string $date_end = '';

    public ?int $hours = null;

    public string $ld_type = '';

    public string $ld_type_other = '';

    public string $location = '';

    public ?int $target_attendees = null;

    public ?float $budget = null;

    public string $budget_source = '';

    public function mount(): void
    {
        $this->authorize('viewAny', LdiTraining::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYear(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, LdiTraining>
     */
    #[Computed]
    public function plans(): LengthAwarePaginator
    {
        return LdiTraining::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $match) => $match->where('title', 'like', $term)
                    ->orWhere('development_partner', 'like', $term)
                    ->orWhere('budget_source', 'like', $term));
            })
            ->when($this->filterYear !== null, fn (Builder $query) => $query->whereYear('date_end', $this->filterYear))
            ->withCount('trainingRecords')
            ->orderByDesc('date_start')
            ->paginate(15);
    }

    /**
     * Only the years that have a plan, so the filter never comes up empty.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function years(): Collection
    {
        return LdiTraining::query()
            ->pluck('date_end')
            ->map(fn (CarbonImmutable $date): int => (int) $date->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();
    }

    public function create(): void
    {
        $this->authorize('create', LdiTraining::class);

        $this->resetForm();

        Flux::modal('ldi-form')->show();
    }

    public function edit(int $planId): void
    {
        $plan = LdiTraining::findOrFail($planId);

        $this->authorize('update', $plan);

        $this->resetValidation();

        $this->editingId = $plan->getKey();
        $this->title = $plan->title;
        $this->development_partner = $plan->development_partner;
        $this->facilitator = $plan->facilitator;
        $this->type_of_training = (string) $plan->type_of_training;
        $this->training_communication = (string) $plan->training_communication;
        $this->cpd_units = $plan->cpd_units;
        $this->date_start = $plan->date_start->toDateString();
        $this->date_end = $plan->date_end->toDateString();
        $this->hours = $plan->hours;
        $this->ld_type = $plan->ld_type->value;
        $this->ld_type_other = (string) $plan->ld_type_other;
        $this->location = (string) $plan->location;
        $this->target_attendees = $plan->target_attendees;
        $this->budget = $plan->budget === null ? null : (float) $plan->budget;
        $this->budget_source = (string) $plan->budget_source;

        Flux::modal('ldi-form')->show();
    }

    public function save(): void
    {
        $this->authorize($this->editingId === null ? 'create' : 'update', $this->editingId === null
            ? LdiTraining::class
            : LdiTraining::findOrFail($this->editingId));

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'development_partner' => ['required', 'string', 'max:255'],
            'facilitator' => ['required', 'string', 'max:255'],
            'type_of_training' => ['nullable', 'string', 'max:255'],
            'training_communication' => ['nullable', 'string', 'max:255'],
            'cpd_units' => ['nullable', 'numeric', 'min:0'],
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'hours' => ['required', 'integer', 'min:1', 'max:9999'],
            'ld_type' => ['required', Rule::enum(LdType::class)],
            'ld_type_other' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->ld_type === LdType::Other->value)],
            'location' => ['nullable', 'string', 'max:255'],
            'target_attendees' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'budget_source' => ['nullable', 'string', 'max:255'],
        ]);

        LdiTraining::updateOrCreate(['id' => $this->editingId], [
            ...$validated,
            'type_of_training' => $validated['type_of_training'] ?: null,
            'training_communication' => $validated['training_communication'] ?: null,
            'ld_type_other' => $this->ld_type === LdType::Other->value ? $validated['ld_type_other'] : null,
            'location' => $validated['location'] ?: null,
            'budget_source' => $validated['budget_source'] ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->resetForm();

        unset($this->plans, $this->years);

        Flux::modal('ldi-form')->close();

        Flux::toast(variant: 'success', text: __('LDI training saved.'));
    }

    /**
     * How much of this source's yearly cap is left, once this plan's own
     * budget is set aside. Null when no cap covers the source and year.
     *
     * Going over never blocks the save — the commitment was made outside
     * this system and HR still has to record it.
     */
    #[Computed]
    public function budgetHint(): ?string
    {
        $cap = BudgetCap::forSourceAndYear(
            $this->budget_source ?: null,
            $this->date_start !== '' ? (int) substr($this->date_start, 0, 4) : null,
        );

        if ($cap === null) {
            return null;
        }

        $committedElsewhere = $cap->committed() - (float) LdiTraining::query()
            ->whereKey($this->editingId)
            ->sum('budget');

        $left = (float) $cap->amount - $committedElsewhere - (float) $this->budget;

        if ($left < 0) {
            return __('Over the :year :source cap by :amount.', [
                'year' => $cap->year,
                'source' => $cap->budget_source,
                'amount' => number_format(abs($left), 2),
            ]);
        }

        return __(':amount left of the :year :source cap.', [
            'amount' => number_format($left, 2),
            'year' => $cap->year,
            'source' => $cap->budget_source,
        ]);
    }

    public function resetForm(): void
    {
        $this->reset(
            'editingId', 'title', 'development_partner', 'facilitator', 'type_of_training',
            'training_communication', 'date_start', 'date_end', 'hours', 'cpd_units',
            'ld_type', 'ld_type_other', 'location', 'target_attendees', 'budget', 'budget_source',
        );
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('LDI trainings') }}</flux:heading>

        <flux:button variant="primary" wire:click="create">{{ __('Add LDI training') }}</flux:button>
    </div>

    <flux:callout icon="information-circle">
        {{ __('These are the trainings the agency planned and funded. Attendance recorded here needs no approval. A training an employee found on their own is submitted from My trainings instead.') }}
    </flux:callout>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input size="sm" class="lg:flex-1" wire:model.live.debounce.300ms="search"
            :placeholder="__('Search title, partner or budget source')" />

        <flux:select size="sm" class="lg:w-40" wire:model.live="filterYear">
            <flux:select.option value="">{{ __('All years') }}</flux:select.option>
            @foreach ($this->years as $year)
                <flux:select.option :value="$year">{{ $year }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->plans">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Development partner') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Attendees') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Budget') }}</flux:table.column>
            <flux:table.column>{{ __('Source') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->plans as $plan)
                <flux:table.row :key="$plan->id">
                    <flux:table.cell>
                        {{-- The width and truncation must live on a wrapper: flux:link is
                             always `inline`, and an inline element ignores both. --}}
                        <div class="w-72 truncate" title="{{ $plan->title }}">
                            <flux:link :href="route('ldi.show', $plan)" wire:navigate>
                                {{ $plan->title }}
                            </flux:link>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-48 truncate" title="{{ $plan->development_partner }}">
                            {{ $plan->development_partner }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $plan->inclusive_dates }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-attendee-count :actual="$plan->training_records_count" :target="$plan->target_attendees" />
                    </flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">
                        {{ $plan->budget === null ? '—' : number_format((float) $plan->budget, 2) }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-36 truncate" title="{{ $plan->budget_source }}">
                            {{ $plan->budget_source ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $plan->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">{{ __('No LDI trainings yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="ldi-form" class="md:w-7xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add LDI training') : __('Edit LDI training') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input class="md:col-span-2" wire:model="title" :label="__('Title')" required />

                <x-picklist-input wire:model="development_partner" :label="__('Development partner')"
                    :options="config('ldi.development_partners')" required />

                <x-picklist-input wire:model="facilitator" :label="__('Conducted or sponsored by')"
                    :options="config('ldi.facilitators')" required />

                <x-picklist-input wire:model="type_of_training" :label="__('Type of training')"
                    :options="config('ldi.training_types')" />

                <flux:select wire:model="training_communication" :label="__('Training communication')">
                    <flux:select.option value="">{{ __('Not stated') }}</flux:select.option>
                    @foreach (config('ldi.training_communications') as $option)
                        <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live="date_start" :label="__('From')" type="date" required />
                <flux:input wire:model="date_end" :label="__('To')" type="date" required />

                <flux:input wire:model="hours" :label="__('Number of hours')" type="number" min="1" required />
                <flux:input wire:model="cpd_units" :label="__('CPD units')" type="number" step="0.1" min="0" />

                <flux:select wire:model.live="ld_type" :label="__('Type of LD')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (LdType::cases() as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($ld_type === LdType::Other->value)
                    <flux:input class="md:col-span-2" wire:model="ld_type_other" :label="__('Specify the type')" required />
                @endif

                <flux:input wire:model="location" :label="__('Location')" />
                <flux:input wire:model="target_attendees" :label="__('Target attendees')" type="number" min="1" />

                <div class="md:col-span-2">
                    <flux:separator :text="__('Budget')" />
                </div>

                <flux:input wire:model.live.debounce.400ms="budget" :label="__('Budget')"
                    type="number" step="0.01" min="0" />

                <x-picklist-input wire:model.live="budget_source" :label="__('Budget source')"
                    :options="config('ldi.budget_sources')" />

                @if ($this->budgetHint)
                    <div class="md:col-span-2">
                        <flux:callout :variant="str_contains($this->budgetHint, __('Over')) ? 'warning' : 'secondary'"
                            icon="banknotes">
                            {{ $this->budgetHint }}
                        </flux:callout>
                    </div>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">
                    {{ $editingId === null ? __('Add') : __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
