<?php

namespace App\Enums;

enum ApprovalLevel: string
{
    case SectionHead = 'section_head';
    case DivisionHead = 'division_head';

    /**
     * HR deciding in place of a head. It is never a record's current level
     * — only the level a decision is recorded at, so the trail says the
     * decision came from outside the chain rather than from a head.
     */
    case Hr = 'hr';

    public function label(): string
    {
        return $this === self::Hr ? 'HR' : str($this->name)->headline()->toString();
    }
}
