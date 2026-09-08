<?php

namespace App\Enums;

/**
 * The three lists of Section VIII, in the order the form prints them.
 */
enum OtherInformationType: string
{
    case Skill = 'skill';
    case Distinction = 'distinction';
    case Membership = 'membership';

    public function label(): string
    {
        return match ($this) {
            self::Skill => 'Special skills and hobbies',
            self::Distinction => 'Non-academic distinctions or recognition',
            self::Membership => 'Membership in association or organisation',
        };
    }
}
