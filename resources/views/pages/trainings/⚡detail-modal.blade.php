<?php

use App\Enums\ApprovalDecision;
use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

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

        // No employee, section or division: the modal stopped naming whose
        // record it is, and loading them would be three queries for nothing.
        return TrainingRecord::with(['submittedBy', 'approvals.approver'])->find($this->recordId);
    }

    #[Computed]
    public function standing(): array
    {
        $record = $this->record;

        return match (true) {
            $record->status === TrainingStatus::Approved => [__('Fully approved'), 'emerald'],

            $record->status === TrainingStatus::Rejected => [__('Rejected'), 'red'],

            $record->current_level === null => [__('Nobody can approve this yet'), 'amber'],

            default => [
                __('Waiting for the :level', [
                    'level' => strtolower($record->current_level->label()),
                ]),
                'zinc',
            ],
        };
    }

    #[Computed]
    public function trail(): array
    {
        $record = $this->record;

        $steps = [
            [
                'title' => __('Submitted'),
                'by' => $record->submittedBy->name,
                'at' => $record->created_at,
                'remarks' => null,
                'open' => false,
                'rejected' => false,
            ],
        ];

        foreach ($record->approvals as $approval) {
            $rejected = $approval->decision === ApprovalDecision::Rejected;

            $steps[] = [
                'title' => $rejected
                    ? __('Rejected by the :level', [
                        'level' => strtolower($approval->level->label()),
                    ])
                    : __('Approved by the :level', [
                        'level' => strtolower($approval->level->label()),
                    ]),
                'by' => $approval->approver->name,
                'at' => $approval->decided_at,
                'remarks' => $approval->remarks,
                'open' => false,
                'rejected' => $rejected,
            ];
        }

        if ($record->status === TrainingStatus::Pending) {
            $steps[] = [
                'title' =>
                    $record->current_level === null
                        ? __('No approver is designated')
                        : __('With the :level', [
                            'level' => strtolower($record->current_level->label()),
                        ]),
                'by' => null,
                'at' => null,
                'remarks' => $record->current_level === null ? __('Set a section or division head under Setup to let this move.') : null,
                'open' => true,
                'rejected' => false,
            ];
        }

        return $steps;
    }

    /**
     * What the record says about the training itself. Whose record it is
     * is not here: the modal opens from a list that already names them.
     *
     * @return list<array{label: string, value: string, numeric?: bool}>
     */
    #[Computed]
    public function facts(): array
    {
        $record = $this->record;

        return [
            ['label' => __('Inclusive dates'), 'value' => $record->inclusive_dates],
            ['label' => __('Hours'), 'value' => (string) $record->hours, 'numeric' => true],
            ['label' => __('Type of LD'), 'value' => $record->ld_type_label],
            ['label' => __('Conducted by'), 'value' => $record->conducted_by],
            ['label' => __('Location'), 'value' => $record->location ?? '—'],
            ['label' => __('CPD units'), 'value' => (string) ($record->cpd_units ?? '—'), 'numeric' => true],
        ];
    }

    #[Computed]
    public function hasCosts(): bool
    {
        return $this->record->registration_fee !== null || $this->record->tev !== null || $this->record->expenses !== null;
    }

    #[Computed]
    public function totalCost(): float
    {
        return (float) $this->record->registration_fee + (float) $this->record->tev + (float) $this->record->expenses;
    }
}; ?>

