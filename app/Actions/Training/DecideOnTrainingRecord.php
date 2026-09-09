<?php

namespace App\Actions\Training;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Notifications\TrainingAwaitsDecision;
use App\Notifications\TrainingDecided;
use App\Workflow\ApprovalRouter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DecideOnTrainingRecord
{
    public function __construct(private readonly ApprovalRouter $router) {}

    /**
     * Record one approval decision and move the record along.
     *
     * Approval at the section level advances to the division head, unless
     * the division has no head, in which case the record is complete.
     * Rejection ends it at whatever level rejected.
     *
     * @throws InvalidArgumentException when the record is not awaiting a decision
     */
    public function handle(
        TrainingRecord $record,
        User $approver,
        ApprovalDecision $decision,
        ?string $remarks = null,
    ): TrainingRecord {
        if ($record->status !== TrainingStatus::Pending) {
            throw new InvalidArgumentException('Only a pending record can be decided on.');
        }

        $level = $record->current_level ?? $this->overrideLevelFor($approver);

        DB::transaction(function () use ($record, $approver, $decision, $remarks, $level): void {
            $record->approvals()->create([
                'level' => $level,
                'approver_user_id' => $approver->getKey(),
                'decision' => $decision,
                'remarks' => $remarks,
                'decided_at' => now(),
            ]);

            if ($decision === ApprovalDecision::Rejected) {
                $record->update([
                    'status' => TrainingStatus::Rejected,
                    'current_level' => null,
                    'rejection_reason' => $remarks,
                ]);

                return;
            }

            $next = $this->router->levelAfter($level, $record->employee);

            $record->update([
                'status' => $next === null ? TrainingStatus::Approved : TrainingStatus::Pending,
                'current_level' => $next,
            ]);
        });

        $record->refresh()->load('approvals');

        $this->tell($record, $decision);

        return $record;
    }

    /**
     * The level a decision on an unroutable record is recorded at.
     *
     * Only HR and admin may make one. Recording it against HR rather than
     * against a head keeps the trail honest: no head ever saw it.
     *
     * @throws InvalidArgumentException when anybody else tries
     */
    private function overrideLevelFor(User $approver): ApprovalLevel
    {
        if (! $approver->isAdminOrHr()) {
            throw new InvalidArgumentException('This record has no approver assigned.');
        }

        return ApprovalLevel::Hr;
    }

    /**
     * Say what happened, to whoever it now concerns.
     *
     * A record that has moved up is not finished, so the employee hears
     * nothing yet — only the head who now has to act on it.
     */
    private function tell(TrainingRecord $record, ApprovalDecision $decision): void
    {
        $employee = $record->employee;

        if ($employee === null) {
            return;
        }

        if ($record->current_level !== null) {
            $this->router->approverFor($record->current_level, $employee)?->user?->notify(
                new TrainingAwaitsDecision($record),
            );

            return;
        }

        $employee->user?->notify(new TrainingDecided($record, $decision));
    }
}
