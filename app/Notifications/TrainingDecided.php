<?php

namespace App\Notifications;

use App\Enums\ApprovalDecision;
use App\Models\TrainingRecord;
use Illuminate\Notifications\Notification;

/**
 * Told to the employee whose record was approved or rejected.
 *
 * A rejection carries its reason, because that is the whole of what the
 * employee needs to know and going to look it up is the step people skip.
 */
class TrainingDecided extends Notification
{
    public function __construct(
        private readonly TrainingRecord $record,
        private readonly ApprovalDecision $decision,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'training_record_id' => $this->record->getKey(),
            'title' => $this->record->title,
            'decision' => $this->decision->value,
            'reason' => $this->record->rejection_reason,
            'kind' => 'decided',
            'route' => 'trainings.mine',
        ];
    }
}
