<?php

use App\Enums\ApprovalDecision;
use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The training detail, shown wherever a record is listed.
 *
 * Include it once per page and open it with
 * `$dispatch('show-training', { recordId: ... })`, so the markup lives in
 * one place instead of being repeated on every list that links to a record.
 */
new class extends Component {
    public ?int $recordId = null;

    #[On('show-training')]
    public function showTraining(int $recordId): void
    {
        $this->authorize('view', TrainingRecord::findOrFail($recordId));

        $this->recordId = $recordId;

        unset($this->record);

        Flux::modal('training-detail')->show();
    }

    #[Computed]
    public function record(): ?TrainingRecord
    {
        if ($this->recordId === null) {
            return null;
        }

        return TrainingRecord::with(['employee.section.division', 'submittedBy', 'approvals.approver'])
            ->find($this->recordId);
    }

    /**
     * The one sentence that answers "where is this record".
     *
     * @return array{string, string}  the sentence and its colour
     */
    #[Computed]
    public function standing(): array
    {
        $record = $this->record;

        return match (true) {
            $record->status === TrainingStatus::Approved => [__('Fully approved'), 'emerald'],
            $record->status === TrainingStatus::Rejected => [__('Rejected'), 'red'],
            $record->current_level === null => [__('Nobody can approve this yet'), 'amber'],
            default => [
                __('Waiting for the :level', ['level' => strtolower($record->current_level->label())]),
                'zinc',
            ],
        };
    }

    /**
     * The record's life as a sequence, including the step that has not
     * happened yet. A plain list of past decisions cannot show what the
     * record is waiting on, which is the reason people open this.
     *
     * @return list<array{title: string, by: string|null, at: mixed, remarks: string|null, open: bool, rejected: bool}>
     */
    #[Computed]
    public function trail(): array
    {
        $record = $this->record;

        $steps = [[
            'title' => __('Submitted'),
            'by' => $record->submittedBy->name,
            'at' => $record->created_at,
            'remarks' => null,
            'open' => false,
            'rejected' => false,
        ]];

        foreach ($record->approvals as $approval) {
            $rejected = $approval->decision === ApprovalDecision::Rejected;

            $steps[] = [
                'title' => $rejected
                    ? __('Rejected by the :level', ['level' => strtolower($approval->level->label())])
                    : __('Approved by the :level', ['level' => strtolower($approval->level->label())]),
                'by' => $approval->approver->name,
                'at' => $approval->decided_at,
                'remarks' => $approval->remarks,
                'open' => false,
                'rejected' => $rejected,
            ];
        }

        if ($record->status === TrainingStatus::Pending) {
            $steps[] = [
                'title' => $record->current_level === null
                    ? __('No approver is designated')
                    : __('With the :level', ['level' => strtolower($record->current_level->label())]),
                'by' => null,
                'at' => null,
                'remarks' => $record->current_level === null
                    ? __('Set a section or division head under Setup to let this move.')
                    : null,
                'open' => true,
                'rejected' => false,
            ];
        }

        return $steps;
    }

    /**
     * Most imported history carries no amounts at all, and a row of dashes
     * under a 0.00 total says less than one plain sentence.
     */
    #[Computed]
    public function hasCosts(): bool
    {
        return $this->record->registration_fee !== null
            || $this->record->tev !== null
            || $this->record->expenses !== null;
    }

    #[Computed]
    public function totalCost(): float
    {
        return (float) $this->record->registration_fee
            + (float) $this->record->tev
            + (float) $this->record->expenses;
    }
}; ?>

