<?php

namespace App\Enums;

enum EligibilityStatus: string
{
    case Expiring = 'expiring';
    case Expired = 'expired';
    case NoExpiry = 'no_expiry';

    public function label(): string
    {
        return match ($this) {
            self::Expiring => __('Expiring within a year'),
            self::Expired => __('Expired'),
            self::NoExpiry => __('No expiry'),
        };
    }
}
