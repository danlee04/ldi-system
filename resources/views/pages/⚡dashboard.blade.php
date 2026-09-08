<?php

use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Workflow\ApprovalRouter;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public int $myPending = 0;

    public int $myApproved = 0;

    public int $awaitingMe = 0;

    public int $unroutable = 0;

    public function mount(): void
    {
        $user = auth()->user();
        $employee = $user->employee;

        if ($employee instanceof Employee) {
            $this->myPending = $employee->trainingRecords()->pending()->count();
            $this->myApproved = $employee->trainingRecords()->approved()->count();
            $this->awaitingMe = $this->countAwaitingMe($employee);
        }

        if ($user->isAdminOrHr()) {
            $this->unroutable = TrainingRecord::query()->unroutable()->count();
        }
    }

    /**
     * Asks the router per record rather than filtering in SQL, so there is
     * one definition of who approves what.
     */
    private function countAwaitingMe(Employee $employee): int
    {
        $router = app(ApprovalRouter::class);

        return TrainingRecord::query()
            ->pending()
            ->whereNotNull('current_level')
            ->with('employee')
            ->get()
            ->filter(function (TrainingRecord $record) use ($router, $employee): bool {
                $approver = $router->approverFor($record->current_level, $record->employee);

                return $approver instanceof Employee && $approver->is($employee);
            })
            ->count();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text size="sm">{{ __('My pending trainings') }}</flux:text>
            <flux:heading size="xl">{{ $myPending }}</flux:heading>
            <flux:link :href="route('trainings.mine')" wire:navigate>{{ __('View mine') }}</flux:link>
        </flux:card>

        <flux:card>
            <flux:text size="sm">{{ __('My approved trainings') }}</flux:text>
            <flux:heading size="xl">{{ $myApproved }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text size="sm">{{ __('Waiting for my decision') }}</flux:text>
            <flux:heading size="xl">{{ $awaitingMe }}</flux:heading>
            @if ($awaitingMe > 0)
                <flux:link :href="route('approvals')" wire:navigate>{{ __('Go to approvals') }}</flux:link>
            @endif
        </flux:card>
    </div>

    @if ($unroutable > 0)
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Records with no approver')">
            {{ __(':count records cannot move because no head is designated. Set one under Setup.', ['count' => $unroutable]) }}
        </flux:callout>
    @endif

    @if (auth()->user()->employee === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Your account is not linked to an employee record yet.') }}
        </flux:callout>
    @endif
</div>
