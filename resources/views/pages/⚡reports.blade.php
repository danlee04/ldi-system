<?php

use App\Actions\Reports\ApprovalsAgingReport;
use App\Actions\Reports\BudgetUtilizationReport;
use App\Actions\Reports\CostPerParticipantReport;
use App\Actions\Reports\CoverageByDivisionReport;
use App\Actions\Reports\EmployeesWithoutTrainingReport;
use App\Actions\Reports\FundUtilizationByDivisionReport;
use App\Actions\Reports\LdiAccomplishmentReport;
use App\Actions\Reports\MonthlyActivityReport;
use App\Actions\Reports\RepeatAttendanceReport;
use App\Models\Division;
use App\Models\Section;
use App\Models\TrainingRecord;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Reports')] class extends Component {
    use WithPagination;

    #[Url]
    public string $report = 'activity';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $month = null;

    #[Url]
    public string $quarter = '';

    #[Url]
    public string $divisionId = '';

    #[Url]
    public string $sectionId = '';

    /**
     * Rows to a page.
     */
    private const PER_PAGE = 25;

    /**
     * Every report this page offers, and what it is called on screen.
     *
     * @var array<string, string>
     */
    public const REPORTS = [
        'activity' => 'Monthly activity',
        'without' => 'Without training',
        'coverage' => 'Coverage by division',
        'repeat' => 'Repeat and first-timers',
        'accomplishment' => 'LDI accomplishment',
        'budget' => 'Budget utilization',
        'funds' => 'Fund utilization by division',
        'providers' => 'Cost per participant',
        'aging' => 'Approvals aging',
    ];

    /**
     * A page of a report that has already been worked out in full.
     *
     * The reports total and export themselves from every row, so
     * this slices for the screen only — the CSV and the figures
     * above each table still come from all of them.
     *
     * @param  Collection<int, mixed>|array<int, mixed>  $rows
     * @return LengthAwarePaginator<int, mixed>
     */
    public function paginated(Collection|array $rows): LengthAwarePaginator
    {
        $rows = collect($rows);
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Changing what is being read starts it from the top, or the
     * table would open on a page that no longer exists.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $this->year ??= now()->year;
        $this->month ??= now()->month;
    }

    /**
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function activity(): Collection
    {
        return app(MonthlyActivityReport::class)->handle($this->year, $this->month);
    }

    /**
     * @return array{attendances: int, trainings: int, employees: int, hours: int, cost: float}
     */
    #[Computed]
    public function activityTotals(): array
    {
        return app(MonthlyActivityReport::class)->summarise($this->activity);
    }

    /**
     * @return list<array{employee: \App\Models\Employee, last_training: \Carbon\CarbonImmutable|null}>
     */
    #[Computed]
    public function without(): array
    {
        return app(EmployeesWithoutTrainingReport::class)->handle(
            $this->year,
            $this->divisionId === '' ? null : (int) $this->divisionId,
            $this->sectionId === '' ? null : (int) $this->sectionId,
        );
    }

    /**
     * @return array{without: int, active: int, never: int}
     */
    #[Computed]
    public function withoutTotals(): array
    {
        return app(EmployeesWithoutTrainingReport::class)->summarise($this->without);
    }

    /**
     * @return list<array{source: string, cap: float|null, committed: float, spent: float, plans: int}>
     */
    #[Computed]
    public function budget(): array
    {
        return app(BudgetUtilizationReport::class)->handle($this->year);
    }

    /**
     * @return list<array{plan: \App\Models\LdiTraining, attendees: int, spent: float}>
     */
    #[Computed]
    public function accomplishment(): array
    {
        return app(LdiAccomplishmentReport::class)->handle(
            $this->year,
            $this->quarter === '' ? null : (int) $this->quarter,
        );
    }

    /**
     * @return array{plans: int, attendees: int, hours: int, spent: float}
     */
    #[Computed]
    public function accomplishmentTotals(): array
    {
        return app(LdiAccomplishmentReport::class)->summarise($this->accomplishment);
    }

    /**
     * @return list<array{division: string, quarters: array<int, float>, total: float, attendances: int}>
     */
    #[Computed]
    public function funds(): array
    {
        return app(FundUtilizationByDivisionReport::class)->handle($this->year);
    }

    /**
     * @return list<array{division: string, employees: int, covered: int, percentage: float}>
     */
    #[Computed]
    public function coverage(): array
    {
        return app(CoverageByDivisionReport::class)->handle($this->year);
    }

    /**
     * @return array{employees: int, covered: int, percentage: float}
     */
    #[Computed]
    public function coverageTotals(): array
    {
        return app(CoverageByDivisionReport::class)->summarise($this->coverage);
    }

    /**
     * @return list<array{employee: \App\Models\Employee, attendances: int, earlier: int, first_timer: bool}>
     */
    #[Computed]
    public function repeat(): array
    {
        return app(RepeatAttendanceReport::class)->handle($this->year);
    }

    /**
     * @return array{attendees: int, first_timers: int, repeats: int}
     */
    #[Computed]
    public function repeatTotals(): array
    {
        return app(RepeatAttendanceReport::class)->summarise($this->repeat);
    }

    /**
     * @return list<array{provider: string, attendances: int, employees: int, hours: int, cost: float, per_participant: float}>
     */
    #[Computed]
    public function providers(): array
    {
        return app(CostPerParticipantReport::class)->handle($this->year);
    }

    /**
     * @return array{providers: int, attendances: int, cost: float, per_participant: float}
     */
    #[Computed]
    public function providerTotals(): array
    {
        return app(CostPerParticipantReport::class)->summarise($this->providers);
    }

    /**
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function aging(): Collection
    {
        return app(ApprovalsAgingReport::class)->handle();
    }

    /**
     * @return array{pending: int, unroutable: int, longest: int}
     */
    #[Computed]
    public function agingTotals(): array
    {
        return app(ApprovalsAgingReport::class)->summarise($this->aging);
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed(persist: true)]
    public function divisions(): Collection
    {
        return Division::query()->orderBy('code')->get();
    }

    /**
     * Only the sections of the chosen division, so the two filters cannot
     * contradict each other.
     *
     * @return Collection<int, Section>
     */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()
            ->when($this->divisionId !== '', fn ($query) => $query->where('division_id', $this->divisionId))
            ->orderBy('name')
            ->get();
    }

    /**
     * A section outside the newly chosen division would filter everybody
     * out, so it is dropped rather than left to look broken.
     */
    public function updatedDivisionId(): void
    {
        $this->sectionId = '';
    }

    /**
     * The years worth offering: every year that has a training record,
     * plus this one, so the picker is never empty on a fresh install.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function years(): Collection
    {
        return TrainingRecord::query()
            ->pluck('date_end')
            ->map(fn ($date): int => (int) $date->format('Y'))
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function months(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [
                $month => now()->startOfYear()->addMonths($month - 1)->format('F'),
            ])
            ->all();
    }

    #[Computed]
    public function heading(): string
    {
        return match ($this->report) {
            'without' => __('Employees with no training in :year', ['year' => $this->year]),
            'budget' => __('Budget utilization for :year', ['year' => $this->year]),
            'coverage' => __('Training coverage by division, :year', ['year' => $this->year]),
            'repeat' => __('Repeat attendance and first-timers, :year', ['year' => $this->year]),
            'accomplishment' => __('LDI accomplishment, :period', ['period' => $this->period()]),
            'funds' => __('Fund utilization by division, :year', ['year' => $this->year]),
            'providers' => __('Cost per participant by provider, :year', ['year' => $this->year]),
            'aging' => __('Training still awaiting a decision'),
            default => __('Training activity for :month :year', [
                'month' => $this->months[$this->month] ?? '',
                'year' => $this->year,
            ]),
        };
    }

    public function download(): StreamedResponse
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        [$name, $rows] = match ($this->report) {
            'without' => [
                "employees-without-training-{$this->year}",
                app(EmployeesWithoutTrainingReport::class)->toRows($this->without),
            ],
            'budget' => [
                "budget-utilization-{$this->year}",
                app(BudgetUtilizationReport::class)->toRows($this->budget),
            ],
            'coverage' => [
                "coverage-by-division-{$this->year}",
                app(CoverageByDivisionReport::class)->toRows($this->coverage),
            ],
            'repeat' => [
                "repeat-and-first-timers-{$this->year}",
                app(RepeatAttendanceReport::class)->toRows($this->repeat),
            ],
            'accomplishment' => [
                'ldi-accomplishment-'.str($this->period())->slug(),
                app(LdiAccomplishmentReport::class)->toRows($this->accomplishment),
            ],
            'funds' => [
                "fund-utilization-by-division-{$this->year}",
                app(FundUtilizationByDivisionReport::class)->toRows($this->funds),
            ],
            'providers' => [
                "cost-per-participant-{$this->year}",
                app(CostPerParticipantReport::class)->toRows($this->providers),
            ],
            'aging' => [
                'approvals-aging-'.now()->format('Y-m-d'),
                app(ApprovalsAgingReport::class)->toRows($this->aging),
            ],
            default => [
                "training-activity-{$this->year}-{$this->month}",
                app(MonthlyActivityReport::class)->toRows($this->activity),
            ],
        };

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $name.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * What the accomplishment report covers, for the heading and the file
     * name: a quarter of a year, or the whole of it.
     */
    private function period(): string
    {
        return $this->quarter === ''
            ? (string) $this->year
            : 'Q'.$this->quarter.' '.$this->year;
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between print:hidden">
        <flux:heading size="xl">{{ __('Reports') }}</flux:heading>

        <flux:button variant="primary" icon="arrow-down-tray" wire:click="download">
            {{ __('Download CSV') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center print:hidden">
        <flux:select size="sm" class="lg:w-64" wire:model.live="report">
            @foreach ($this::REPORTS as $key => $label)
                <flux:select.option :value="$key">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($report === 'activity')
            <flux:select size="sm" class="lg:w-44" wire:model.live="month">
                @foreach ($this->months as $number => $name)
                    <flux:select.option :value="$number">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if ($report === 'accomplishment')
            <flux:select size="sm" class="lg:w-40" wire:model.live="quarter">
                <flux:select.option value="">{{ __('Whole year') }}</flux:select.option>
                @foreach ([1, 2, 3, 4] as $option)
                    <flux:select.option :value="$option">{{ __('Quarter :n', ['n' => $option]) }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if ($report === 'without')
            <flux:select size="sm" class="lg:w-64" wire:model.live="divisionId">
                <flux:select.option value="">{{ __('All divisions') }}</flux:select.option>
                @foreach ($this->divisions as $division)
                    <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select size="sm" class="lg:w-64" wire:model.live="sectionId">
                <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
                @foreach ($this->sections as $section)
                    <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif


        @if ($report !== 'aging')
            <flux:select size="sm" class="lg:w-32" wire:model.live="year">
                @foreach ($this->years as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    {{-- Only this heading survives printing, so the paper says what it is. --}}
    <div class="hidden print:block">
        <flux:heading size="lg">{{ config('app.name') }}</flux:heading>
        <flux:text>{{ $this->heading }}</flux:text>
        <flux:text size="sm">
            {{ __('Generated on :date by :user', [
                'date' => now()->format('d M Y, g:i a'),
                'user' => auth()->user()->name,
            ]) }}
        </flux:text>
    </div>

    @switch ($report)
        @case('without')
            <x-reports.without :rows="$this->paginated($this->without)" :totals="$this->withoutTotals" :year="$year" />
            @break

        @case('coverage')
            <x-reports.coverage :rows="$this->paginated($this->coverage)" :totals="$this->coverageTotals" />
            @break

        @case('repeat')
            <x-reports.repeat :rows="$this->paginated($this->repeat)" :totals="$this->repeatTotals" />
            @break

        @case('accomplishment')
            <x-reports.accomplishment :rows="$this->paginated($this->accomplishment)" :totals="$this->accomplishmentTotals" />
            @break

        @case('budget')
            <x-reports.budget :rows="$this->paginated($this->budget)" />
            @break

        @case('funds')
            <x-reports.funds :rows="$this->paginated($this->funds)" />
            @break

        @case('providers')
            <x-reports.providers :rows="$this->paginated($this->providers)" :totals="$this->providerTotals" />
            @break

        @case('aging')
            <x-reports.aging :records="$this->paginated($this->aging)" :totals="$this->agingTotals" />
            @break

        @default
            <x-reports.activity :records="$this->paginated($this->activity)" :totals="$this->activityTotals" />
    @endswitch
</div>
