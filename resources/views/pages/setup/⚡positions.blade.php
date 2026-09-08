<?php

use App\Models\Position;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Positions')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $filterSalaryGrade = null;

    public ?int $editingId = null;

    public string $title = '';

    public string $itemNumber = '';

    public ?int $salaryGrade = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSalaryGrade(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Position>
     */
    #[Computed]
    public function positions(): LengthAwarePaginator
    {
        return Position::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $match) => $match->where('title', 'like', $term)->orWhere('item_number', 'like', $term));
            })
            ->when($this->filterSalaryGrade !== null, fn (Builder $query) => $query->where('salary_grade', $this->filterSalaryGrade))
            ->withCount('employees')
            ->orderBy('title')
            ->paginate(15);
    }

    /**
     * Only the grades actually in use, so the filter never offers an
     * option that returns nothing.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function salaryGrades(): Collection
    {
        return Position::query()
            ->whereNotNull('salary_grade')
            ->distinct()
            ->orderBy('salary_grade')
            ->pluck('salary_grade');
    }

    public function create(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->resetForm();

        Flux::modal('position-form')->show();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $position = Position::findOrFail($id);

        $this->resetValidation();

        $this->editingId = $position->id;
        $this->title = $position->title;
        $this->itemNumber = $position->item_number ?? '';
        $this->salaryGrade = $position->salary_grade;

        Flux::modal('position-form')->show();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'itemNumber' => ['nullable', 'string', 'max:50'],
            'salaryGrade' => ['nullable', 'integer', 'between:1,33'],
        ]);

        Position::updateOrCreate(['id' => $this->editingId], [
            'title' => $validated['title'],
            'item_number' => $validated['itemNumber'] ?: null,
            'salary_grade' => $validated['salaryGrade'],
        ]);

        $this->resetForm();

        unset($this->positions);

        Flux::modal('position-form')->close();

        Flux::toast(variant: 'success', text: __('Position saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'title', 'itemNumber', 'salaryGrade');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Positions') }}</flux:heading>

        <flux:button variant="primary" wire:click="create">{{ __('Add position') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input size="sm" class="lg:flex-1" wire:model.live.debounce.300ms="search"
            :placeholder="__('Search title or item number')" />

        <flux:select size="sm" class="lg:w-48" wire:model.live="filterSalaryGrade">
            <flux:select.option value="">{{ __('All salary grades') }}</flux:select.option>
            @foreach ($this->salaryGrades as $grade)
                <flux:select.option :value="$grade">{{ __('SG :grade', ['grade' => $grade]) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->positions">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Item number') }}</flux:table.column>
            <flux:table.column>{{ __('Salary grade') }}</flux:table.column>
            <flux:table.column>{{ __('Employees') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->positions as $position)
                <flux:table.row :key="$position->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-80 truncate" title="{{ $position->title }}">{{ $position->title }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $position->item_number ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $position->salary_grade ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $position->employees_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $position->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No positions yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="position-form" class="md:w-5xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add position') : __('Edit position') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input class="md:col-span-2" wire:model="title" :label="__('Title')" required />

                <flux:input wire:model="itemNumber" :label="__('Item number')" />
                <flux:input wire:model="salaryGrade" :label="__('Salary grade')" type="number" min="1" max="33" />
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
