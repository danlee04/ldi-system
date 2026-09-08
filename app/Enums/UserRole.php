<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Hr = 'hr';
    case DivisionHead = 'division_head';
    case SectionHead = 'section_head';
    case Employee = 'employee';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }

    /**
     * Roles that may see every employee and every training record.
     */
    public function seesEverything(): bool
    {
        return in_array($this, [self::Admin, self::Hr], true);
    }
}
