<?php

use App\Enums\EligibilityStatus;
use App\Enums\EmploymentStatus;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Employees')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $divisionId = null;

    #[Url]
    public ?int $sectionId = null;

    #[Url]
    public string $employmentStatus = '';

    #[Url]
    public string $eligibilityStatus = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Choosing a division discards a section from another one, otherwise
     * the two filters would silently contradict each other.
     */
    public function updatedDivisionId(): void
    {
        if ($this->sectionId !== null && ! $this->sections->contains('id', $this->sectionId)) {
            $this->sectionId = null;
        }
    }

    /**
     * The year the CPD column reports on.
     */
    #[Computed]
    public function year(): int
    {
        return now()->year;
    }

    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    #[Computed]
    public function employees(): LengthAwarePaginator
    {
        return Employee::query()
            ->visibleTo(auth()->user())
            ->active()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $match) use ($term): void {
                    $match->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('employee_number', 'like', $term);
                });
            })
            ->when($this->divisionId !== null, fn (Builder $query) => $query->where('division_id', $this->divisionId))
            ->when($this->sectionId !== null, fn (Builder $query) => $query->where('section_id', $this->sectionId))
            ->when($this->employmentStatus !== '', fn (Builder $query) => $query->where('employment_status', $this->employmentStatus))
            ->when(
                $this->eligibilityStatus !== '',
                fn (Builder $query) => $query->eligibilityStatus(EligibilityStatus::from($this->eligibilityStatus)),
            )
            ->withSum(
                ['trainingRecords as cpd_units_for_year' => fn ($query) => $query
                    ->where('status', TrainingStatus::Approved)
                    ->whereYear('date_end', $this->year)],
                'cpd_units',
            )
            ->with(['section', 'division', 'position', 'eligibility'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed]
    public function divisions(): Collection
    {
        return Division::query()->orderBy('name')->get();
    }

    /**
     * Narrowed to the chosen division so the two filters agree.
     *
     * @return Collection<int, Section>
     */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()
            ->when($this->divisionId !== null, fn (Builder $query) => $query->where('division_id', $this->divisionId))
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Employees') }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
        <flux:input wire:model.live.debounce.300ms="search"
            :placeholder="__('Search name or employee number')" />

        <flux:select wire:model.live="divisionId" :placeholder="__('All divisions')">
            <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
            @foreach ($this->divisions as $division)
                <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="sectionId">
            <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
            @foreach ($this->sections as $section)
                <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="employmentStatus">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (EmploymentStatus::cases() as $status)
                <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="eligibilityStatus">
            <flux:select.option value="">{{ __('All eligibility') }}</flux:select.option>
            @foreach (EligibilityStatus::cases() as $status)
                <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->employees">
        <flux:table.columns>
            <flux:table.column>{{ __('Full Name') }}</flux:table.column>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Position') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('CPD Units (:year)', ['year' => $this->year]) }}</flux:table.column>
            <flux:table.column>{{ __('Eligibility') }}</flux:table.column>
            <flux:table.column>{{ __('Eligibility Expiry') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->employees as $employee)
                <flux:table.row :key="$employee->id">
                    <flux:table.cell>
                        <flux:link :href="route('employees.show', $employee)" wire:navigate>
                            {{ $employee->full_name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->division?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->section?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->position?->title ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->employment_status->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->cpd_units_for_year ?? 0 }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $employee->eligibility_detail ?: ($employee->eligibility?->name ?? '—') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-eligibility-expiry :date="$employee->eligibility_expires_on" />
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost"
                            :href="route('employees.show', $employee)" wire:navigate>
                            {{ __('View') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="9">{{ __('No employees found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
