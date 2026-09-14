<?php

use App\Actions\Ldna\SaveSupervisorRating;
use App\Enums\CompetencyType;
use App\Models\LdnaAssessment;
use App\Models\LdnaRating;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rate')] class extends Component {
    public LdnaAssessment $assessment;

    /** @var array<int, string> the level found, keyed by rating id; '' for none yet */
    public array $levels = [];

    /** @var array<int, string> keyed by rating id */
    public array $remarks = [];

    public function mount(LdnaAssessment $assessment): void
    {
        $this->authorize('viewAsRater', $assessment);

        $this->assessment = $assessment->load(['employee.section', 'employee.position', 'cycle']);

        $this->fillForm();
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

    public function save(SaveSupervisorRating $save): void
    {
        $save->handle(auth()->user(), $this->assessment, $this->levels, $this->remarks);

        Flux::toast(variant: 'success', text: __('Saved.'));
    }

    public function submit(SaveSupervisorRating $save): void
    {
        $save->handle(auth()->user(), $this->assessment, $this->levels, $this->remarks, submit: true);

        Flux::modal('ldna-rate-submit')->close();

        Flux::toast(variant: 'success', text: __('Rating submitted.'));

        $this->redirectRoute('ldna.ratings', navigate: true);
    }

    private function fillForm(): void
    {
        $ratings = $this->assessment->ratings()->get();

        $this->levels = $ratings->mapWithKeys(fn (LdnaRating $rating): array => [$rating->id => (string) $rating->supervisor_level?->value])->all();
        $this->remarks = $ratings->mapWithKeys(fn (LdnaRating $rating): array => [$rating->id => (string) $rating->remarks])->all();
    }
}; ?>

<div class="space-y-6">
    @php
        $canRate = auth()->user()->can('rate', $assessment);
        $done = collect($levels)->filter()->count();
    @endphp

    <div>
        <flux:link :href="route('ldna.ratings')" wire:navigate class="text-sm">{{ __('LDNA ratings') }}</flux:link>
        <flux:heading size="xl">{{ $assessment->employee->listing_name }}</flux:heading>
        <flux:text>
            {{ $assessment->employee->position?->title ?? '—' }} ·
            {{ $assessment->employee->section?->name ?? '—' }} ·
            {{ __('LDNA :year', ['year' => $assessment->cycle->year]) }}
        </flux:text>
    </div>

    @if ($assessment->isRated())
        <flux:callout icon="check-circle" variant="success">
            {{ __('Submitted on :date. It can still be changed until :close.', [
                'date' => $assessment->rated_at?->format('M j, Y'),
                'close' => $assessment->cycle->closes_on->format('M j, Y'),
            ]) }}
        </flux:callout>
    @elseif (! $assessment->isSelfSubmitted())
        <flux:callout icon="information-circle" variant="secondary">
            {{ __('They have not rated themselves yet. You can rate them anyway; only your rating counts toward the gap.') }}
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

                            {{-- A draft self-rating is theirs until they submit it. --}}
                            @if ($assessment->isSelfSubmitted() && $rating->self_level !== null)
                                <flux:badge size="sm" color="blue">
                                    {{ __('Self: :level', ['level' => $rating->self_level->label()]) }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>

                    <x-ldna.level-picker :competency="$rating->competency" :disabled="! $canRate"
                        wire:model.live="levels.{{ $rating->id }}" />

                    <flux:textarea wire:model="remarks.{{ $rating->id }}" :label="__('Remarks')" rows="2"
                        :disabled="! $canRate" />
                </flux:card>
            @endforeach
        </section>
    @endforeach

    @if ($canRate)
        <div class="sticky bottom-4 z-10 flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-lg dark:border-white/10 dark:bg-zinc-800">
            <flux:text class="tabular-nums">
                {{ __(':done of :total rated', ['done' => $done, 'total' => count($levels)]) }}
            </flux:text>

            <flux:error name="levels" />
            <flux:error name="remarks" />

            <flux:spacer />

            <flux:button wire:click="save">{{ __('Save') }}</flux:button>

            <flux:modal.trigger name="ldna-rate-submit">
                <flux:button variant="primary">{{ __('Submit') }}</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="ldna-rate-submit" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
            <div class="space-y-6">
                <flux:heading size="lg">
                    {{ __('Submit your rating of :name?', ['name' => $assessment->employee->listing_name]) }}
                </flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:text>{{ __(':done of :total competencies rated.', ['done' => $done, 'total' => count($levels)]) }}</flux:text>
                    <flux:text>{{ __('Your levels decide their gaps. They see them once the cycle closes.') }}</flux:text>
                </div>

                <flux:error name="levels" />

                <div class="flex gap-2">
                    <flux:spacer />

                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" wire:click="submit">{{ __('Submit') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
