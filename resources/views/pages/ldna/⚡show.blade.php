<?php

use App\Actions\Ldna\RefreshLdnaAssessment;
use App\Actions\Ldna\SyncLdnaCycle;
use App\Actions\Reports\LdnaGapReport;
use App\Enums\CompetencyType;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use App\Models\Section;
use App\Workflow\LdnaRater;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('LDNA')] class extends Component {
    public LdnaCycle $cycle;

    #[Url]
    public ?int $filterDivision = null;

    /** '' for everybody, or not_self, not_rated, rated */
    #[Url]
    public string $filterStatus = '';

    /** progress, or gaps */
    #[Url]
    public string $tab = 'progress';

    #[Url]
    public ?int $gapDivision = null;

    #[Url]
    public ?int $gapSection = null;

    /** The competency whose people are showing in the gaps table. */
    public ?int $expanded = null;

    public string $closesOn = '';

    public ?int $refreshingId = null;

    private ?LdnaRater $rater = null;

    public function mount(LdnaCycle $cycle): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->cycle = $cycle;
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed]
    public function divisions(): Collection
    {
        return Division::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, LdnaAssessment>
     */
    #[Computed]
    public function assessments(): Collection
    {
        return LdnaAssessment::query()
            ->where('ldna_cycle_id', $this->cycle->id)
            ->with(['employee.section', 'employee.division', 'position'])
            ->when($this->filterDivision !== null, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employee) => $employee->where('division_id', $this->filterDivision),
            ))
            ->when($this->filterStatus === 'not_self', fn (Builder $query) => $query->whereNull('self_submitted_at'))
            ->when($this->filterStatus === 'not_rated', fn (Builder $query) => $query->whereNull('rated_at'))
            ->when($this->filterStatus === 'rated', fn (Builder $query) => $query->whereNotNull('rated_at'))
            ->get()
            ->sortBy(fn (LdnaAssessment $assessment): string => $assessment->employee->listing_name)
            ->values();
    }

    /**
     * Who rates each person, by assessment id: a head's name, or HR.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function raters(): array
    {
        $rater = $this->rater();

        $raterIds = $this->assessments->mapWithKeys(
            fn (LdnaAssessment $assessment): array => [$assessment->id => $rater->raterIdFor($assessment->employee)],
        );

        $names = Employee::query()
            ->whereIn('id', $raterIds->filter()->unique()->values())
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->listing_name]);

        return $raterIds
            ->map(fn (?int $id): string => $id === null ? __('HR') : (string) ($names[$id] ?? __('HR')))
            ->all();
    }

    /**
     * The assessments HR rates itself — nobody above the person — by id.
     *
     * @return array<int, true>
     */
    #[Computed]
    public function hrRates(): array
    {
        $rater = $this->rater();

        return $this->assessments
            ->filter(fn (LdnaAssessment $assessment): bool => $rater->raterIdFor($assessment->employee) === null)
            ->mapWithKeys(fn (LdnaAssessment $assessment): array => [$assessment->id => true])
            ->all();
    }

    /**
     * The one LdnaRater used by both raters() and hrRates(), so the two do
     * not each run their own active-employee query. Never a singleton
     * (see .ai/rules/ldna.md), so it is memoised per-request here instead.
     */
    private function rater(): LdnaRater
    {
        return $this->rater ??= app(LdnaRater::class);
    }

    /**
     * Positions that name at least one active technical competency.
     * Somebody in a position that names none — or whose only one has since
     * been deactivated — is assessed on core alone, which is seldom what
     * HR meant. Mirrors BuildCompetencyProfile's own filter, so this badge
     * agrees with what the person is actually assessed on.
     *
     * @return array<int, true>
     */
    #[Computed]
    public function positionsWithTechnical(): array
    {
        return array_fill_keys(
            DB::table('competency_position')
                ->join('competencies', 'competencies.id', '=', 'competency_position.competency_id')
                ->where('competencies.is_active', true)
                ->where('competencies.type', CompetencyType::Technical->value)
                ->distinct()
                ->pluck('competency_position.position_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all(),
            true,
        );
    }

    /**
     * @return array{people: int, self: int, rated: int}
     */
    #[Computed]
    public function progress(): array
    {
        $all = fn (): Builder => LdnaAssessment::query()->where('ldna_cycle_id', $this->cycle->id);

        return [
            'people' => $all()->count(),
            'self' => $all()->whereNotNull('self_submitted_at')->count(),
            'rated' => $all()->whereNotNull('rated_at')->count(),
        ];
    }

    #[Computed]
    public function refreshing(): ?LdnaAssessment
    {
        return $this->refreshingId === null ? null : LdnaAssessment::with('employee')->find($this->refreshingId);
    }

    public function updatedGapDivision(): void
    {
        // A section from another division would narrow to nobody.
        $this->gapSection = null;
        $this->expanded = null;
    }

    /**
     * @return list<array{competency_id: int, competency: string, type: CompetencyType, rated: int, with_gap: int, percentage: float, average_gap: float, plans: int, people: list<array{employee: string, section: string, required: \App\Enums\ProficiencyLevel, rating: \App\Enums\ProficiencyLevel|null, gap: int}>}>
     */
    #[Computed]
    public function gaps(): array
    {
        return app(LdnaGapReport::class)->handle($this->cycle, $this->gapDivision, $this->gapSection);
    }

    /**
     * @return Collection<int, Section>
     */
    #[Computed]
    public function gapSections(): Collection
    {
        return Section::query()
            ->when($this->gapDivision !== null, fn (Builder $query) => $query->where('division_id', $this->gapDivision))
            ->orderBy('name')
            ->get();
    }

    public function toggle(int $competencyId): void
    {
        $this->expanded = $this->expanded === $competencyId ? null : $competencyId;
    }

    public function confirmSync(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->resetValidation();

        Flux::modal('ldna-sync')->show();
    }

    public function syncCycle(SyncLdnaCycle $sync): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $added = $sync->handle($this->cycle);

        unset($this->assessments, $this->raters, $this->hrRates, $this->progress);

        Flux::modal('ldna-sync')->close();

        Flux::toast(variant: 'success', text: trans_choice(
            '{0} Everybody is already in it.|{1} Added one person.|[2,*] Added :count people.',
            $added,
            ['count' => $added],
        ));
    }

    public function confirmRefresh(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->resetValidation();

        $this->refreshingId = LdnaAssessment::query()->where('ldna_cycle_id', $this->cycle->id)->findOrFail($id)->id;

        unset($this->refreshing);

        Flux::modal('ldna-refresh')->show();
    }

    public function refreshAssessment(RefreshLdnaAssessment $refresh): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $refresh->handle(LdnaAssessment::query()->where('ldna_cycle_id', $this->cycle->id)->findOrFail($this->refreshingId));

        $this->refreshingId = null;

        unset($this->assessments, $this->raters, $this->hrRates, $this->progress, $this->refreshing);

        Flux::modal('ldna-refresh')->close();

        Flux::toast(variant: 'success', text: __('Assessment refreshed.'));
    }

    public function editClosing(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->resetValidation();

        $this->closesOn = $this->cycle->closes_on->toDateString();

        Flux::modal('ldna-extend')->show();
    }

    public function extend(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->validate([
            'closesOn' => ['required', 'date', 'after_or_equal:'.$this->cycle->opens_on->toDateString()],
        ]);

        $this->cycle->update(['closes_on' => $this->closesOn]);

        Flux::modal('ldna-extend')->close();

        Flux::toast(variant: 'success', text: __('LDNA :year now closes on :date.', [
            'year' => $this->cycle->year,
            'date' => $this->cycle->closes_on->format('M j, Y'),
        ]));
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3 print:hidden">
        <div>
            <flux:link :href="route('ldna.index')" wire:navigate class="text-sm">{{ __('All cycles') }}</flux:link>
            <flux:heading size="xl">{{ __('LDNA :year', ['year' => $cycle->year]) }}</flux:heading>
            <flux:text>
                {{ $cycle->opens_on->format('M j, Y') }} – {{ $cycle->closes_on->format('M j, Y') }} · {{ $cycle->status() }}
            </flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button icon="calendar" wire:click="editClosing">{{ __('Change closing date') }}</flux:button>

            @unless ($cycle->hasClosed())
                <flux:button icon="user-plus" wire:click="confirmSync">{{ __('Add new people') }}</flux:button>
            @endunless
        </div>
    </div>

    {{-- Flux's tabs are a Pro component, so these are two plain buttons. --}}
    <div class="flex gap-2 print:hidden">
        <flux:button size="sm" :variant="$tab === 'progress' ? 'primary' : 'ghost'" wire:click="$set('tab', 'progress')">
            {{ __('Progress') }}
        </flux:button>

        <flux:button size="sm" :variant="$tab === 'gaps' ? 'primary' : 'ghost'" wire:click="$set('tab', 'gaps')">
            {{ __('Gaps') }}
        </flux:button>
    </div>

    @if ($tab === 'progress')
    <div class="space-y-6">
            <x-dashboard.figures :cards="[
                [
                    'icon' => 'users',
                    'label' => __('People in it'),
                    'value' => number_format($this->progress['people']),
                    'support' => __('active when it was set up, or added since'),
                ],
                [
                    'icon' => 'user',
                    'label' => __('Rated themselves'),
                    'value' => number_format($this->progress['self']),
                    'support' => __('of :people', ['people' => $this->progress['people']]),
                ],
                [
                    'icon' => 'check-badge',
                    'label' => __('Rated by a supervisor'),
                    'value' => number_format($this->progress['rated']),
                    'support' => __('of :people', ['people' => $this->progress['people']]),
                ],
            ]" />

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <flux:select size="sm" class="lg:w-64" wire:model.live="filterDivision">
                    <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
                    @foreach ($this->divisions as $division)
                        <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select size="sm" class="lg:w-56" wire:model.live="filterStatus">
                    <flux:select.option value="">{{ __('Everybody') }}</flux:select.option>
                    <flux:select.option value="not_self">{{ __('Has not rated themselves') }}</flux:select.option>
                    <flux:select.option value="not_rated">{{ __('Not rated by a supervisor') }}</flux:select.option>
                    <flux:select.option value="rated">{{ __('Rated by a supervisor') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Section') }}</flux:table.column>
                    <flux:table.column>{{ __('Self-rating') }}</flux:table.column>
                    <flux:table.column>{{ __('Supervisor') }}</flux:table.column>
                    <flux:table.column>{{ __('Rated by') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->assessments as $assessment)
                        <flux:table.row :key="$assessment->id">
                            <flux:table.cell>
                                <div class="w-56 truncate" title="{{ $assessment->employee->full_name }}">
                                    {{ $assessment->employee->listing_name }}
                                </div>

                                <div class="mt-1 flex gap-1">
                                    {{-- array_key_exists, not isset: isset on a computed
                                         property goes through __isset and never reaches it. --}}
                                    @unless (array_key_exists($assessment->position_id ?? 0, $this->positionsWithTechnical))
                                        <flux:badge size="sm" color="amber">{{ __('No technical') }}</flux:badge>
                                    @endunless

                                    @if ($assessment->employee->user_id === null)
                                        <flux:badge size="sm" color="zinc">{{ __('No account') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="w-40 truncate" title="{{ $assessment->employee->section?->name }}">
                                    {{ $assessment->employee->section?->name ?? '—' }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$assessment->isSelfSubmitted() ? 'green' : 'zinc'">
                                    {{ $assessment->isSelfSubmitted() ? __('Submitted') : __('Not yet') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$assessment->isRated() ? 'green' : 'zinc'">
                                    {{ $assessment->isRated() ? __('Rated') : __('Not yet') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="w-40 truncate" title="{{ $this->raters[$assessment->id] }}">
                                    {{ $this->raters[$assessment->id] }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    @if (array_key_exists($assessment->id, $this->hrRates))
                                        <flux:button size="sm" variant="ghost" :href="route('ldna.rate', $assessment)" wire:navigate>
                                            {{ __('Rate') }}
                                        </flux:button>
                                    @endif

                                    @unless ($cycle->hasClosed())
                                        <flux:tooltip :content="__('Refresh from their position now')">
                                            <flux:button size="sm" variant="ghost" icon="arrow-path" square
                                                wire:click="confirmRefresh({{ $assessment->id }})"
                                                :aria-label="__('Refresh from their position now')" />
                                        </flux:tooltip>
                                    @endunless
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __('Nobody matches.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
    </div>
    @else
        <div class="space-y-6">
            <div class="hidden print:block">
                <flux:heading size="xl">{{ __('LDNA :year — gaps', ['year' => $cycle->year]) }}</flux:heading>
            </div>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center print:hidden">
                <flux:select size="sm" class="lg:w-64" wire:model.live="gapDivision">
                    <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
                    @foreach ($this->divisions as $division)
                        <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select size="sm" class="lg:w-64" wire:model.live="gapSection">
                    <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
                    @foreach ($this->gapSections as $section)
                        <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:spacer />

                <flux:button size="sm" icon="printer" x-on:click="window.print()">{{ __('Print') }}</flux:button>
            </div>

            @if ($this->gaps === [])
                <flux:callout icon="chart-bar-square" variant="secondary">
                    {{ __('Nobody here has been rated by a supervisor yet.') }}
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Competency') }}</flux:table.column>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Rated') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Short') }}</flux:table.column>
                        <flux:table.column class="text-right">%</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Avg. levels short') }}</flux:table.column>
                        <flux:table.column>{{ __('LDI plans in :year', ['year' => $cycle->year]) }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->gaps as $row)
                            @php($unanswered = $row['with_gap'] > 0 && $row['plans'] === 0)

                            <flux:table.row :key="'gap-'.$row['competency_id']"
                                :class="$unanswered ? 'bg-amber-50 dark:bg-amber-500/10' : ''">
                                <flux:table.cell>
                                    {{-- A button, not a link: it opens the names under the row. --}}
                                    <button type="button" wire:click="toggle({{ $row['competency_id'] }})"
                                        class="block w-72 cursor-pointer truncate text-left text-(--color-accent-content)"
                                        title="{{ $row['competency'] }}" @disabled($row['with_gap'] === 0)>
                                        {{ $row['competency'] }}
                                    </button>
                                </flux:table.cell>
                                <flux:table.cell>{{ $row['type']->label() }}</flux:table.cell>
                                <flux:table.cell class="text-right tabular-nums">{{ $row['rated'] }}</flux:table.cell>
                                <flux:table.cell class="text-right tabular-nums">{{ $row['with_gap'] }}</flux:table.cell>
                                <flux:table.cell class="text-right tabular-nums">{{ number_format($row['percentage'], 1) }}</flux:table.cell>
                                <flux:table.cell class="text-right tabular-nums">{{ number_format($row['average_gap'], 1) }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($unanswered)
                                        {{-- Said in words as well as colour. --}}
                                        <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ __('No plan') }}</flux:badge>
                                    @else
                                        <span class="tabular-nums">{{ $row['plans'] }}</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>

                            @if ($expanded === $row['competency_id'])
                                <flux:table.row :key="'gap-people-'.$row['competency_id']">
                                    <flux:table.cell colspan="7">
                                        <div class="divide-y divide-zinc-200 dark:divide-white/10">
                                            @foreach ($row['people'] as $person)
                                                <div class="flex flex-wrap items-baseline justify-between gap-3 py-2">
                                                    <div class="w-64 truncate text-sm" title="{{ $person['employee'] }}">{{ $person['employee'] }}</div>
                                                    <div class="w-48 truncate text-xs text-zinc-600 dark:text-zinc-300" title="{{ $person['section'] }}">{{ $person['section'] }}</div>
                                                    <div class="text-xs tabular-nums">
                                                        {{ __(':rating of :required required', [
                                                            'rating' => $person['rating']?->label(),
                                                            'required' => $person['required']->label(),
                                                        ]) }}
                                                    </div>
                                                    <flux:badge size="sm" color="amber">
                                                        {{ trans_choice('{1} 1 level short|[2,*] :count levels short', $person['gap'], ['count' => $person['gap']]) }}
                                                    </flux:badge>
                                                </div>
                                            @endforeach
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endif
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif

    <flux:modal name="ldna-sync" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Add new people to LDNA :year?', ['year' => $cycle->year]) }}</flux:heading>

            <flux:text>
                {{ __('Everybody active who is not in it yet gets an assessment and is told the dates. Nobody already in it is changed.') }}
            </flux:text>

            <flux:error name="cycle" />

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" wire:click="syncCycle">{{ __('Add them') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="ldna-refresh" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        @if ($this->refreshing)
            <div class="space-y-6">
                <flux:heading size="lg">
                    {{ __('Refresh :name?', ['name' => $this->refreshing->employee->listing_name]) }}
                </flux:heading>

                <flux:text>
                    {{ __('Their competencies are read again from their position and designation now. Ratings of a competency they still have are kept. A new one arrives unrated, and anything submitted is reopened.') }}
                </flux:text>

                <flux:error name="cycle" />

                <div class="flex gap-2">
                    <flux:spacer />

                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" wire:click="refreshAssessment">{{ __('Refresh') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="ldna-extend" class="md:w-2xl md:max-w-[calc(100vw-4rem)]">
        <form wire:submit="extend" class="space-y-6">
            <flux:heading size="lg">{{ __('Change the closing date') }}</flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input :label="__('Opens on')" type="date" :value="$cycle->opens_on->toDateString()" disabled />
                <flux:input wire:model="closesOn" :label="__('Closes on')" type="date" required />
            </div>

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
