<?php

use App\Enums\LdType;
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

    public string $type_of_training = '';

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
        $this->type_of_training = (string) $plan->type_of_training;
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
            'type_of_training' => ['nullable', 'string', 'max:255'],
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

    public function resetForm(): void
    {
        $this->reset(
            'editingId', 'title', 'development_partner', 'type_of_training', 'date_start',
            'date_end', 'hours', 'ld_type', 'ld_type_other', 'location',
            'target_attendees', 'budget', 'budget_source',
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
            <flux:table.column>{{ __('Budget') }}</flux:table.column>
            <flux:table.column>{{ __('Source') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->plans as $plan)
                <flux:table.row :key="$plan->id">
                    <flux:table.cell>
                        <flux:link class="block w-56 truncate" :href="route('ldi.show', $plan)"
                            :title="$plan->title" wire:navigate>
                            {{ $plan->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-40 truncate" title="{{ $plan->development_partner }}">
                            {{ $plan->development_partner }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $plan->date_start->format('d M Y') }} – {{ $plan->date_end->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-attendee-count :actual="$plan->training_records_count" :target="$plan->target_attendees" />
                    </flux:table.cell>
                    <flux:table.cell>{{ $plan->budget === null ? '—' : number_format((float) $plan->budget, 2) }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-32 truncate" title="{{ $plan->budget_source }}">
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

    <flux:modal name="ldi-form" class="md:w-4xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add LDI training') : __('Edit LDI training') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input class="md:col-span-2" wire:model="title" :label="__('Title')" required />

                <flux:input wire:model="development_partner" :label="__('Development partner')"
                    :description="__('Who conducts or sponsors it.')" required />
                <flux:input wire:model="type_of_training" :label="__('Type of training')"
                    :placeholder="__('Training, Workshop, Seminar')" />

                <flux:input wire:model="date_start" :label="__('From')" type="date" required />
                <flux:input wire:model="date_end" :label="__('To')" type="date" required />

                <flux:input wire:model="hours" :label="__('Number of hours')" type="number" min="1" required />

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

                <flux:input wire:model="budget" :label="__('Budget')" type="number" step="0.01" min="0" />
                <flux:input wire:model="budget_source" :label="__('Budget source')"
                    :placeholder="__('WFP-GAA 2026')" />
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