<flux:modal name="training-detail" class="md:w-5xl">
    @if ($this->record)
        @php($record = $this->record)
        @php([$standing, $tone] = $this->standing)

        <div class="overflow-hidden">

            {{-- Header --}}
            <div
                class="relative -mx-6 -mt-6 border-b border-zinc-200 bg-zinc-50 px-6 py-6 dark:border-zinc-700 dark:bg-zinc-900/70">

                <div class="flex items-start justify-between gap-5">

                    <div class="min-w-0 flex-1">

                        {{-- Status --}}
                        <div class="mb-3 flex flex-wrap items-center gap-2">

                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                                'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-400' =>
                                    $tone === 'emerald',
                                'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400' =>
                                    $tone === 'red',
                                'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400' =>
                                    $tone === 'amber',
                                'bg-zinc-100 text-zinc-700 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300' =>
                                    $tone === 'zinc',
                            ])>
                                <span @class([
                                    'size-1.5 rounded-full',
                                    'bg-emerald-500' => $tone === 'emerald',
                                    'bg-red-500' => $tone === 'red',
                                    'bg-amber-500' => $tone === 'amber',
                                    'bg-zinc-400 dark:bg-zinc-500' => $tone === 'zinc',
                                ])></span>

                                {{ $standing }}
                            </span>

                            @if ($record->status)
                                <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ $record->status->label() }}
                                </span>
                            @endif

                        </div>

                        {{-- Title --}}
                        <flux:heading size="xl" class="max-w-3xl tracking-tight">
                            {{ $record->title }}
                        </flux:heading>

                    </div>

                </div>
            </div>


            {{-- Main Content --}}
            <div class="space-y-7 py-6">

                {{-- Training Overview --}}
                <section>

                    <div class="mb-3 flex items-center gap-2">
                        <div class="flex size-7 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon name="information-circle" variant="mini"
                                class="text-zinc-600 dark:text-zinc-300" />
                        </div>

                        <flux:heading size="sm">
                            {{ __('Training details') }}
                        </flux:heading>
                    </div>

                    {{-- A card each, with air between them, rather than six
                         cells sharing one box and its hairlines. --}}
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->facts as $fact)
                            <div
                                class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ $fact['label'] }}
                                </div>

                                <div @class([
                                    'mt-1.5 text-sm text-zinc-900 dark:text-white',
                                    'tabular-nums' => $fact['numeric'] ?? false,
                                ])>
                                    {{ $fact['value'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>


                {{-- Costs + Approval --}}
                <div class="grid gap-6 lg:grid-cols-2">

                    {{-- Costs --}}
                    <section
                        class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-700 dark:bg-zinc-900/50">

                        <div class="flex items-start justify-between gap-4">

                            <div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-8 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
                                        <flux:icon name="banknotes" variant="mini"
                                            class="text-zinc-600 dark:text-zinc-300" />
                                    </div>

                                    <flux:heading size="sm">
                                        {{ __('Costs') }}
                                    </flux:heading>
                                </div>

                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Expenses associated with this training.') }}
                                </p>
                            </div>

                        </div>

                        @if ($this->hasCosts)

                            <div class="mt-5 space-y-3">

                                @foreach ([
        __('Registration fee') => $record->registration_fee,
        __('Travel expenses') => $record->tev,
        __('Other expenses') => $record->expenses,
    ] as $label => $amount)
                                    <div class="flex items-center justify-between gap-4 text-sm">

                                        <span class="text-zinc-500 dark:text-zinc-400">
                                            {{ $label }}
                                        </span>

                                        <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">
                                            {{ $amount === null ? '—' : number_format((float) $amount, 2) }}
                                        </span>

                                    </div>
                                @endforeach

                                <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">

                                    <div class="flex items-end justify-between gap-4">

                                        <div>
                                            <div
                                                class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                {{ __('Total cost') }}
                                            </div>
                                        </div>

                                        <div
                                            class="text-xl font-semibold tabular-nums tracking-tight text-zinc-950 dark:text-white">
                                            {{ number_format($this->totalCost, 2) }}
                                        </div>

                                    </div>

                                </div>

                            </div>
                        @else
                            <div
                                class="mt-5 rounded-lg border border-dashed border-zinc-300 bg-white px-4 py-5 text-center dark:border-zinc-700 dark:bg-zinc-900">

                                <flux:icon name="receipt-percent" variant="outline"
                                    class="mx-auto size-6 text-zinc-400" />

                                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Nothing was charged for this training.') }}
                                </p>

                            </div>

                        @endif

                    </section>


                    {{-- Approval --}}
                    <section
                        class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">

                        <div class="flex items-center gap-2">

                            <div
                                class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon name="check-badge" variant="mini" class="text-zinc-600 dark:text-zinc-300" />
                            </div>

                            <div>
                                <flux:heading size="sm">
                                    {{ __('Approval history') }}
                                </flux:heading>

                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Record progression and decisions.') }}
                                </p>
                            </div>

                        </div>


                        <ol class="mt-5">

                            @foreach ($this->trail as $step)
                                <li class="relative flex gap-3 pb-5 last:pb-0">

                                    {{-- Connector --}}
                                    @unless ($loop->last)
                                        <span
                                            class="absolute left-[15px] top-8 h-[calc(100%-12px)] w-px bg-zinc-200 dark:bg-zinc-700"></span>
                                    @endunless


                                    {{-- Timeline Icon --}}
                                    <div @class([
                                        'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-zinc-900',
                                        'bg-red-500 text-white' => $step['rejected'],
                                        'bg-emerald-500 text-white' => !$step['rejected'] && !$step['open'],
                                        'border border-dashed border-zinc-300 bg-white text-zinc-400 dark:border-zinc-600 dark:bg-zinc-800' =>
                                            $step['open'],
                                    ])>

                                        @if ($step['rejected'])
                                            <flux:icon name="x-mark" variant="mini" />
                                        @elseif ($step['open'])
                                            <flux:icon name="clock" variant="mini" />
                                        @else
                                            <flux:icon name="check" variant="mini" />
                                        @endif

                                    </div>


                                    {{-- Content --}}
                                    <div class="min-w-0 flex-1 pt-0.5">

                                        <div class="flex flex-wrap items-start justify-between gap-2">

                                            <div @class([
                                                'text-sm font-medium',
                                                'text-zinc-500 dark:text-zinc-400' => $step['open'],
                                                'text-red-700 dark:text-red-400' => $step['rejected'],
                                                'text-zinc-900 dark:text-white' => !$step['open'] && !$step['rejected'],
                                            ])>
                                                {{ $step['title'] }}
                                            </div>

                                            @if ($step['at'])
                                                <time
                                                    class="shrink-0 text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                                                    {{ $step['at']->format('d M Y') }}
                                                </time>
                                            @endif

                                        </div>


                                        @if ($step['by'])
                                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $step['by'] }}
                                            </div>
                                        @endif


                                        @if ($step['remarks'])
                                            <div @class([
                                                'mt-2 rounded-lg px-3 py-2 text-xs leading-relaxed',
                                                'bg-amber-50 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300' =>
                                                    $step['open'],
                                                'bg-red-50 text-red-800 dark:bg-red-400/10 dark:text-red-300' =>
                                                    $step['rejected'],
                                                'bg-zinc-50 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' =>
                                                    !$step['open'] && !$step['rejected'],
                                            ])>
                                                {{ $step['remarks'] }}
                                            </div>
                                        @endif

                                    </div>

                                </li>
                            @endforeach

                        </ol>

                    </section>

                </div>

            </div>


            {{-- Footer --}}
            <div
                class="-mx-6 flex items-center justify-between border-t border-zinc-200 bg-zinc-50 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-900/70">

                <div class="hidden text-xs text-zinc-500 sm:block dark:text-zinc-400">
                    {{ __('Training record details') }}
                </div>

                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">
                        {{ __('Close') }}
                    </flux:button>
                </flux:modal.close>

            </div>

        </div>
    @endif
</flux:modal>
