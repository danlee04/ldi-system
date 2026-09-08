<?php

namespace App\Enums;

enum ApprovalLevel: string
{
    case SectionHead = 'section_head';
    case DivisionHead = 'division_head';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
