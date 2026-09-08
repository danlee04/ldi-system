<?php

use App\Actions\Reports\BudgetUtilizationReport;
use App\Actions\Reports\EmployeesWithoutTrainingReport;
use App\Actions\Reports\MonthlyActivityReport;
use App\Models\TrainingRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Reports')] class extends Component {
    #[Url]
    public string $report = 'activity';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $month = null;

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
     * @return Collection<int, \App\Models\Employee>
     */
    #[Computed]
    public function without(): Collection
    {
        return app(EmployeesWithoutTrainingReport::class)->handle($this->year);
    }

    /**
     * @return array{without: int, active: int}
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
            default => [
                "training-activity-{$this->year}-{$this->month}",
                app(MonthlyActivityReport::class)->toRows($this->activity),
            ],
        };

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $name.'.csv', ['Content-Type' => 'text/csv']);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between print:hidden">
        <flux:heading size="xl">{{ __('Reports') }}</flux:heading>

        <flux:button variant="primary" icon="arrow-down-tray" wire:click="download">
            {{ __('Download CSV') }}
        </flux:button>
    </div>

    <div class="print:hidden">
        <flux:radio.group wire:model.live="report" variant="segmented">
            <flux:radio value="activity" :label="__('Monthly activity')" />
            <flux:radio value="without" :label="__('Without training')" />
            <flux:radio value="budget" :label="__('Budget utilization')" />
        </flux:radio.group>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center print:hidden">
        @if ($report === 'activity')
            <flux:select size="sm" class="lg:w-44" wire:model.live="month">
                @foreach ($this->months as $number => $name)
                    <flux:select.option :value="$number">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select size="sm" class="lg:w-32" wire:model.live="year">
            @foreach ($this->years as $option)
                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Only this heading survives printing, so the paper says what it is. --}}
    <div class="hidden print:block">
        <flux:heading size="lg">{{ config('app.name') }}</flux:heading>
        <flux:text>{{ $this->heading }}</flux:text>
    </div>

    @if ($report === 'activity')
        <div class="grid gap-4 md:grid-cols-4">
            <flux:card>
                <flux:text size="sm">{{ __('Attendances') }}</flux:text>
                <flux:heading size="xl">{{ $this->activityTotals['attendances'] }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:text size="sm">{{ __('Employees') }}</flux:text>
                <flux:heading size="xl">{{ $this->activityTotals['employees'] }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:text size="sm">{{ __('Hours') }}</flux:text>
                <flux:heading size="xl">{{ number_format($this->activityTotals['hours']) }}</flux:heading>
            </flux:card>
            <flux:card>
                <flux:text size="sm">{{ __('Cost') }}</flux:text>
                <flux:heading size="xl">{{ number_format($this->activityTotals['cost'], 2) }}</flux:heading>
            </flux:card>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Division') }}</flux:table.column>
                <flux:table.column>{{ __('Employee') }}</flux:table.column>
                <flux:table.column>{{ __('Training') }}</flux:table.column>
                <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Hours') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Cost') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->activity as $record)
                    <flux:table.row :key="$record->id">
                        <flux:table.cell>{{ $record->employee->division?->code ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="w-52 truncate" title="{{ $record->employee->full_name }}">
                                {{ $record->employee->listing_name }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="w-64 truncate" title="{{ $record->title }}">{{ $record->title }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $record->inclusive_dates }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ $record->hours }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">
                            {{ number_format((float) $record->registration_fee + (float) $record->tev + (float) $record->expenses, 2) }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            {{ __('No training ended in this month.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @elseif ($report === 'without')
        <flux:callout icon="exclamation-triangle" variant="warning">
            {{ __(':without of :active active employees finished no approved training in :year.', [
                'without' => $this->withoutTotals['without'],
                'active' => $this->withoutTotals['active'],
                'year' => $this->year,
            ]) }}
        </flux:callout>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Division') }}</flux:table.column>
                <flux:table.column>{{ __('Section') }}</flux:table.column>
                <flux:table.column>{{ __('Employee no.') }}</flux:table.column>
                <flux:table.column>{{ __('Employee') }}</flux:table.column>
                <flux:table.column>{{ __('Position') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->without as $employee)
                    <flux:table.row :key="$employee->id">
                        <flux:table.cell>{{ $employee->division?->code ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="w-48 truncate" title="{{ $employee->section?->name }}">
                                {{ $employee->section?->name ?? '—' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $employee->employee_number }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="w-52 truncate" title="{{ $employee->full_name }}">
                                {{ $employee->listing_name }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="w-48 truncate" title="{{ $employee->position?->title }}">
                                {{ $employee->position?->title ?? '—' }}
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            {{ __('Everybody has training on record for this year.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Budget source') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Plans') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Cap') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Committed') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Spent') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Unspent') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->budget as $row)
                    <flux:table.row :key="$row['source']">
                        <flux:table.cell>{{ $row['source'] }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ $row['plans'] }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">
                            {{ $row['cap'] === null ? '—' : number_format($row['cap'], 2) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">
                            {{ number_format($row['committed'], 2) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">
                            {{ number_format($row['spent'], 2) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">
                            {{ number_format($row['committed'] - $row['spent'], 2) }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            {{ __('No plan carried a budget source in this year. Set a cap under Setup to track one.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif
</div>
