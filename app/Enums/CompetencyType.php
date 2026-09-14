<?php

namespace App\Enums;

/**
 * The three kinds of competency in the dictionary. They differ in whom
 * they apply to, and so in where their required level is kept.
 */
enum CompetencyType: string
{
    case Core = 'core';
    case Leadership = 'leadership';
    case Technical = 'technical';

    public function label(): string
    {
        return $this->name;
    }

    /**
     * Core and Leadership ask one level of everybody they apply to, so the
     * level lives on the competency. Technical differs by position and is
     * set on each position instead.
     */
    public function hasOwnRequiredLevel(): bool
    {
        return $this !== self::Technical;
    }
}
