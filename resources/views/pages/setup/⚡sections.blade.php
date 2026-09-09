<?php

use App\Models\Division;
use App\Models\Employee;
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

new #[Title('Sections')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $filterDivisionId = null;

    public ?int $editingId = null;

    public ?int $divisionId = null;

    public string $name = '';

    public string $code = '';

    public ?int $sectionHeadEmployeeId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDivisionId(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Section>
     */
    #[Computed]
    public function sections(): LengthAwarePaginator
    {
        return Section::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%' . $this->search . '%';

                $query->where(fn(Builder $match) => $match->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->when($this->filterDivisionId !== null, fn(Builder $query) => $query->where('division_id', $this->filterDivisionId))
            ->with(['division', 'head'])
            ->withCount('employees')
            ->orderBy('name')
            ->paginate(15);
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
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employees(): Collection
    {
        return Employee::query()->active()->orderBy('last_name')->get();
    }

    public function create(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->resetForm();

        Flux::modal('section-form')->show();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $section = Section::findOrFail($id);

        $this->resetValidation();

        $this->editingId = $section->id;
        $this->divisionId = $section->division_id;
        $this->name = $section->name;
        $this->code = $section->code;
        $this->sectionHeadEmployeeId = $section->section_head_employee_id;

        Flux::modal('section-form')->show();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $validated = $this->validate([
            'divisionId' => ['required', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sections', 'code')->ignore($this->editingId)],
            'sectionHeadEmployeeId' => ['nullable', 'exists:employees,id'],
        ]);

        Section::updateOrCreate(
            ['id' => $this->editingId],
            [
                'division_id' => $validated['divisionId'],
                'name' => $validated['name'],
                'code' => $validated['code'],
                'section_head_employee_id' => $validated['sectionHeadEmployeeId'],
            ],
        );

        $this->resetForm();

        unset($this->sections);

        Flux::modal('section-form')->close();

        Flux::toast(variant: 'success', text: __('Section saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'divisionId', 'name', 'code', 'sectionHeadEmployeeId');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Sections') }}</flux:heading>

        <flux:button variant="primary" wire:click="create">{{ __('Add section') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input size="sm" class="lg:flex-1" wire:model.live.debounce.300ms="search"
            :placeholder="__('Search section name or code')" />

        <flux:select size="sm" class="lg:w-64" wire:model.live="filterDivisionId">
            <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
            @foreach ($this->divisions as $division)
                <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->sections">
        <flux:table.columns>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Head') }}</flux:table.column>
            <flux:table.column>{{ __('Employees') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->sections as $section)
                <flux:table.row :key="$section->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-56 truncate" title="{{ $section->name }}">{{ $section->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $section->division->code }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($section->head)
                            <div class="w-44 truncate" title="{{ $section->head->full_name }}">
                                {{ $section->head->full_name }}
                            </div>
                        @else
                            <flux:badge color="amber">{{ __('No head') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $section->employees_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $section->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No sections yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="section-form" class="md:w-5xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add section') : __('Edit section') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="divisionId" :label="__('Division')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach ($this->divisions as $division)
                        <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="code" :label="__('Code')" required />

                <flux:input class="md:col-span-2" wire:model="name" :label="__('Name')" required />

                <flux:select class="md:col-span-2" wire:model="sectionHeadEmployeeId" :label="__('Section head')"
                    :description="__('Leave empty to send submissions straight to the division head.')">
                    <flux:select.option value="">{{ __('No head') }}</flux:select.option>
                    @foreach ($this->employees as $employee)
                        <flux:select.option :value="$employee->id">{{ $employee->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>
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
