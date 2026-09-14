<?php

namespace App\Notifications;

use App\Models\LdnaCycle;
use Illuminate\Notifications\Notification;

/**
 * Told to everybody enrolled in a cycle, with the days they have to fill
 * it in. Sent when HR sets the cycle up rather than on the day it opens:
 * nothing runs the scheduler on this server, so a message meant for a
 * later day would never go.
 */
class LdnaCycleOpened extends Notification
{
    public function __construct(private readonly LdnaCycle $cycle) {}

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
            'ldna_cycle_id' => $this->cycle->getKey(),
            'title' => __('LDNA :year', ['year' => $this->cycle->year]),
            'window' => $this->cycle->opens_on->format('M j').' – '.$this->cycle->closes_on->format('M j, Y'),
            'kind' => 'ldna_opened',
            'route' => 'ldna.mine',
        ];
    }
}
