<?php

use App\Actions\Ldna\SaveSelfRating;
use App\Enums\CompetencyType;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\LdnaRating;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('My LDNA')] class extends Component {
    #[Url]
    public ?int $year = null;

    /** @var array<int, string> the level they gave themselves, keyed by rating id; '' for none yet */
    public array $levels = [];

    public function mount(): void
    {
        abort_if(auth()->user()->employee === null, 403);

        // The open cycle first; failing that, the last one they were in.
        $this->year ??= LdnaCycle::current()?->year ?? $this->assessments->first()?->cycle->year;

        $this->fillLevels();
    }

    /**
     * Every cycle they were part of, newest first.
     *
     * @return Collection<int, LdnaAssessment>
     */
    #[Computed]
    public function assessments(): Collection
    {
        return LdnaAssessment::query()
            ->where('employee_id', auth()->user()->employee->getKey())
            ->with('cycle')
            ->get()
            ->sortByDesc(fn (LdnaAssessment $assessment): int => $assessment->cycle->year)
            ->values();
    }

    #[Computed]
    public function cycle(): ?LdnaCycle
    {
        return $this->year === null ? null : LdnaCycle::query()->where('year', $this->year)->first();
    }

    #[Computed]
    public function assessment(): ?LdnaAssessment
    {
        return $this->assessments
            ->first(fn (LdnaAssessment $assessment): bool => $assessment->cycle->year === $this->year)
            ?->load('ratings.competency.indicators');
    }

    /**
     * Their ratings by the competency's type, alphabetical within each.
     *
     * @return Collection<string, Collection<int, LdnaRating>>
     */
    #[Computed]
    public function groups(): Collection
    {
        return ($this->assessment?->ratings ?? collect())
            ->sortBy(fn (LdnaRating $rating): string => $rating->competency->name)
            ->groupBy(fn (LdnaRating $rating): string => $rating->competency->type->value);
    }

    public function updatedYear(): void
    {
        unset($this->cycle, $this->assessment, $this->groups);

        $this->resetValidation();
        $this->fillLevels();
    }

    public function save(SaveSelfRating $save): void
    {
        $save->handle(auth()->user(), $this->currentAssessment(), $this->levels);

        unset($this->assessments, $this->assessment, $this->groups);

        Flux::toast(variant: 'success', text: __('Saved. You can come back to it until :date.', [
            'date' => $this->cycle?->closes_on->format('M j, Y'),
        ]));
    }

    public function submit(SaveSelfRating $save): void
    {
        $save->handle(auth()->user(), $this->currentAssessment(), $this->levels, submit: true);

        unset($this->assessments, $this->assessment, $this->groups);

        Flux::modal('ldna-submit')->close();

        Flux::toast(variant: 'success', text: __('Submitted. Whoever rates you has been told.'));
    }

    private function currentAssessment(): LdnaAssessment
    {
        return $this->assessment ?? abort(404);
    }

    private function fillLevels(): void
    {
        $this->levels = ($this->assessment?->ratings ?? collect())
            ->mapWithKeys(fn (LdnaRating $rating): array => [$rating->id => (string) $rating->self_level?->value])
            ->all();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <x-page-heading icon="clipboard-document-list">{{ __('My LDNA') }}</x-page-heading>

            @if ($this->cycle)
                <flux:text>
                    {{ __('LDNA :year', ['year' => $this->cycle->year]) }} ·
                    {{ $this->cycle->opens_on->format('M j') }} – {{ $this->cycle->closes_on->format('M j, Y') }} ·
                    {{ $this->cycle->status() }}
                </flux:text>
            @endif
        </div>

        @if ($this->assessments->count() > 1)
            <flux:select size="sm" class="w-40" wire:model.live="year">
                @foreach ($this->assessments as $past)
                    <flux:select.option :value="$past->cycle->year">
                        {{ __('LDNA :year', ['year' => $past->cycle->year]) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    @if ($this->cycle === null)
        <flux:callout icon="clipboard-document-list" variant="secondary">
            {{ __('No LDNA is open right now.') }}
        </flux:callout>
    @elseif ($this->assessment === null)
        <flux:callout icon="clipboard-document-list" variant="secondary">
            {{ __('You are not in LDNA :year yet. HR will add you.', ['year' => $this->cycle->year]) }}
        </flux:callout>
    @else
        @php
            $canEdit = auth()->user()->can('selfRate', $this->assessment);
            $showsRequired = $this->assessment->isSelfSubmitted();
            $showsResult = auth()->user()->can('seeOwnResult', $this->assessment);
            $done = collect($levels)->filter()->count();
        @endphp

        @if (! $this->cycle->isOpen() && ! $this->cycle->hasClosed())
            <flux:callout icon="clock" variant="secondary">
                {{ __('It opens on :date. You can read through it until then.', ['date' => $this->cycle->opens_on->format('M j, Y')]) }}
            </flux:callout>
        @elseif ($showsRequired && $canEdit)
            <flux:callout icon="check-circle" variant="success">
                {{ __('Submitted on :date. You can still change a level until :close.', [
                    'date' => $this->assessment->self_submitted_at?->format('M j, Y'),
                    'close' => $this->cycle->closes_on->format('M j, Y'),
                ]) }}
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
                                @if ($showsRequired)
                                    <flux:badge size="sm">
                                        {{ __('Required: :level', ['level' => $rating->required_level->label()]) }}
                                    </flux:badge>
                                @endif

                                @if ($showsResult && $rating->gap() > 0)
                                    <flux:badge size="sm" color="amber">
                                        {{ trans_choice('{1} 1 level short|[2,*] :count levels short', $rating->gap(), ['count' => $rating->gap()]) }}
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        <x-ldna.level-picker :competency="$rating->competency" :disabled="! $canEdit"
                            wire:model.live="levels.{{ $rating->id }}" />
                    </flux:card>
                @endforeach
            </section>
        @endforeach

        @if ($canEdit)
            <div class="sticky bottom-4 z-10 flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-lg dark:border-white/10 dark:bg-zinc-800">
                <flux:text class="tabular-nums">
                    {{ __(':done of :total rated', ['done' => $done, 'total' => count($levels)]) }}
                </flux:text>

                <flux:error name="levels" />

                <flux:spacer />

                <flux:button wire:click="save">{{ __('Save') }}</flux:button>

                @unless ($this->assessment->isSelfSubmitted())
                    <flux:modal.trigger name="ldna-submit">
                        <flux:button variant="primary">{{ __('Submit') }}</flux:button>
                    </flux:modal.trigger>
                @endunless
            </div>

            <flux:modal name="ldna-submit" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Submit your self-rating?') }}</flux:heading>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:text>
                            {{ __(':done of :total competencies rated.', ['done' => $done, 'total' => count($levels)]) }}
                        </flux:text>

                        <flux:text>
                            {{ __('Whoever rates you is told it is ready. You can still change a level until :date.', [
                                'date' => $this->cycle->closes_on->format('M j, Y'),
                            ]) }}
                        </flux:text>
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
    @endif
</div>
