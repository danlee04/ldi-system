<?php

use App\Actions\Ldna\SetPositionCompetencies;
use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Position;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public ?int $competencyPositionId = null;

    /** @var array<int, string> the level each technical competency is asked at, '' when the position does not need it */
    public array $positionLevels = [];

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
            ->withCount(['employees', 'competencies'])
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

    /**
     * Every technical competency still in use, which is what the modal
     * offers. An inactive one is left off, and so is left alone on save.
     *
     * @return Collection<int, Competency>
     */
    #[Computed]
    public function technicalCompetencies(): Collection
    {
        return Competency::query()
            ->active()
            ->where('type', CompetencyType::Technical)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function competencyPosition(): ?Position
    {
        return $this->competencyPositionId === null ? null : Position::find($this->competencyPositionId);
    }

    public function editCompetencies(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $position = Position::findOrFail($id);

        $this->resetValidation();

        $set = DB::table('competency_position')
            ->where('position_id', $position->id)
            ->pluck('required_level', 'competency_id');

        $this->competencyPositionId = $position->id;
        $this->positionLevels = $this->technicalCompetencies
            ->mapWithKeys(fn (Competency $competency): array => [$competency->id => (string) ($set[$competency->id] ?? '')])
            ->all();

        unset($this->competencyPosition);

        Flux::modal('position-competencies')->show();
    }

    public function saveCompetencies(SetPositionCompetencies $set): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $position = Position::findOrFail($this->competencyPositionId);

        $this->validate(['positionLevels.*' => ['nullable', Rule::enum(ProficiencyLevel::class)]]);

        $set->handle($position, $this->technicalCompetencies, $this->positionLevels);

        $this->reset('competencyPositionId', 'positionLevels');

        unset($this->positions);

        Flux::modal('position-competencies')->close();

        Flux::toast(variant: 'success', text: __('Competencies saved.'));
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
            <flux:table.column>{{ __('Technical') }}</flux:table.column>
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
                    <flux:table.cell>{{ $position->competencies_count }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:tooltip :content="__('Technical competencies')">
                                <flux:button size="sm" variant="ghost" icon="puzzle-piece" square
                                    wire:click="editCompetencies({{ $position->id }})"
                                    :aria-label="__('Technical competencies')" />
                            </flux:tooltip>

                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $position->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No positions yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="position-form" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add position') : __('Edit position') }}
            </flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="title" :label="__('Title')" required />

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

    <flux:modal name="position-competencies" class="md:w-4xl md:max-w-[calc(100vw-4rem)]">
        <form wire:submit="saveCompetencies" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Technical competencies') }}</flux:heading>
                <flux:text>{{ $this->competencyPosition?->title }}</flux:text>
            </div>

            @if ($this->technicalCompetencies->isEmpty())
                <flux:callout icon="puzzle-piece" variant="secondary">
                    {{ __('There are no technical competencies yet. Add them in Setup → Competencies.') }}
                </flux:callout>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($this->technicalCompetencies as $competency)
                        <flux:select wire:key="position-level-{{ $competency->id }}"
                            wire:model="positionLevels.{{ $competency->id }}" :label="$competency->name">
                            <flux:select.option value="">{{ __('Not needed') }}</flux:select.option>
                            @foreach (ProficiencyLevel::cases() as $level)
                                <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
