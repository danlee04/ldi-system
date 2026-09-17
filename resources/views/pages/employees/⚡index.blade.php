<?php

use App\Actions\Employees\ExportEmployeeRoster;
use App\Actions\Employees\SaveEmployee;
use App\Enums\EligibilityStatus;
use App\Enums\EmploymentStatus;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Eligibility;
use App\Models\Employee;
use App\Models\EmployeeEligibility;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $suffix = '';

    public string $gender = '';

    public ?int $positionId = null;

    /** The plantilla item, kept per person: two people can hold the same position under different items. */
    public string $item_number = '';

    /** Form only — the section is what is stored, and the division follows it. */
    public ?int $employeeDivisionId = null;

    public ?int $employeeSectionId = null;

    public string $employment_status = '';

    public ?int $eligibilityId = null;

    public string $eligibilityExpiresOn = '';

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
     * The same rule inside the form: a section from another division must
     * not survive the division being changed under it.
     */
    public function updatedEmployeeDivisionId(): void
    {
        unset($this->formSections);

        if ($this->employeeSectionId !== null && ! $this->formSections->contains('id', $this->employeeSectionId)) {
            $this->employeeSectionId = null;
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
        return $this->roster()->paginate(25);
    }

    /**
     * Hands the list on screen over as a spreadsheet. The same query is
     * behind both, so the file is always what the filters were showing
     * rather than a second, differently shaped roster.
     */
    public function exportCsv(ExportEmployeeRoster $export): StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);

        return $export->handle($this->roster(), $this->year);
    }

    /**
     * The roster as the filters leave it, scoped to the people the
     * signed-in user is allowed to see.
     *
     * @return Builder<Employee>
     */
    private function roster(): Builder
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
            ->orderBy('first_name');
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
        $this->first_name = $employee->first_name;
        $this->middle_name = (string) $employee->middle_name;
        $this->last_name = $employee->last_name;
        $this->suffix = (string) $employee->suffix;
        $this->gender = (string) $employee->gender;
        $this->positionId = $employee->position_id;
        $this->item_number = (string) $employee->item_number;
        $this->employeeDivisionId = $employee->division_id;
        $this->employeeSectionId = $employee->section_id;
        $this->employment_status = $employee->employment_status->value;

        // The first line only: the form carries one eligibility, and that
        // is the one the roster's own column reports.
        $eligibility = $employee->eligibilities()->oldest('id')->first();

        $this->eligibilityId = $eligibility?->eligibility_id;
        $this->eligibilityExpiresOn = $eligibility?->date_of_validity?->toDateString() ?? '';

        unset($this->formSections);

        Flux::modal('employee-form')->show();
    }

    public function saveEmployee(SaveEmployee $save): void
    {
        $employee = $this->editingId === null ? null : Employee::findOrFail($this->editingId);

        $this->authorize($employee === null ? 'create' : 'update', $employee ?? Employee::class);

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:Male,Female'],
            'positionId' => ['nullable', 'exists:positions,id'],
            'item_number' => ['nullable', 'string', 'max:50'],
            'employeeDivisionId' => ['nullable', 'exists:divisions,id'],
            'employeeSectionId' => ['nullable', 'exists:sections,id'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'eligibilityId' => ['nullable', 'exists:eligibilities,id'],
            'eligibilityExpiresOn' => ['nullable', 'date'],
        ], attributes: [
            'employeeDivisionId' => __('division'),
            'employeeSectionId' => __('section'),
            'eligibilityId' => __('eligibility'),
            'eligibilityExpiresOn' => __('expiry date'),
        ]);

        $save->handle($employee, $validated);

        $message = $this->editingId === null ? __('Employee added.') : __('Employee updated.');

        $this->resetForm();

        unset($this->employees);

        Flux::modal('employee-form')->close();

        Flux::toast(variant: 'success', text: $message);
    }

    private function resetForm(): void
    {
        $this->reset(
            'editingId', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender',
            'positionId', 'item_number', 'employeeDivisionId', 'employeeSectionId',
            'employment_status', 'eligibilityId', 'eligibilityExpiresOn',
        );

        unset($this->formSections);

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

        // A soft delete: their training records and their data sheet are
        // set to cascade, so taking the row out for real would take the
        // Center's own accomplishment figures with it.
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

    /**
     * The sections the form offers, narrowed by the division chosen in it.
     *
     * @return Collection<int, Section>
     */
    #[Computed]
    public function formSections(): Collection
    {
        return Section::query()
            ->when($this->employeeDivisionId !== null, fn (Builder $query) => $query->where('division_id', $this->employeeDivisionId))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Eligibility>
     */
    #[Computed(persist: true)]
    public function eligibilityList(): Collection
    {
        return Eligibility::query()->orderBy('name')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <x-page-heading icon="users">{{ __('Employees') }}</x-page-heading>

        <div class="flex items-center gap-3">
            {{-- Offered to everybody who can read the roster, not only to
                 HR: a head downloading their own section is the whole
                 point, and the query behind it is scoped to them. --}}
            <flux:button icon="arrow-down-tray" wire:click="exportCsv">{{ __('Download CSV') }}</flux:button>

            @if ($this->canManage)
                <flux:button variant="primary" wire:click="createEmployee">{{ __('Add employee') }}</flux:button>
            @endif
        </div>
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
            <flux:table.column>{{ __('CPD :year', ['year' => $this->year]) }}</flux:table.column>
            <flux:table.column>{{ __('Eligibility') }}</flux:table.column>
            <flux:table.column>{{ __('Expires') }}</flux:table.column>
            @if ($this->canManage)
                <flux:table.column>{{ __('Action') }}</flux:table.column>
            @endif
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->employees as $employee)
                <flux:table.row :key="$employee->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-48 truncate" title="{{ $employee->full_name }}">
                            <flux:link :href="route('employees.show', $employee)" wire:navigate>
                                {{ $employee->listing_name }}
                            </flux:link>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->division?->code ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-32 truncate" title="{{ $employee->section?->name }}">
                            {{ $employee->section?->name ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-32 truncate" title="{{ $employee->position?->title }}">
                            {{ $employee->position?->title ?? '—' }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->employment_status->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->cpd_units_for_year ?? 0 }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-24 truncate" title="{{ $employee->eligibilities->map->name()->join(', ') }}">
                            {{ $employee->eligibilitySummary() }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <x-eligibility-expiry :date="$employee->eligibilityExpiresOn()" />
                    </flux:table.cell>
                    @if ($this->canManage)
                        <flux:table.cell>
                            {{-- Icons rather than words: two labelled buttons
                                 in the ninth column were what pushed the table
                                 past the edge of the screen. Each still says
                                 what it does, to a pointer and a screen reader. --}}
                            <div class="flex gap-1">
                                <flux:tooltip :content="__('Edit')">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" square
                                        :aria-label="__('Edit :name', ['name' => $employee->listing_name])"
                                        wire:click="editEmployee({{ $employee->id }})" />
                                </flux:tooltip>

                                <flux:tooltip :content="__('Delete')">
                                    <flux:button size="sm" variant="danger" icon="trash" square
                                        :aria-label="__('Delete :name', ['name' => $employee->listing_name])"
                                        wire:click="confirmDelete({{ $employee->id }})" />
                                </flux:tooltip>
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

    {{-- One column, with only the short paired fields side by side. Both
         width classes are needed: Flux puts a zero-specificity max-w-xl on
         the same element, so md:w-2xl alone renders at 36rem. --}}
    <flux:modal name="employee-form" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <form wire:submit="saveEmployee" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add employee') : __('Edit employee') }}
            </flux:heading>

            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="first_name" :label="__('First name')" required />
                    <flux:input wire:model="middle_name" :label="__('Middle name')" />

                    <flux:input wire:model="last_name" :label="__('Last name')" required />
                    <flux:input wire:model="suffix" :label="__('Suffix')" :placeholder="__('Jr., Sr., III')" />
                </div>

                <flux:select wire:model="gender" :label="__('Sex')">
                    <flux:select.option value="">{{ __('Not stated') }}</flux:select.option>
                    <flux:select.option value="Female">{{ __('Female') }}</flux:select.option>
                    <flux:select.option value="Male">{{ __('Male') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="space-y-4">
                <flux:separator :text="__('Appointment')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="positionId" :label="__('Position')">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($this->positions as $position)
                            <flux:select.option :value="$position->id">{{ $position->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="item_number" :label="__('Plantilla item')" />
                </div>

                <flux:select wire:model="employment_status" :label="__('Employment status')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (EmploymentStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="space-y-4">
                <flux:separator :text="__('Where they sit')" />

                {{-- Choosing a division only narrows the sections below it.
                     The section is what is stored; the division follows it. --}}
                <flux:select wire:model.live="employeeDivisionId" :label="__('Division')">
                    <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
                    @foreach ($this->divisions as $division)
                        <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="employeeSectionId" :label="__('Section')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->formSections as $section)
                        <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="space-y-4">
                <flux:separator :text="__('Eligibility')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="eligibilityId" :label="__('Eligibility')">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($this->eligibilityList as $eligibility)
                            <flux:select.option :value="$eligibility->id">{{ $eligibility->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="eligibilityExpiresOn" :label="__('Expiry date')" type="date" />
                </div>

                {{-- Under the pair, not on one field: a description on only
                     one of two side-by-side controls pushes it down and
                     leaves the two boxes out of line. --}}
                <flux:text size="sm">{{ __('Leave empty to keep what is on their PDS.') }}</flux:text>
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

    <flux:modal name="employee-delete" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Remove this employee?') }}</flux:heading>

            @if ($this->deleting)
                <flux:heading>{{ $this->deleting->listing_name }}</flux:heading>

                <dl class="grid grid-cols-2 gap-x-6 gap-y-5 border-y border-zinc-200 py-5 dark:border-zinc-700">
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Employee no.') }}</dt>
                        <dd class="mt-0.5 text-sm tabular-nums">{{ $this->deleting->employee_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Section') }}</dt>
                        <dd class="mt-0.5 text-sm">{{ $this->deleting->section?->name ?? '—' }}</dd>
                    </div>
                </dl>
            @endif

            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __('The employee leaves the list but their training records and approval history are kept.') }}
            </flux:callout>

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
