<?php

use App\Actions\Ldna\DeleteCompetency;
use App\Actions\Ldna\SaveCompetency;
use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Competencies')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterType = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $type = '';

    public string $requiredLevel = '';

    /** @var array<string, string> what each level looks like, keyed by the level's value */
    public array $indicators = [];

    public ?int $confirmingId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Competency>
     */
    #[Computed]
    public function competencies(): LengthAwarePaginator
    {
        return Competency::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->filterType !== '', fn (Builder $query) => $query->where('type', $this->filterType))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function confirming(): ?Competency
    {
        return $this->confirmingId === null ? null : Competency::find($this->confirmingId);
    }

    public function create(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->resetForm();

        Flux::modal('competency-form')->show();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $competency = Competency::with('indicators')->findOrFail($id);

        $this->resetValidation();

        $this->editingId = $competency->id;
        $this->name = $competency->name;
        $this->description = (string) $competency->description;
        $this->type = $competency->type->value;
        $this->requiredLevel = (string) $competency->required_level?->value;
        $this->indicators = collect(ProficiencyLevel::cases())
            ->mapWithKeys(fn (ProficiencyLevel $level): array => [$level->value => (string) $competency->indicatorFor($level)])
            ->all();

        Flux::modal('competency-form')->show();
    }

    public function save(SaveCompetency $save): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $levels = collect(ProficiencyLevel::cases());

        $validated = $this->validate(
            [
                'name' => ['required', 'string', 'max:255', Rule::unique('competencies', 'name')->ignore($this->editingId)],
                'description' => ['nullable', 'string', 'max:2000'],
                'type' => ['required', Rule::enum(CompetencyType::class)],
                'requiredLevel' => [
                    Rule::requiredIf(fn (): bool => CompetencyType::tryFrom($this->type)?->hasOwnRequiredLevel() ?? false),
                    'nullable',
                    Rule::enum(ProficiencyLevel::class),
                ],
                ...$levels->mapWithKeys(fn (ProficiencyLevel $level): array => ["indicators.{$level->value}" => ['required', 'string', 'max:2000']])->all(),
            ],
            attributes: $levels->mapWithKeys(fn (ProficiencyLevel $level): array => ["indicators.{$level->value}" => $level->label()])->all(),
        );

        $save->handle(
            $this->editingId === null ? null : Competency::findOrFail($this->editingId),
            $validated,
            $this->indicators,
        );

        $this->resetForm();

        unset($this->competencies);

        Flux::modal('competency-form')->close();

        Flux::toast(variant: 'success', text: __('Competency saved.'));
    }

    /**
     * Opens the confirmation for taking one out of use, or back into it.
     */
    public function confirm(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->confirmingId = Competency::findOrFail($id)->id;

        unset($this->confirming);

        Flux::modal('competency-confirm')->show();
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $competency = Competency::findOrFail($id);
        $competency->update(['is_active' => ! $competency->is_active]);

        $this->confirmingId = null;

        unset($this->competencies, $this->confirming);

        Flux::modal('competency-confirm')->close();

        Flux::toast(variant: 'success', text: $competency->is_active ? __('Competency reactivated.') : __('Competency deactivated.'));
    }

    public function delete(int $id, DeleteCompetency $remove): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        if (! $remove->handle(Competency::findOrFail($id))) {
            Flux::toast(variant: 'danger', text: __('Somebody has been assessed on it. Deactivate it instead.'));

            return;
        }

        $this->confirmingId = null;

        unset($this->competencies, $this->confirming);

        Flux::modal('competency-confirm')->close();

        Flux::toast(variant: 'success', text: __('Competency deleted.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'description', 'type', 'requiredLevel');
        $this->indicators = collect(ProficiencyLevel::cases())->mapWithKeys(fn (ProficiencyLevel $level): array => [$level->value => ''])->all();
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <x-page-heading icon="puzzle-piece">{{ __('Competencies') }}</x-page-heading>

        <flux:button variant="primary" wire:click="create">{{ __('Add competency') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input size="sm" class="lg:flex-1" wire:model.live.debounce.300ms="search" :placeholder="__('Search name')" />

        <flux:select size="sm" class="lg:w-48" wire:model.live="filterType">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (CompetencyType::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->competencies">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Required') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->competencies as $competency)
                <flux:table.row :key="$competency->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-80 truncate" title="{{ $competency->name }}">{{ $competency->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $competency->type->label() }}</flux:table.cell>
                    <flux:table.cell>
                        {{-- Technical is set on each position, so there is no one level to show. --}}
                        {{ $competency->required_level?->label() ?? __('Per position') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($competency->is_active)
                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:tooltip :content="__('Edit')">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" square
                                    wire:click="edit({{ $competency->id }})" :aria-label="__('Edit')" />
                            </flux:tooltip>

                            <flux:tooltip :content="$competency->is_active ? __('Remove') : __('Reactivate')">
                                <flux:button size="sm" variant="ghost" square
                                    :icon="$competency->is_active ? 'trash' : 'arrow-uturn-left'"
                                    wire:click="confirm({{ $competency->id }})"
                                    :aria-label="$competency->is_active ? __('Remove') : __('Reactivate')" />
                            </flux:tooltip>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No competencies yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="competency-form" class="md:w-5xl md:max-w-[calc(100vw-4rem)]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add competency') : __('Edit competency') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input class="md:col-span-2" wire:model="name" :label="__('Name')" required />

                <flux:select wire:model.live="type" :label="__('Type')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (CompetencyType::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if (CompetencyType::tryFrom($type)?->hasOwnRequiredLevel())
                    <flux:select wire:model="requiredLevel" :label="__('Required level')" required>
                        <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                        @foreach (ProficiencyLevel::cases() as $level)
                            <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif ($type === CompetencyType::Technical->value)
                    <flux:text size="sm" class="self-end pb-2">
                        {{ __('Each position sets its own level, in Setup → Positions.') }}
                    </flux:text>
                @else
                    <div></div>
                @endif

                <flux:textarea class="md:col-span-2" wire:model="description" :label="__('Description')" rows="2" />

                <div class="md:col-span-2">
                    <flux:separator :text="__('What each level looks like')" />
                </div>

                @foreach (ProficiencyLevel::cases() as $level)
                    <flux:textarea wire:model="indicators.{{ $level->value }}" :label="$level->label()" rows="3" required />
                @endforeach
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

    <flux:modal name="competency-confirm" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        @if ($this->confirming)
            <div class="space-y-6">
                <flux:heading size="lg">
                    {{ $this->confirming->is_active
                        ? __('Remove :name?', ['name' => $this->confirming->name])
                        : __('Reactivate :name?', ['name' => $this->confirming->name]) }}
                </flux:heading>

                <flux:text>
                    @if ($this->confirming->is_active)
                        {{ __('Deactivating keeps it on every assessment it is already part of, and leaves it out of new ones.') }}
                    @else
                        {{ __('It will be part of new assessments again.') }}
                    @endif
                </flux:text>

                <div class="flex gap-2">
                    <flux:spacer />

                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    @if ($this->confirming->is_active)
                        @unless ($this->confirming->isInUse())
                            <flux:button variant="danger" wire:click="delete({{ $this->confirming->id }})">
                                {{ __('Delete') }}
                            </flux:button>
                        @endunless

                        <flux:button variant="primary" wire:click="toggleActive({{ $this->confirming->id }})">
                            {{ __('Deactivate') }}
                        </flux:button>
                    @else
                        <flux:button variant="primary" wire:click="toggleActive({{ $this->confirming->id }})">
                            {{ __('Reactivate') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
