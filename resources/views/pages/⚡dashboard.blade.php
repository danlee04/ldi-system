<?php

use App\Actions\Pds\PersonalDataSheetProgress;
use Carbon\CarbonImmutable;
use App\Models\LdiTraining;
use App\Actions\Reports\TrainingByMonthReport;
use App\Actions\Reports\CoverageByDivisionReport;
use App\Actions\Calendar\BuildCalendarMonth;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Actions\Reports\AgencyTotalsReport;
use App\Actions\Reports\ApprovalsAgingReport;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeEligibility;
use App\Models\TrainingRecord;
use App\Workflow\ApprovalRouter;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public int $myPending = 0;

    public int $myApproved = 0;

    public int $unroutable = 0;

    public function mount(): void
    {
        $this->chartYear = $this->chartYear > 0 ? $this->chartYear : $this->year();

        $user = auth()->user();
        $employee = $user->employee;

        if ($employee instanceof Employee) {
            $this->myPending = $employee->trainingRecords()->pending()->count();
            $this->myApproved = $employee->trainingRecords()->approved()->count();
        }

        if ($user->isAdminOrHr()) {
            $this->unroutable = TrainingRecord::query()->unroutable()->count();
        }
    }

    #[Computed(persist: true)]
    public function employee(): ?Employee
    {
        return auth()->user()->employee;
    }

    /**
     * What their approved training counts for this year.
     */
    #[Computed]
    public function cpdUnits(): float
    {
        return (float) $this->employee?->trainingRecords()->where('status', TrainingStatus::Approved)->whereYear('date_end', now()->year)->sum('cpd_units');
    }

    #[Computed]
    public function approvedThisYear(): int
    {
        return (int) $this->employee?->trainingRecords()->where('status', TrainingStatus::Approved)->whereYear('date_end', now()->year)->count();
    }

    /**
     * Their own submissions still waiting, oldest first.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function mySubmissions(): Collection
    {
        return $this->employee?->trainingRecords()->pending()->orderBy('created_at')->get() ?? collect();
    }

    /**
     * Who a record of theirs is sitting with, by name — the question an
     * employee actually asks. A level on its own does not answer it.
     */
    public function waitingOn(TrainingRecord $record): string
    {
        if ($record->current_level === null || $this->employee === null) {
            return __('Nobody yet — no head is designated');
        }

        $approver = app(ApprovalRouter::class)->approverFor($record->current_level, $this->employee);

        return $approver instanceof Employee ? $approver->listing_name . ' (' . $record->current_level->label() . ')' : $record->current_level->label();
    }

    public function daysWaiting(TrainingRecord $record): int
    {
        return (int) $record->created_at?->diffInDays(now());
    }

    /**
     * Their own eligibility that has lapsed or is about to.
     *
     * @return Collection<int, EmployeeEligibility>
     */
    #[Computed]
    public function eligibilityAlerts(): Collection
    {
        return $this->employee?->eligibilities->filter(fn(EmployeeEligibility $line): bool => $line->date_of_validity !== null && $line->date_of_validity->lessThanOrEqualTo(today()->addYear()))->values() ?? collect();
    }

    /**
     * @return list<array{number: string, label: string, filled: bool}>
     */
    #[Computed]
    public function pdsSections(): array
    {
        return $this->employee === null ? [] : app(PersonalDataSheetProgress::class)->handle($this->employee);
    }

    #[Computed]
    public function pdsPercentage(): int
    {
        return app(PersonalDataSheetProgress::class)->percentage($this->pdsSections);
    }

    /**
     * The agency-wide half of this page is HR and admin only. Everything
     * below is guarded by it rather than by a role check per panel.
     */
    #[Computed]
    public function seesAgency(): bool
    {
        return auth()->user()->isAdminOrHr();
    }

    public function year(): int
    {
        return now()->year;
    }

    /**
     * @return array{total: int, rows: list<array{label: string, count: int}>}
     */
    #[Computed]
    public function employeeTotals(): array
    {
        return app(AgencyTotalsReport::class)->employeesByDivision();
    }

    /**
     * @return array{total: int, rows: list<array{label: string, count: int}>}
     */
    #[Computed]
    public function planTotals(): array
    {
        return app(AgencyTotalsReport::class)->plansByCommunication($this->year());
    }

    /**
     * @return array{total: float, hr: float, other: float}
     */
    #[Computed]
    public function fundingTotals(): array
    {
        return app(AgencyTotalsReport::class)->fundingBySource($this->year());
    }
    /**
     * The year's coverage as one figure. The panel below breaks it down by
     * division; this is the number the office is asked for.
     *
     * @return array{covered: int, employees: int, percentage: int}
     */
    #[Computed]
    public function coverageTotal(): array
    {
        $employees = array_sum(array_column($this->coverage, 'employees'));
        $covered = array_sum(array_column($this->coverage, 'covered'));

        return [
            'covered' => $covered,
            'employees' => $employees,
            'percentage' => $employees === 0 ? 0 : (int) round(($covered / $employees) * 100),
        ];
    }

    /**
     * @return list<array{month: int, label: string, attendances: int}>
     */
    #[Computed]
    public function months(): array
    {
        $report = app(TrainingByMonthReport::class);

        return $this->chartMonth === null
            ? $report->handle($this->chartYear, $this->chartDivision)
            : $report->forMonth($this->chartYear, $this->chartMonth, $this->chartDivision);
    }

    #[Computed]
    public function monthPeak(): int
    {
        return app(TrainingByMonthReport::class)->peak($this->months);
    }

    /**
     * The three ways the chart can be narrowed. All of them live in the
     * link, so a head can send somebody a division's year.
     *
     * The chart's year is its own: the cards and the panels beside it
     * report on the year that is running, and a chart looking back at 2025
     * should not quietly change what they say.
     */
    #[Url]
    public ?int $chartDivision = null;

    #[Url]
    public ?int $chartMonth = null;

    #[Url]
    public int $chartYear = 0;

    public function updatedChartDivision(): void
    {
        $this->forgetChart();
    }

    public function updatedChartMonth(): void
    {
        $this->forgetChart();
    }

    public function updatedChartYear(): void
    {
        $this->forgetChart();
    }

    private function forgetChart(): void
    {
        unset($this->months, $this->monthPeak);
    }

    /**
     * Every year with something in it, and the one that is running even
     * when nothing has been recorded in it yet.
     *
     * @return list<int>
     */
    #[Computed]
    public function chartYears(): array
    {
        $years = TrainingRecord::query()
            ->where('status', TrainingStatus::Approved)
            ->pluck('date_end')
            ->map(fn (CarbonImmutable $date): int => $date->year)
            ->push($this->year())
            ->unique()
            ->sortDesc()
            ->values();

        return $years->all();
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed]
    public function chartDivisions(): Collection
    {
        return Division::query()->orderBy('name')->get();
    }

    /**
     * @return list<array{division: string, employees: int, covered: int, percentage: float}>
     */
    #[Computed]
    public function coverage(): array
    {
        return app(CoverageByDivisionReport::class)->handle($this->year());
    }

    /**
     * The mix of learning and development types.
     *
     * Drawn as emphasis rather than as a categorical set: one type carries
     * almost all of it, and that imbalance is the point of the panel.
     *
     * @return list<array{label: string, attendances: int, share: float}>
     */
    #[Computed]
    public function ldMix(): array
    {
        $records = TrainingRecord::query()->where('status', TrainingStatus::Approved)->whereYear('date_end', $this->year())->get();

        $total = max(1, $records->count());

        return $records
            ->groupBy(fn(TrainingRecord $record): string => $record->ld_type->label())
            ->map->count()
            ->sortDesc()
            ->map(
                fn(int $count, string $label): array => [
                    'label' => $label,
                    'attendances' => $count,
                    'share' => round(($count / $total) * 100, 1),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * Where the year's money went, in the three parts a record records.
     *
     * @return array{registration: float, tev: float, other: float, total: float}
     */
    #[Computed]
    public function spend(): array
    {
        $records = TrainingRecord::query()->where('status', TrainingStatus::Approved)->whereYear('date_end', $this->year())->get();

        $parts = [
            'registration' => (float) $records->sum(fn(TrainingRecord $r): float => (float) $r->registration_fee),
            'tev' => (float) $records->sum(fn(TrainingRecord $r): float => (float) $r->tev),
            'other' => (float) $records->sum(fn(TrainingRecord $r): float => (float) $r->expenses),
        ];

        return [...$parts, 'total' => array_sum($parts)];
    }

    /**
     * The longest waits, not all of them — the whole list is a report.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function aging(): Collection
    {
        return app(ApprovalsAgingReport::class)->handle()->take(5);
    }

    /**
     * @return Collection<int, LdiTraining>
     */
    #[Computed]
    public function upcomingPlans(): Collection
    {
        return LdiTraining::query()->where('date_start', '>=', today())->orderBy('date_start')->limit(5)->get();
    }

    /**
     * @return array{active: int, statuses: array<string, int>, plans: int}
     */
    #[Computed]
    public function quickStats(): array
    {
        return [
            'active' => Employee::query()->active()->count(),
            'statuses' => Employee::query()->active()->get()->groupBy(fn(Employee $employee): string => $employee->employment_status->label())->map->count()->sortDesc()->all(),
            'plans' => LdiTraining::query()->whereYear('date_start', $this->year())->count(),
        ];
    }

    /**
     * Everybody whose eligibility has lapsed or is about to, soonest first.
     *
     * @return Collection<int, EmployeeEligibility>
     */
    #[Computed]
    public function agencyEligibilityAlerts(): Collection
    {
        return EmployeeEligibility::query()
            ->whereNotNull('date_of_validity')
            ->where('date_of_validity', '<=', today()->addYear())
            ->with('employee', 'eligibility')
            ->orderBy('date_of_validity')
            ->get();
    }

    /**
     * The month the calendar is showing, as an offset from this one so the
     * component holds a number rather than a date it has to re-parse.
     */
    public int $monthOffset = 0;

    public function previousMonth(): void
    {
        $this->monthOffset--;

        unset($this->calendar);
    }

    public function nextMonth(): void
    {
        $this->monthOffset++;

        unset($this->calendar);
    }

    /**
     * The visible month, drawn the same way the calendar page draws it:
     * bars across the days a thing runs, in the same colours. The rail is
     * too narrow for titles, so the bars carry theirs on hover and the
     * legend under them says what the colours mean.
     *
     * @return array{month: CarbonImmutable, weeks: list<array{days: list<array{day: int|null, date: CarbonImmutable|null}>, bars: list<array<string, mixed>>, lanes: int}>, legend: list<array{label: string, classes: string}>}
     */
    #[Computed]
    public function calendar(): array
    {
        $month = CarbonImmutable::today()->startOfMonth()->addMonths($this->monthOffset);
        $until = $month->endOfMonth();

        $entries = [];
        $legend = [];

        foreach (Activity::query()->overlapping($month, $until)->orderBy('date_start')->get() as $activity) {
            $entries[] = [
                'key' => 'activity-' . $activity->getKey(),
                'kind' => 'activity',
                'id' => $activity->getKey(),
                'title' => $activity->title,
                'classes' => $activity->type->chipClasses(),
                'start' => $activity->date_start,
                'end' => $activity->date_end,
            ];

            $legend[$activity->type->value] = [
                'label' => $activity->type->label(),
                'classes' => $activity->type->chipClasses(),
            ];
        }

        $plans = LdiTraining::query()->whereDate('date_start', '<=', $until)->whereDate('date_end', '>=', $month)->orderBy('date_start')->get();

        foreach ($plans as $plan) {
            $entries[] = [
                'key' => 'plan-' . $plan->getKey(),
                'kind' => 'plan',
                'id' => $plan->getKey(),
                'title' => $plan->title,
                'classes' => ActivityType::PLAN_CHIP,
                'start' => $plan->date_start,
                'end' => $plan->date_end,
            ];
        }

        if ($plans->isNotEmpty()) {
            $legend['plan'] = ['label' => __('LDI training'), 'classes' => ActivityType::PLAN_CHIP];
        }

        return [
            'month' => $month,
            'weeks' => app(BuildCalendarMonth::class)->handle($month, $entries),
            // Only the kinds actually on show, so the rail is not explaining
            // a colour the month does not use.
            'legend' => array_values($legend),
        ];
    }

    /**
     * @return list<array{number: string, label: string, filled: bool}>
     */
    #[Computed]
    public function pdsMissing(): array
    {
        return array_values(array_filter($this->pdsSections, fn(array $section): bool => !$section['filled']));
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary/10 text-brand-primary dark:bg-brand-primary/20 dark:text-blue-200">
                <flux:icon.squares-2x2 variant="mini" />
            </span>

            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        </div>

        <div class="flex items-center gap-3">
            <flux:text size="sm">{{ today()->format('l, j F Y') }}</flux:text>

            <livewire:notifications />
        </div>
    </div>

    @if ($this->eligibilityAlerts->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Check your eligibility')">
            <div class="space-y-1">
                @foreach ($this->eligibilityAlerts as $line)
                    <div class="flex flex-wrap items-center gap-2">
                        <span>{{ $line->name() }}</span>
                        <x-eligibility-expiry :date="$line->date_of_validity" />
                    </div>
                @endforeach
            </div>
        </flux:callout>
    @endif

    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(13rem,100%),1fr))]">
        @if ($this->employee)
            <flux:card class="space-y-1">
                <flux:text size="sm">{{ __('My pending trainings') }}</flux:text>
                <flux:heading size="xl" class="tabular-nums">{{ $myPending }}</flux:heading>
                <flux:link :href="route('trainings.mine')" wire:navigate>{{ __('View mine') }}</flux:link>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text size="sm">{{ __('My approved trainings') }}</flux:text>
                <flux:heading size="xl" class="tabular-nums">{{ $myApproved }}</flux:heading>
                <flux:text size="sm">
                    {{ __(':count this year', ['count' => $this->approvedThisYear]) }}
                </flux:text>
            </flux:card>

            <flux:card class="space-y-1">
                <flux:text size="sm">{{ __('CPD units this year') }}</flux:text>
                <flux:heading size="xl" class="tabular-nums">
                    {{ rtrim(rtrim(number_format($this->cpdUnits, 1), '0'), '.') }}
                </flux:heading>
            </flux:card>
        @endif

    </div>

    @if ($this->employee)
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
                    <flux:text size="sm">{{ __('Every section has something in it.') }}</flux:text>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->pdsMissing as $section)
                            <flux:badge color="zinc">{{ $section['number'] }}. {{ $section['label'] }}</flux:badge>
                        @endforeach
                    </div>
                @endif

                <div>
                    <flux:button size="sm" variant="primary" :href="route('my-pds')" wire:navigate>
                        {{ __('Fill it in') }}
                    </flux:button>
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Where my submissions stand') }}</flux:heading>

                @if ($this->mySubmissions->isEmpty())
                    <flux:text size="sm">
                        {{ __('Nothing is waiting on anybody. Record a training under My trainings.') }}
                    </flux:text>
                @else
                    <div class="divide-y divide-zinc-200 dark:divide-white/10">
                        @foreach ($this->mySubmissions as $record)
                            <div class="space-y-1 py-3 first:pt-0 last:pb-0">
                                <flux:heading class="break-words">{{ $record->title }}</flux:heading>

                                <flux:text size="sm">
                                    {{ __('With :approver', ['approver' => $this->waitingOn($record)]) }}
                                </flux:text>

                                <flux:text size="sm">
                                    {{ trans_choice('Waiting :count day|Waiting :count days', $this->daysWaiting($record), [
                                        'count' => $this->daysWaiting($record),
                                    ]) }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>
    @endif

    @if ($this->seesAgency)
        <x-dashboard.totals :employees="$this->employeeTotals" :plans="$this->planTotals" :coverage="$this->coverageTotal" :funding="$this->fundingTotals" :spend="$this->spend"
            :year="$this->year()" />

        {{-- A rail of a fixed width rather than a third of the screen: it
             holds a month and two short lists, and everything it does not
             need belongs to the panels beside it. --}}
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_28rem]">
            <div class="space-y-6">
                <x-dashboard.monthly-training :months="$this->months" :peak="$this->monthPeak"
                    :divisions="$this->chartDivisions" :years="$this->chartYears"
                    :month="$this->chartMonth" :year="$this->chartYear" />

                {{-- Two small panels of the same kind of question: what the
                     year was made of, and who it reached. --}}
                <div class="grid gap-6 xl:grid-cols-2">
                    <x-dashboard.ld-mix :rows="$this->ldMix" :year="$this->year()" />
                    <x-dashboard.coverage :rows="$this->coverage" :year="$this->year()" />
                </div>

                <flux:card class="space-y-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <flux:heading size="lg">{{ __('Waiting longest') }}</flux:heading>
                        <flux:link :href="route('reports')" wire:navigate>{{ __('Full report') }}</flux:link>
                    </div>

                    @if ($this->aging->isEmpty())
                        <flux:text size="sm">{{ __('Nothing is waiting for a decision.') }}</flux:text>
                    @else
                        <div class="divide-y divide-zinc-200 dark:divide-white/10">
                            @foreach ($this->aging as $record)
                                <div class="flex flex-wrap items-start justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm" title="{{ $record->title }}">
                                            {{ $record->title }}
                                        </div>
                                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $record->employee?->listing_name ?? '—' }}
                                        </div>
                                    </div>

                                    @if ($record->current_level === null)
                                        <flux:badge color="red">{{ __('No approver') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber">
                                            {{ trans_choice(':count day|:count days', $this->daysWaiting($record), [
                                                'count' => $this->daysWaiting($record),
                                            ]) }}
                                        </flux:badge>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>

                <flux:card class="space-y-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <flux:heading size="lg">{{ __('Upcoming LDI training') }}</flux:heading>
                        <flux:link :href="route('ldi.index')" wire:navigate>{{ __('All plans') }}</flux:link>
                    </div>

                    @if ($this->upcomingPlans->isEmpty())
                        <flux:text size="sm">
                            {{ __('Nothing is planned from today onward. Add a plan under LDI trainings so it reaches the calendar and the attendees.') }}
                        </flux:text>
                    @else
                        <div class="divide-y divide-zinc-200 dark:divide-white/10">
                            @foreach ($this->upcomingPlans as $plan)
                                <div class="flex flex-wrap items-start justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm" title="{{ $plan->title }}">{{ $plan->title }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $plan->inclusive_dates }}
                                        </div>
                                    </div>

                                    <flux:badge color="zinc">
                                        {{ trans_choice(':count seat|:count seats', $plan->target_attendees ?? 0, [
                                            'count' => $plan->target_attendees ?? 0,
                                        ]) }}
                                    </flux:badge>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="space-y-6">
                <x-dashboard.calendar :calendar="$this->calendar" />
                <x-dashboard.quick-stats :stats="$this->quickStats" :year="$this->year()" />
                <x-dashboard.eligibility-alerts :lines="$this->agencyEligibilityAlerts" />
            </div>
        </div>
    @endif

    @if ($unroutable > 0)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ trans_choice(
                ':count training record cannot move because no head is designated.|:count training records cannot move because no head is designated.',
                $unroutable,
                ['count' => $unroutable],
            ) }}

            <flux:link :href="route('approvals')" wire:navigate>{{ __('Go to approvals') }}</flux:link>
        </flux:callout>
    @endif
</div>
