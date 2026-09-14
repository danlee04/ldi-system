<?php

namespace App\Enums;

/**
 * The four levels of the Civil Service scale, lowest first.
 */
enum ProficiencyLevel: string
{
    case Basic = 'basic';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Superior = 'superior';

    public function label(): string
    {
        return $this->name;
    }

    /**
     * Where the level stands on the scale, so that two can be subtracted.
     * A gap is worked out from this and nothing else.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Basic => 1,
            self::Intermediate => 2,
            self::Advanced => 3,
            self::Superior => 4,
        };
    }
}
