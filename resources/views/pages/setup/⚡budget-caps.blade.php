<?php

use App\Enums\UserRole;
use App\Models\BudgetCap;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Budget caps')] class extends Component {
    public ?int $editingId = null;

    public ?int $year = null;

    public string $budget_source = '';

    public ?float $amount = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    /**
     * @return Collection<int, BudgetCap>
     */
    #[Computed]
    public function caps(): Collection
    {
        return BudgetCap::query()->orderByDesc('year')->orderBy('budget_source')->get();
    }

    public function create(): void
    {
        $this->authorizeHr();

        $this->resetForm();
        $this->year = now()->year;

        Flux::modal('budget-cap-form')->show();
    }

    public function edit(int $capId): void
    {
        $this->authorizeHr();

        $cap = BudgetCap::findOrFail($capId);

        $this->resetValidation();

        $this->editingId = $cap->getKey();
        $this->year = $cap->year;
        $this->budget_source = $cap->budget_source;
        $this->amount = (float) $cap->amount;

        Flux::modal('budget-cap-form')->show();
    }

    public function save(): void
    {
        $this->authorizeHr();

        $validated = $this->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'budget_source' => [
                'required', 'string', 'max:255',
                Rule::unique('budget_caps', 'budget_source')
                    ->where(fn ($query) => $query->where('year', $this->year))
                    ->ignore($this->editingId),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        BudgetCap::updateOrCreate(['id' => $this->editingId], $validated);

        $this->resetForm();

        unset($this->caps);

        Flux::modal('budget-cap-form')->close();

        Flux::toast(variant: 'success', text: __('Budget cap saved.'));
    }

    private function authorizeHr(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'year', 'budget_source', 'amount');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Budget caps') }}</flux:heading>

        <flux:button variant="primary" wire:click="create">{{ __('Add cap') }}</flux:button>
    </div>

    <flux:callout icon="information-circle">
        {{ __('How much may be spent from one source in one year. Going over never blocks a plan from being saved — the commitment is made outside this system, so HR is warned and the record still stands.') }}
    </flux:callout>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Year') }}</flux:table.column>
            <flux:table.column>{{ __('Source') }}</flux:table.column>
            <flux:table.column>{{ __('Cap') }}</flux:table.column>
            <flux:table.column>{{ __('Committed') }}</flux:table.column>
            <flux:table.column>{{ __('Remaining') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->caps as $cap)
                <flux:table.row :key="$cap->id">
                    <flux:table.cell>{{ $cap->year }}</flux:table.cell>
                    <flux:table.cell>{{ $cap->budget_source }}</flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $cap->amount, 2) }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($cap->committed(), 2) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($cap->remaining() < 0)
                            <flux:badge color="red">{{ number_format($cap->remaining(), 2) }}</flux:badge>
                        @else
                            <flux:badge color="green">{{ number_format($cap->remaining(), 2) }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $cap->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No caps set. Without one, no budget warning is shown.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="budget-cap-form" class="md:w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId === null ? __('Add budget cap') : __('Edit budget cap') }}
            </flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="year" :label="__('Year')" type="number" min="2000" max="2100" required />

                <x-picklist-input wire:model="budget_source" :label="__('Budget source')"
                    :options="config('ldi.budget_sources')" required />

                <flux:input class="md:col-span-2" wire:model="amount" :label="__('Cap')"
                    type="number" step="0.01" min="0" required />
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
