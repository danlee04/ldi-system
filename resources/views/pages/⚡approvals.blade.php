<?php

use App\Actions\Training\DecideOnTrainingRecord;
use App\Enums\ApprovalDecision;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Workflow\ApprovalRouter;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Approvals')] class extends Component {
    public string $remarks = '';

    public ?int $decidingId = null;

    public string $decisionType = 'approve';

    /**
     * Records this user is the designated approver for, right now.
     *
     * The level is filtered in SQL; the designation itself is checked in
     * PHP through the router, so there is exactly one definition of who
     * approves what.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function queue(): Collection
    {
        $employee = auth()->user()->employee;

        if ($employee === null) {
            return collect();
        }

        $router = app(ApprovalRouter::class);

        return TrainingRecord::query()
            ->pending()
            ->whereNotNull('current_level')
            ->with(['employee.section.division'])
            ->orderBy('created_at')
            ->get()
            ->filter(function (TrainingRecord $record) use ($router, $employee): bool {
                $approver = $router->approverFor($record->current_level, $record->employee);

                return $approver instanceof Employee && $approver->is($employee);
            })
            ->values();
    }

    /**
     * Pending records nobody can approve. HR and admin only.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function unroutable(): Collection
    {
        if (! auth()->user()->isAdminOrHr()) {
            return collect();
        }

        return TrainingRecord::query()
            ->unroutable()
            ->with(['employee.section.division'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The record the open modal is about.
     */
    #[Computed]
    public function deciding(): ?TrainingRecord
    {
        return $this->decidingId === null
            ? null
            : TrainingRecord::with('employee')->find($this->decidingId);
    }

    /**
     * Open the confirmation modal. Remarks left over from an abandoned
     * decision must not leak into the next one.
     */
    public function startDecision(int $recordId, string $decisionType): void
    {
        $this->resetValidation();
        $this->reset('remarks');

        $this->decidingId = $recordId;
        $this->decisionType = $decisionType === 'reject' ? 'reject' : 'approve';

        unset($this->deciding);

        Flux::modal('decide')->show();
    }

    public function confirmDecision(): void
    {
        if ($this->decidingId === null) {
            return;
        }

        if ($this->decisionType === 'reject') {
            $this->reject($this->decidingId);

            return;
        }

        $this->approve($this->decidingId);
    }

    public function approve(int $recordId): void
    {
        $this->decide($recordId, ApprovalDecision::Approved);
    }

    public function reject(int $recordId): void
    {
        $this->validate(
            ['remarks' => ['required', 'string', 'max:1000']],
            ['remarks.required' => __('A reason is required when rejecting.')],
        );

        $this->decide($recordId, ApprovalDecision::Rejected);
    }

    private function decide(int $recordId, ApprovalDecision $decision): void
    {
        $record = TrainingRecord::findOrFail($recordId);

        $this->authorize('decide', $record);

        app(DecideOnTrainingRecord::class)->handle(
            $record,
            auth()->user(),
            $decision,
            $this->remarks !== '' ? $this->remarks : null,
        );

        $this->reset('remarks');
        $this->decidingId = null;
        unset($this->queue, $this->unroutable, $this->deciding);

        Flux::modal('decide')->close();

        Flux::toast(variant: 'success', text: __('Decision recorded.'));
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Approvals') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Training') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->queue as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>{{ $record->employee->full_name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $record)" wire:navigate>
                            {{ $record->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="primary"
                                wire:click="startDecision({{ $record->id }}, 'approve')">
                                {{ __('Approve') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger"
                                wire:click="startDecision({{ $record->id }}, 'reject')">
                                {{ __('Reject') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nothing is waiting for you.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="decide" class="md:w-2xl">
        <div class="space-y-6">
            <flux:heading size="lg">
                {{ $decisionType === 'reject' ? __('Reject this training?') : __('Approve this training?') }}
            </flux:heading>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-3">
                    @if ($this->deciding)
                        <div>
                            <flux:text size="sm">{{ __('Training') }}</flux:text>
                            <flux:heading>{{ $this->deciding->title }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('Employee') }}</flux:text>
                            <flux:heading>{{ $this->deciding->employee->full_name }}</flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('Inclusive dates') }}</flux:text>
                            <flux:heading>
                                {{ $this->deciding->date_start->format('d M Y') }} –
                                {{ $this->deciding->date_end->format('d M Y') }}
                            </flux:heading>
                        </div>
                        <div>
                            <flux:text size="sm">{{ __('Hours') }}</flux:text>
                            <flux:heading>{{ $this->deciding->hours }}</flux:heading>
                        </div>
                    @endif
                </div>

                <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="8"
                    :description="$decisionType === 'reject'
                        ? __('Required. The employee sees this reason.')
                        : __('Optional.')" />
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="confirmDecision"
                    :variant="$decisionType === 'reject' ? 'danger' : 'primary'">
                    {{ $decisionType === 'reject' ? __('Reject') : __('Approve') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    @if ($this->unroutable->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No approver assigned')">
            {{ __('These records cannot move because neither the section nor the division has a head designated. Set a head under Setup.') }}
        </flux:callout>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Employee') }}</flux:table.column>
                <flux:table.column>{{ __('Training') }}</flux:table.column>
                <flux:table.column>{{ __('Section') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->unroutable as $record)
                    <flux:table.row :key="$record->id">
                        <flux:table.cell>{{ $record->employee->full_name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('trainings.show', $record)" wire:navigate>
                                {{ $record->title }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $record->employee->section?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
