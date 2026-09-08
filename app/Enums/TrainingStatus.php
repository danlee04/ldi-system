<?php

namespace App\Enums;

enum TrainingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
