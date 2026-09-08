<?php

namespace App\Enums;

/**
 * The five rows Section III of CS Form No. 212 offers, in the order the
 * form prints them.
 */
enum EducationLevel: string
{
    case Elementary = 'elementary';
    case Secondary = 'secondary';
    case Vocational = 'vocational';
    case College = 'college';
    case Graduate = 'graduate';

    public function label(): string
    {
        return match ($this) {
            self::Elementary => __('Elementary'),
            self::Secondary => __('Secondary'),
            self::Vocational => __('Vocational / Trade course'),
            self::College => __('College'),
            self::Graduate => __('Graduate studies'),
        };
    }
}
