<?php

use App\Actions\Ldna\ConfirmLdnaAssessment;
use App\Enums\CompetencyType;
use App\Models\LdnaAssessment;
use App\Models\LdnaRating;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What somebody said about themselves, for the head who confirms it.
 *
 * The levels are theirs alone and are read-only here. All the head does
 * is read them, note anything worth noting, and agree — which is what
 * lets the assessment count toward the cycle's gaps.
 */
new #[Title('Review')] class extends Component {
    public LdnaAssessment $assessment;

    /** @var array<int, string> keyed by rating id */
    public array $remarks = [];

    public function mount(LdnaAssessment $assessment): void
    {
        $this->authorize('viewAsConfirmer', $assessment);

        $this->assessment = $assessment->load(['employee.section', 'employee.position', 'cycle']);

        $this->remarks = $assessment->ratings()->get()
            ->mapWithKeys(fn (LdnaRating $rating): array => [$rating->id => (string) $rating->remarks])
            ->all();
    }

    /**
     * The ratings by the competency's type, alphabetical within each.
     *
     * @return Collection<string, Collection<int, LdnaRating>>
     */
    #[Computed]
    public function groups(): Collection
    {
        return $this->assessment->ratings()
            ->with('competency.indicators')
            ->get()
            ->sortBy(fn (LdnaRating $rating): string => $rating->competency->name)
            ->groupBy(fn (LdnaRating $rating): string => $rating->competency->type->value);
    }

    /**
     * How many of the competencies they put themselves short on.
     */
    #[Computed]
    public function gaps(): int
    {
        return $this->assessment->ratings()->get()
            ->filter(fn (LdnaRating $rating): bool => $rating->gap() > 0)
            ->count();
    }

    public function save(ConfirmLdnaAssessment $confirm): void
    {
        $confirm->handle(auth()->user(), $this->assessment, $this->remarks);

        Flux::toast(variant: 'success', text: __('Remarks saved.'));
    }

    public function confirm(ConfirmLdnaAssessment $confirm): void
    {
        $confirm->handle(auth()->user(), $this->assessment, $this->remarks, confirm: true);

        Flux::modal('ldna-confirm')->close();

        Flux::toast(variant: 'success', text: __('Assessment confirmed.'));

        $this->redirectRoute('ldna.confirmations', navigate: true);
    }
}; ?>

<div class="space-y-6">
    @php
        $canConfirm = auth()->user()->can('confirm', $assessment);
    @endphp

    <div class="space-y-3">
        <flux:button size="sm" variant="ghost" icon="chevron-left"
            :href="route('ldna.confirmations')" wire:navigate>
            {{ __('LDNA confirmations') }}
        </flux:button>

        <flux:heading size="xl">{{ $assessment->employee->listing_name }}</flux:heading>
        <flux:text>
            {{ $assessment->employee->position?->title ?? '—' }} ·
            {{ $assessment->employee->section?->name ?? '—' }} ·
            {{ __('LDNA :year', ['year' => $assessment->cycle->year]) }}
        </flux:text>
    </div>

    @if ($assessment->isConfirmed())
        <flux:callout icon="check-circle" variant="success">
            {{ __('Confirmed on :date. Your remarks can still be changed until :close.', [
                'date' => $assessment->confirmed_at?->format('M j, Y'),
                'close' => $assessment->cycle->closes_on->format('M j, Y'),
            ]) }}
        </flux:callout>
    @elseif (! $assessment->isSelfSubmitted())
        <flux:callout icon="information-circle" variant="secondary">
            {{ __('They have not submitted their assessment yet. There is nothing to confirm until they do.') }}
        </flux:callout>
    @endif

    @foreach (CompetencyType::cases() as $type)
        @php($ratings = $this->groups->get($type->value))

        @continue($ratings === null)

        <section class="space-y-3" wire:key="group-{{ $type->value }}">
            <flux:heading size="lg">{{ $type->label() }}</flux:heading>

            @foreach ($ratings as $rating)
                <flux:card class="space-y-4" wire:key="rating-{{ $rating->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 max-w-prose">
                            <flux:heading>{{ $rating->competency->name }}</flux:heading>

                            @if ($rating->competency->description)
                                <flux:text size="sm">{{ $rating->competency->description }}</flux:text>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <flux:badge size="sm">
                                {{ __('Required: :level', ['level' => $rating->required_level->label()]) }}
                            </flux:badge>

                            {{-- A draft is theirs until they submit it, so
                                 nothing of it is shown before then. --}}
                            @if ($assessment->isSelfSubmitted() && $rating->self_level !== null)
                                <flux:badge size="sm" color="blue">
                                    {{ __('They said: :level', ['level' => $rating->self_level->label()]) }}
                                </flux:badge>

                                @if ($rating->gap() > 0)
                                    <flux:badge size="sm" color="amber">
                                        {{ __(':count short', ['count' => $rating->gap()]) }}
                                    </flux:badge>
                                @else
                                    <flux:badge size="sm" color="green">{{ __('Meets it') }}</flux:badge>
                                @endif
                            @endif
                        </div>
                    </div>

                    <flux:textarea wire:model="remarks.{{ $rating->id }}" :label="__('Remarks')" rows="2"
                        :disabled="! $canConfirm" />
                </flux:card>
            @endforeach
        </section>
    @endforeach

    @if ($canConfirm)
        <div class="sticky bottom-4 z-10 flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-lg dark:border-white/10 dark:bg-zinc-800">
            <flux:text class="tabular-nums">
                {{ __(':count short of the requirement', ['count' => $this->gaps]) }}
            </flux:text>

            <flux:error name="remarks" />
            <flux:error name="confirm" />

            <flux:spacer />

            <flux:button wire:click="save">{{ __('Save remarks') }}</flux:button>

            @if (! $assessment->isConfirmed())
                <flux:modal.trigger name="ldna-confirm">
                    <flux:button variant="primary" :disabled="! $assessment->isSelfSubmitted()">
                        {{ __('Confirm') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </div>

        <flux:modal name="ldna-confirm" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
            <div class="space-y-6">
                <flux:heading size="lg">
                    {{ __('Confirm the assessment of :name?', ['name' => $assessment->employee->listing_name]) }}
                </flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:text>
                        {{ __('They put themselves short on :count of :total competencies.', [
                            'count' => $this->gaps,
                            'total' => count($remarks),
                        ]) }}
                    </flux:text>
                    <flux:text>{{ __('Confirming is what puts their gaps into the cycle\'s report. They see them once the cycle closes.') }}</flux:text>
                </div>

                <flux:error name="confirm" />

                <div class="flex gap-2">
                    <flux:spacer />

                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" wire:click="confirm">{{ __('Confirm') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
