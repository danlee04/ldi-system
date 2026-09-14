<?php

namespace App\Notifications;

use App\Models\LdnaAssessment;
use Illuminate\Notifications\Notification;

/**
 * Told to whoever rates somebody, once that person has rated themselves.
 */
class SelfRatingSubmitted extends Notification
{
    public function __construct(private readonly LdnaAssessment $assessment) {}

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
            'ldna_assessment_id' => $this->assessment->getKey(),
            'title' => __('LDNA :year', ['year' => $this->assessment->cycle->year]),
            'employee' => $this->assessment->employee->listing_name,
            'kind' => 'ldna_self_rated',
            'route' => 'ldna.ratings',
        ];
    }
}