<flux:modal name="training-detail" class="md:w-5xl">
    @if ($this->record)
        @php($record = $this->record)
        @php([$standing, $tone] = $this->standing)

        <div class="space-y-8">
            <div class="space-y-3">
                <flux:heading size="lg">{{ $record->title }}</flux:heading>

                <div class="flex items-center gap-2">
                    <span @class([
                        'size-2 rounded-full',
                        'bg-emerald-500' => $tone === 'emerald',
                        'bg-red-500' => $tone === 'red',
                        'bg-amber-500' => $tone === 'amber',
                        'bg-zinc-400 dark:bg-zinc-500' => $tone === 'zinc',
                    ])></span>
                    <span @class([
                        'text-sm font-medium',
                        'text-emerald-700 dark:text-emerald-400' => $tone === 'emerald',
                        'text-red-700 dark:text-red-400' => $tone === 'red',
                        'text-amber-700 dark:text-amber-400' => $tone === 'amber',
                        'text-zinc-600 dark:text-zinc-300' => $tone === 'zinc',
                    ])>{{ $standing }}</span>
                </div>

                <flux:text>
                    {{ $record->employee->full_name }}
                    @if ($record->employee->section)
                        — {{ $record->employee->section->name }}
                    @endif
                </flux:text>
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 border-y border-zinc-200 py-5 md:grid-cols-3 dark:border-zinc-700">
                <div>
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Inclusive dates') }}</dt>
                    <dd class="mt-0.5 text-sm">
                        {{ $record->inclusive_dates }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Hours') }}</dt>
                    <dd class="mt-0.5 text-sm tabular-nums">{{ $record->hours }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Type of LD') }}</dt>
                    <dd class="mt-0.5 text-sm">{{ $record->ld_type_label }}</dd>
                </div>
                <div class="col-span-2 md:col-span-1">
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Conducted by') }}</dt>
                    <dd class="mt-0.5 text-sm">{{ $record->conducted_by }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</dt>
                    <dd class="mt-0.5 text-sm">{{ $record->location ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('CPD units') }}</dt>
                    <dd class="mt-0.5 text-sm tabular-nums">{{ $record->cpd_units ?? '—' }}</dd>
                </div>
            </dl>

            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <flux:heading size="sm">{{ __('Costs') }}</flux:heading>

                    @if ($this->hasCosts)
                        <dl class="mt-3 space-y-2 text-sm">
                            @foreach ([
                                __('Registration fee') => $record->registration_fee,
                                __('Travel expenses') => $record->tev,
                                __('Other expenses') => $record->expenses,
                            ] as $label => $amount)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                                    <dd class="tabular-nums">
                                        {{ $amount === null ? '—' : number_format((float) $amount, 2) }}
                                    </dd>
                                </div>
                            @endforeach

                            <div class="flex justify-between gap-4 border-t border-zinc-200 pt-2 font-medium dark:border-zinc-700">
                                <dt>{{ __('Total') }}</dt>
                                <dd class="tabular-nums">{{ number_format($this->totalCost, 2) }}</dd>
                            </div>
                        </dl>
                    @else
                        <flux:text class="mt-3">{{ __('Nothing was charged for this training.') }}</flux:text>
                    @endif
                </div>

                <div>
                    <flux:heading size="sm">{{ __('Approval') }}</flux:heading>

                    <ol class="mt-3 space-y-1">
                        @foreach ($this->trail as $step)
                            <li class="relative flex gap-3 pb-4 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute top-4 bottom-0 left-1.25 w-px bg-zinc-200 dark:bg-zinc-700"></span>
                                @endunless

                                <span @class([
                                    'relative mt-1.5 size-[11px] shrink-0 rounded-full border-2',
                                    'border-red-500 bg-red-500' => $step['rejected'],
                                    'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-800' => $step['open'],
                                    'border-emerald-500 bg-emerald-500' => ! $step['rejected'] && ! $step['open'],
                                ])></span>

                                <div class="min-w-0 flex-1 text-sm">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <span @class(['font-medium', 'text-zinc-500 dark:text-zinc-400' => $step['open']])>
                                            {{ $step['title'] }}
                                        </span>

                                        @if ($step['at'])
                                            <span class="text-xs text-zinc-500 tabular-nums dark:text-zinc-400">
                                                {{ $step['at']->format('d M Y') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($step['by'])
                                        <div class="text-zinc-500 dark:text-zinc-400">{{ $step['by'] }}</div>
                                    @endif

                                    @if ($step['remarks'])
                                        <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $step['remarks'] }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="flex">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    @endif
</flux:modal>
