<?php

namespace App\Notifications;

use App\Models\TrainingRecord;
use Illuminate\Notifications\Notification;

/**
 * Told to whoever has to decide on a training record.
 *
 * Sent when it is submitted, and again when a section head passes it up
 * to the division head — the second person would otherwise have no way
 * of knowing it had reached them.
 */
class TrainingAwaitsDecision extends Notification
{
    public function __construct(private readonly TrainingRecord $record) {}

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
            'employee' => $this->record->employee->listing_name ?? '',
            'kind' => 'awaiting',
            'route' => 'approvals',
        ];
    }
}
