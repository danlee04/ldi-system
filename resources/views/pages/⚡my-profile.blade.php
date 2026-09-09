<?php

use App\Actions\Pds\PersonalDataSheetProgress;
use App\Enums\TrainingStatus;
use App\Models\Employee;
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
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-4">
            <flux:avatar size="lg" :name="$this->employee->full_name" />

            <div class="min-w-0 space-y-1">
                <flux:heading size="xl">{{ $this->employee->full_name }}</flux:heading>

                <flux:text>
                    {{ $this->employee->position?->title ?? __('No position on record') }}
                    @if ($this->employee->section)
                        — {{ $this->employee->section->name }}
                    @endif
                </flux:text>

                {{--
                    The 201 file facts, kept quiet. They are here so the
                    employee can check them, not so they compete with the
                    two things below that actually ask something of them.
                --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                    <span>{{ __('No.') }} {{ $this->employee->employee_number }}</span>

                    <span aria-hidden="true">·</span>

                    <span>{{ $this->employee->division?->name ?? __('No division') }}</span>

                    <span aria-hidden="true">·</span>

                    <span>{{ $this->employee->employment_status->label() }}</span>

                    @if ($this->employee->date_hired)
                        <span aria-hidden="true">·</span>

                        <span>{{ __('Hired') }} {{ $this->employee->date_hired->format('d M Y') }}</span>
                    @endif
                </div>

                <flux:text size="sm">
                    {{ __('Ask HR to correct anything that is wrong here.') }}
                </flux:text>
            </div>
        </div>

        <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate>
            {{ __('Account settings') }}
        </flux:button>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg">{{ __('My PDS') }}</flux:heading>

                <flux:text size="sm">
                    {{ __(':filled of :total sections started', [
                        'filled' => count($this->pdsSections) - count($this->pdsMissing),
                        'total' => count($this->pdsSections),
                    ]) }}
                </flux:text>
            </div>

            <div class="flex items-center gap-3">
                <flux:progress :value="$this->pdsPercentage" class="flex-1" />
                <span class="text-sm tabular-nums">{{ $this->pdsPercentage }}%</span>
            </div>

            @if ($this->pdsMissing === [])
                <flux:callout icon="check-circle" variant="success">
                    {{ __('Every section has something in it. Check it over before you print.') }}
                </flux:callout>
            @else
                <div class="space-y-2">
                    <flux:text size="sm">{{ __('Still empty:') }}</flux:text>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->pdsMissing as $section)
                            <flux:badge color="zinc">{{ $section['number'] }}. {{ $section['label'] }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            <flux:text size="sm">
                {{ __('Section VI fills itself from your approved training — you do not type it in.') }}
            </flux:text>

            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" :href="route('my-pds')" wire:navigate>
                    {{ __('Fill it in') }}
                </flux:button>

                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('my-pds.download')">
                    {{ __('Download') }}
                </flux:button>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Civil service eligibility') }}</flux:heading>

            @if ($this->employee->eligibilities->isEmpty())
                <flux:callout icon="information-circle">
                    {{ __('Nothing on record yet. Add it under My PDS, Section IV.') }}
                </flux:callout>
            @else
                {{-- One card holding a divided list, rather than a card each. --}}
                <div class="divide-y divide-zinc-200 dark:divide-white/10">
                    @foreach ($this->employee->eligibilities as $eligibility)
                        <div class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <flux:heading class="break-words">{{ $eligibility->name() }}</flux:heading>

                                <flux:text size="sm">
                                    {{ $eligibility->rating
                                        ? __('Rating :rating', ['rating' => $eligibility->rating])
                                        : __('No rating on record') }}
                                    @if ($eligibility->date_of_examination)
                                        — {{ $eligibility->date_of_examination->format('d M Y') }}
                                    @endif
                                </flux:text>
                            </div>

                            <x-eligibility-expiry :date="$eligibility->date_of_validity" />
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    <flux:card class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <flux:heading size="lg">{{ __('CPD units this year') }}</flux:heading>
            <flux:text size="sm">
                {{ __('From training approved and finished in :year.', ['year' => now()->year]) }}
            </flux:text>
        </div>

        <flux:heading size="xl" class="tabular-nums">
            {{ rtrim(rtrim(number_format($this->cpdUnits, 1), '0'), '.') }}
        </flux:heading>
    </flux:card>
</div>
