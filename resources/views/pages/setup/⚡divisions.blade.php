<?php

use App\Models\Division;
use App\Models\Employee;
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

new #[Title('Divisions')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public ?int $divisionHeadEmployeeId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Division>
     */
    #[Computed]
    public function divisions(): LengthAwarePaginator
    {
        return Division::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $match) => $match->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->with('head')
            ->withCount('sections')
            ->orderBy('name')
            ->paginate(15);
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

        Flux::modal('division-form')->show();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $division = Division::findOrFail($id);

        $this->resetValidation();

        $this->editingId = $division->id;
        $this->name = $division->name;
        $this->code = $division->code;
        $this->divisionHeadEmployeeId = $division->division_head_employee_id;

        Flux::modal('division-form')->show();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('divisions', 'code')->ignore($this->editingId)],
            'divisionHeadEmployeeId' => ['nullable', 'exists:employees,id'],
        ]);

        Division::updateOrCreate(['id' => $this->editingId], [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'division_head_employee_id' => $validated['divisionHeadEmployeeId'],
        ]);

        $this->resetForm();

        unset($this->divisions);

        Flux::modal('division-form')->close();

        Flux::toast(variant: 'success', text: __('Division saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'code', 'divisionHeadEmployeeId');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Divisions') }}</flux:heading>

        <flux:button variant="primary" wire:click="create">{{ __('Add division') }}</flux:button>
    </div>

    <flux:input size="sm" class="lg:w-96" wire:model.live.debounce.300ms="search"
        :placeholder="__('Search division name or code')" />

    <flux:table :paginate="$this->divisions">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Head') }}</flux:table.column>
            <flux:table.column>{{ __('Sections') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->divisions as $division)
                <flux:table.row :key="$division->id">
                    <flux:table.cell>
                        <div class="w-72 truncate" title="{{ $division->name }}">{{ $division->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $division->code }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($division->head)
                            <div class="w-44 truncate" title="{{ $division->head->full_name }}">
                                {{ $division->head->full_name }}
                            </div>
                        @else
                            <flux:badge color="amber">{{ __('No head') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $division->sections_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $division->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No divisions yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="division-form" class="md:w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add division') : __('Edit division') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="code" :label="__('Code')" required />

                <flux:select class="md:col-span-2" wire:model="divisionHeadEmployeeId" :label="__('Division head')"
                    :description="__('The last step of every approval in this division.')">
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
