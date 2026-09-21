<?php

use App\Actions\Ldna\DeleteLdnaCycle;
use App\Actions\Ldna\OpenLdnaCycle;
use App\Models\LdnaCycle;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('LDNA')] class extends Component {
    public string $year = '';

    public string $opensOn = '';

    public string $closesOn = '';

    public ?int $deletingId = null;

    public string $typedYear = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    /**
     * @return Collection<int, LdnaCycle>
     */
    #[Computed]
    public function cycles(): Collection
    {
        return LdnaCycle::query()
            ->withCount([
                'assessments',
                'assessments as confirmed_count' => fn (Builder $query) => $query->whereNotNull('confirmed_at'),
            ])
            ->orderByDesc('year')
            ->get();
    }

    public function create(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->resetValidation();

        // The usual case: set up late in the year, for the year after.
        $this->year = (string) (now()->year + 1);
        $this->opensOn = today()->toDateString();
        $this->closesOn = today()->addMonth()->toDateString();

        Flux::modal('ldna-cycle-form')->show();
    }

    public function openCycle(OpenLdnaCycle $open): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $validated = $this->validate([
            'year' => ['required', 'integer', 'between:2000,2100', Rule::unique('ldna_cycles', 'year')],
            'opensOn' => ['required', 'date'],
            'closesOn' => ['required', 'date', 'after_or_equal:opensOn'],
        ]);

        $cycle = $open->handle(
            auth()->user(),
            (int) $validated['year'],
            CarbonImmutable::parse($validated['opensOn']),
            CarbonImmutable::parse($validated['closesOn']),
        );

        Flux::modal('ldna-cycle-form')->close();

        Flux::toast(variant: 'success', text: __('LDNA :year is set up for :count people.', [
            'year' => $cycle->year,
            'count' => $cycle->assessments()->count(),
        ]));

        $this->redirectRoute('ldna.show', $cycle, navigate: true);
    }

    public function confirmDelete(int $cycleId): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->deletingId = LdnaCycle::findOrFail($cycleId)->getKey();
        $this->typedYear = '';
        $this->resetValidation();

        unset($this->deleting);

        Flux::modal('delete-cycle')->show();
    }

    /**
     * The cycle the delete modal is asking about, with what it holds.
     */
    #[Computed]
    public function deleting(): ?LdnaCycle
    {
        return $this->deletingId === null
            ? null
            : LdnaCycle::query()
                ->withCount([
                    'assessments',
                    'assessments as submitted_count' => fn (Builder $query) => $query->whereNotNull('self_submitted_at'),
                    'assessments as confirmed_count' => fn (Builder $query) => $query->whereNotNull('confirmed_at'),
                ])
                ->find($this->deletingId);
    }

    public function deleteCycle(DeleteLdnaCycle $delete): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $cycle = LdnaCycle::findOrFail($this->deletingId);

        $this->resetValidation('typedYear');

        // Left open on a wrong year, with the field marked, rather than
        // closed on a toast the user might miss.
        if (! $delete->handle($cycle, $this->typedYear)) {
            $this->addError('typedYear', __('Type ":year" to delete this cycle.', ['year' => $cycle->year]));

            return;
        }

        $this->reset('deletingId', 'typedYear');

        unset($this->cycles, $this->deleting);

        Flux::modal('delete-cycle')->close();

        Flux::toast(variant: 'success', text: __('LDNA :year deleted.', ['year' => $cycle->year]));
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <x-page-heading icon="chart-bar-square">{{ __('LDNA') }}</x-page-heading>

        <flux:button variant="primary" wire:click="create">{{ __('New cycle') }}</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Year') }}</flux:table.column>
            <flux:table.column>{{ __('Window') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Confirmed by a head') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->cycles as $cycle)
                <flux:table.row :key="$cycle->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        {{-- A plain anchor, not flux:link: every variant of it
                             underlines either at rest or on hover, and passing
                             a class to undo that loses silently. Same accent
                             and hover the approvals table uses. --}}
                        <a href="{{ route('ldna.show', $cycle) }}" wire:navigate
                            class="font-medium text-[var(--color-accent-content)] hover:opacity-70">
                            {{ __('LDNA :year', ['year' => $cycle->year]) }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $cycle->opens_on->format('M j, Y') }} – {{ $cycle->closes_on->format('M j, Y') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$cycle->isOpen() ? 'green' : ($cycle->hasClosed() ? 'zinc' : 'amber')">
                            {{ $cycle->status() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="tabular-nums">
                        {{ __(':confirmed of :people', ['confirmed' => $cycle->confirmed_count, 'people' => $cycle->assessments_count]) }}
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $cycle->id }})">
                            {{ __('Delete') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No cycle yet. Set up the first one when the framework is ready.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="ldna-cycle-form" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <form wire:submit="openCycle" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New cycle') }}</flux:heading>
                <flux:text>{{ __('Everybody still working here is given an assessment, and told the dates.') }}</flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input class="md:col-span-2" wire:model="year" :label="__('Year planned for')" type="number"
                    min="2000" max="2100" required />

                <flux:input wire:model="opensOn" :label="__('Opens on')" type="date" required />
                <flux:input wire:model="closesOn" :label="__('Closes on')" type="date" required />
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">{{ __('Set up cycle') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-cycle" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <form wire:submit="deleteCycle" class="space-y-6">
            <flux:heading size="lg">{{ __('Delete this cycle?') }}</flux:heading>

            @if ($this->deleting)
                <div class="space-y-1">
                    <flux:heading>{{ __('LDNA :year', ['year' => $this->deleting->year]) }}</flux:heading>
                    <flux:text>
                        {{ __(':people assessments · :submitted submitted · :confirmed confirmed', [
                            'people' => $this->deleting->assessments_count,
                            'submitted' => $this->deleting->submitted_count,
                            'confirmed' => $this->deleting->confirmed_count,
                        ]) }}
                    </flux:text>
                </div>

                <flux:callout variant="danger" icon="exclamation-triangle">
                    {{ __('Every assessment and every answer in it is deleted for good, with the notices it sent. This cannot be undone.') }}
                </flux:callout>

                @if ($this->deleting->confirmed_count > 0)
                    {{-- Set apart: the quotes around the year would close the
                         attribute if the string sat inside :label="…". --}}
                    @php($typeYearLabel = __('A head has confirmed some of these. Type ":year" to delete it anyway.', ['year' => $this->deleting->year]))

                    <flux:input wire:model="typedYear" inputmode="numeric" autocomplete="off" :label="$typeYearLabel" />
                @endif
            @endif

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
