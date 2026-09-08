<?php

namespace App\Enums;

enum LdType: string
{
    case Managerial = 'managerial';
    case Supervisory = 'supervisory';
    case Technical = 'technical';
    case Foundation = 'foundation';
    case Other = 'other';

    public function label(): string
    {
        return $this->name;
    }

    /**
     * The first four are the CS Form 212 types. Other carries its own
     * free text in TrainingRecord::$ld_type_other.
     */
    public function requiresOwnText(): bool
    {
        return $this === self::Other;
    }
}
