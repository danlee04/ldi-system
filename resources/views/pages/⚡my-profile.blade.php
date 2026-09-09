<?php

use App\Actions\Pds\PersonalDataSheetProgress;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What the system holds about the person signed in.
 *
 * Read-only on purpose. The employment details here are HR's to set, what
 * they may change themselves is on My PDS, and their sign-in details are
 * under Settings — this page points at both rather than duplicating them.
 */
new #[Title('My profile')] class extends Component {
    public function mount(): void
    {
        abort_if($this->employee === null, 403, __('Your account is not linked to an employee record.'));
    }

    #[Computed(persist: true)]
    public function employee(): ?Employee
    {
        return auth()->user()->employee?->load(['section.division', 'position', 'eligibilities.eligibility']);
    }

    /**
     * Their whole training history, newest first.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function records(): Collection
    {
        return $this->employee->trainingRecords()->orderByDesc('date_end')->get();
    }

    /**
     * What their approved training counts for this year. Only approved
     * records count — a pending one may yet be turned down.
     */
    #[Computed]
    public function cpdUnits(): float
    {
        return (float) $this->employee->trainingRecords()
            ->where('status', TrainingStatus::Approved)
            ->whereYear('date_end', now()->year)
            ->sum('cpd_units');
    }

    /**
     * @return list<array{number: string, label: string, filled: bool}>
     */
    #[Computed]
    public function pdsSections(): array
    {
        return app(PersonalDataSheetProgress::class)->handle($this->employee);
    }

    #[Computed]
    public function pdsPercentage(): int
    {
        return app(PersonalDataSheetProgress::class)->percentage($this->pdsSections);
    }

    /**
     * @return list<array{number: string, label: string, filled: bool}>
     */
    #[Computed]
    public function pdsMissing(): array
    {
        return array_values(array_filter(
            $this->pdsSections,
            fn (array $section): bool => ! $section['filled'],
        ));
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $this->employee->full_name }}</flux:heading>
            <flux:text>
                {{ $this->employee->position?->title ?? __('No position on record') }}
                @if ($this->employee->section)
                    — {{ $this->employee->section->name }}
                @endif
            </flux:text>
        </div>

        <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate>
            {{ __('Account settings') }}
        </flux:button>
    </div>

    <flux:card class="grid gap-4 md:grid-cols-5">
        <div>
            <flux:text size="sm">{{ __('Employee no.') }}</flux:text>
            <flux:heading size="lg">{{ $this->employee->employee_number }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Division') }}</flux:text>
            <flux:heading size="lg">{{ $this->employee->division?->name ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Employment status') }}</flux:text>
            <flux:heading size="lg">{{ $this->employee->employment_status->label() }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Date hired') }}</flux:text>
            <flux:heading size="lg">{{ $this->employee->date_hired?->format('d M Y') ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('CPD units this year') }}</flux:text>
            <flux:heading size="lg" class="tabular-nums">{{ rtrim(rtrim(number_format($this->cpdUnits, 1), '0'), '.') }}</flux:heading>
        </div>
    </flux:card>

    <flux:text size="sm">
        {{ __('These come from your 201 file. Ask HR to correct anything that is wrong here.') }}
    </flux:text>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Civil service eligibility') }}</flux:heading>

            @forelse ($this->employee->eligibilities as $eligibility)
                <flux:card class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading>{{ $eligibility->name() }}</flux:heading>
                        <flux:text size="sm">
                            {{ $eligibility->rating ? __('Rating :rating', ['rating' => $eligibility->rating]) : __('No rating on record') }}
                            @if ($eligibility->date_of_examination)
                                — {{ $eligibility->date_of_examination->format('d M Y') }}
                            @endif
                        </flux:text>
                    </div>

                    <x-eligibility-expiry :date="$eligibility->date_of_validity" />
                </flux:card>
            @empty
                <flux:callout icon="information-circle">
                    {{ __('Nothing on record yet. Add it under My PDS, Section IV.') }}
                </flux:callout>
            @endforelse
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('My PDS') }}</flux:heading>

                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('my-pds.download')">
                        {{ __('Download') }}
                    </flux:button>
                    <flux:button size="sm" :href="route('my-pds')" wire:navigate>{{ __('Fill it in') }}</flux:button>
                </div>
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between text-sm">
                    <span>{{ __(':filled of :total sections started', [
                        'filled' => count($this->pdsSections) - count($this->pdsMissing),
                        'total' => count($this->pdsSections),
                    ]) }}</span>
                    <span class="tabular-nums">{{ $this->pdsPercentage }}%</span>
                </div>
                <flux:progress :value="$this->pdsPercentage" />
            </div>

            @if ($this->pdsMissing === [])
                <flux:callout icon="check-circle" variant="success">
                    {{ __('Every section has something in it. Check it over before you print.') }}
                </flux:callout>
            @else
                <flux:text size="sm">{{ __('Still empty:') }}</flux:text>

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->pdsMissing as $section)
                        <flux:badge color="zinc">{{ $section['number'] }}. {{ $section['label'] }}</flux:badge>
                    @endforeach
                </div>
            @endif

            <flux:text size="sm">
                {{ __('Section VI fills itself from your approved training — you do not type it in.') }}
            </flux:text>
        </div>
    </div>

    <flux:heading size="lg">{{ __('Training history') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Conducted by') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->records as $record)
                <flux:table.row :key="$record->id" class="transition-colors hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:table.cell>
                        <div class="w-72 truncate" title="{{ $record->title }}">
                            <button type="button"
                                class="block w-full cursor-pointer truncate text-left text-[var(--color-accent-content)] hover:opacity-70"
                                wire:click="$dispatch('show-training', { recordId: {{ $record->id }} })">
                                {{ $record->title }}
                            </button>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $record->inclusive_dates }}</flux:table.cell>
                    <flux:table.cell class="text-right tabular-nums">{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="w-28 truncate" title="{{ $record->ld_type_label }}">
                            {{ $record->ld_type_label }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="w-48 truncate" title="{{ $record->conducted_by }}">
                            {{ $record->conducted_by }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell><x-training-status :record="$record" /></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No training on record yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <livewire:pages::trainings.detail-modal />
</div>
