<?php

use App\Enums\EligibilityStatus;
use App\Enums\EmploymentStatus;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $employee_number = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $suffix = '';

    public string $gender = '';

    public ?int $positionId = null;

    public ?int $employeeSectionId = null;

    public string $employment_status = '';

    public string $date_hired = '';

    public bool $is_active = true;

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
            ->with(['section', 'division', 'position', 'eligibilities.eligibility'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);
    }

    /**
     * Only admin and HR get the action column at all. A division or
     * section head reads their people; they do not maintain them.
     */
    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()->isAdminOrHr();
    }

    public function createEmployee(): void
    {
        $this->authorize('create', Employee::class);

        $this->resetForm();

        Flux::modal('employee-form')->show();
    }

    public function editEmployee(int $employeeId): void
    {
        $employee = Employee::findOrFail($employeeId);

        $this->authorize('update', $employee);

        $this->resetValidation();

        $this->editingId = $employee->getKey();
        $this->employee_number = $employee->employee_number;
        $this->first_name = $employee->first_name;
        $this->middle_name = (string) $employee->middle_name;
        $this->last_name = $employee->last_name;
        $this->suffix = (string) $employee->suffix;
        $this->gender = (string) $employee->gender;
        $this->positionId = $employee->position_id;
        $this->employeeSectionId = $employee->section_id;
        $this->employment_status = $employee->employment_status->value;
        $this->date_hired = $employee->date_hired?->toDateString() ?? '';
        $this->is_active = $employee->is_active;

        Flux::modal('employee-form')->show();
    }

    public function saveEmployee(): void
    {
        $employee = $this->editingId === null ? new Employee : Employee::findOrFail($this->editingId);

        $this->authorize($this->editingId === null ? 'create' : 'update', $this->editingId === null
            ? Employee::class
            : $employee);

        $validated = $this->validate([
            'employee_number' => ['required', 'string', 'max:255', Rule::unique('employees', 'employee_number')->ignore($this->editingId)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:Male,Female'],
            'positionId' => ['nullable', 'exists:positions,id'],
            'employeeSectionId' => ['nullable', 'exists:sections,id'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'date_hired' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $employee->fill([
            'employee_number' => $validated['employee_number'],
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?: null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?: null,
            'gender' => $validated['gender'] ?: null,
            'position_id' => $validated['positionId'],
            'section_id' => $validated['employeeSectionId'],
            'employment_status' => $validated['employment_status'],
            'date_hired' => $validated['date_hired'] ?: null,
            'is_active' => $validated['is_active'],
        ])->save();

        $message = $this->editingId === null ? __('Employee added.') : __('Employee updated.');

        $this->resetForm();

        unset($this->employees);

        Flux::modal('employee-form')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    private function resetForm(): void
    {
        $this->reset(
            'editingId', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender',
            'positionId', 'employeeSectionId', 'employment_status', 'date_hired',
        );

        $this->is_active = true;
        $this->resetValidation();
    }

    public function confirmDelete(int $employeeId): void
    {
        $employee = Employee::findOrFail($employeeId);

        $this->authorize('delete', $employee);

        $this->deletingId = $employee->getKey();

        unset($this->deleting);

        Flux::modal('employee-delete')->show();
    }

    /**
     * The employee the delete modal is about.
     */
    #[Computed]
    public function deleting(): ?Employee
    {
        return $this->deletingId === null ? null : Employee::find($this->deletingId);
    }

    public function deleteEmployee(): void
    {
        $employee = Employee::findOrFail($this->deletingId);

        $this->authorize('delete', $employee);

        $employee->delete();

        $this->deletingId = null;

        unset($this->employees, $this->deleting);

        Flux::modal('employee-delete')->close();

        Flux::toast(variant: 'success', text: __('Employee removed.'));
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
     * @return Collection<int, Position>
     */
    #[Computed]
    public function positions(): Collection
    {
        return Position::query()->orderBy('title')->get();
    }

    /**
     * Every section, regardless of the list filter — the form must be able
     * to move somebody into a section the filter is hiding.
     *
     * @return Collection<int, Section>
     */
    #[Computed]
    public function allSections(): Collection
    {
        return Section::query()->orderBy('name')->get();
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
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Employees') }}</flux:heading>

        @if ($this->canManage)
            <flux:button variant="primary" wire:click="createEmployee">{{ __('Add employee') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input size="sm" class="lg:flex-1" wire:model.live.debounce.300ms="search"
            :placeholder="__('Search name or employee number')" />

        <flux:select size="sm" class="lg:w-52" wire:model.live="divisionId">
            <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
            @foreach ($this->divisions as $division)
                <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select size="sm" class="lg:w-48" wire:model.live="sectionId">
            <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
            @foreach ($this->sections as $section)
                <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select size="sm" class="lg:w-44" wire:model.live="employmentStatus">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (EmploymentStatus::cases() as $status)
                <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select size="sm" class="lg:w-52" wire:model.live="eligibilityStatus">
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
            @if ($this->canManage)
                <flux:table.column>{{ __('Action') }}</flux:table.column>
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->employees as $employee)
                <flux:table.row :key="$employee->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-56 truncate" title="{{ $employee->full_name }}">
                            <flux:link :href="route('employees.show', $employee)" wire:navigate>
                                {{ $employee->listing_name }}
                            </flux:link>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->division?->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-36 truncate" title="{{ $employee->section?->name }}">
                            {{ $employee->section?->name ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-36 truncate" title="{{ $employee->position?->title }}">
                            {{ $employee->position?->title ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->employment_status->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->cpd_units_for_year ?? 0 }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-28 truncate" title="{{ $employee->eligibilities->map->name()->join(', ') }}">
                            {{ $employee->eligibilitySummary() }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-eligibility-expiry :date="$employee->eligibilityExpiresOn()" />
                    </flux:table.cell>
                    @if ($this->canManage)
                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:button size="sm" variant="ghost"
                                    wire:click="editEmployee({{ $employee->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger"
                                    wire:click="confirmDelete({{ $employee->id }})">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell :colspan="$this->canManage ? 9 : 8">
                        {{ __('No employees found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="employee-form" class="md:w-7xl">
        <form wire:submit="saveEmployee" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add employee') : __('Edit employee') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="employee_number" :label="__('Employee no.')" required />
                <flux:input wire:model="date_hired" :label="__('Date hired')" type="date" />

                <flux:input wire:model="first_name" :label="__('First name')" required />
                <flux:input wire:model="middle_name" :label="__('Middle name')" />

                <flux:input wire:model="last_name" :label="__('Last name')" required />
                <flux:input wire:model="suffix" :label="__('Suffix')" :placeholder="__('Jr., Sr., III')" />

                <flux:select wire:model="gender" :label="__('Sex')">
                    <flux:select.option value="">{{ __('Not stated') }}</flux:select.option>
                    <flux:select.option value="Female">{{ __('Female') }}</flux:select.option>
                    <flux:select.option value="Male">{{ __('Male') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model="employeeSectionId" :label="__('Section')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->allSections as $section)
                        <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="positionId" :label="__('Position')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->positions as $position)
                        <flux:select.option :value="$position->id">{{ $position->title }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="employment_status" :label="__('Employment status')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (EmploymentStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:field variant="inline" class="self-end">
                    <flux:switch wire:model="is_active" />
                    <flux:label>{{ __('Active') }}</flux:label>
                </flux:field>
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

    <flux:modal name="employee-delete" class="md:w-5xl">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Remove this employee?') }}</flux:heading>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-3">
                    @if ($this->deleting)
                        <div>
                            <flux:text size="sm">{{ __('Name') }}</flux:text>
                            <flux:heading>{{ $this->deleting->full_name }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('Employee no.') }}</flux:text>
                            <flux:heading>{{ $this->deleting->employee_number }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('Section') }}</flux:text>
                            <flux:heading>{{ $this->deleting->section?->name ?? '—' }}</flux:heading>
                        </div>
                    @endif
                </div>

                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('The employee leaves the list but their training records and approval history are kept.') }}
                </flux:callout>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="deleteEmployee" variant="danger">{{ __('Remove') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
