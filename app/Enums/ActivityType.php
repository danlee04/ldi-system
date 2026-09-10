<?php

namespace App\Enums;

/**
 * What kind of thing sits on the office calendar.
 *
 * The type is what gives a day its colour, so the month can be read at a
 * glance without opening anything.
 */
enum ActivityType: string
{
    case Meeting = 'meeting';
    case Holiday = 'holiday';
    case Deadline = 'deadline';
    case Other = 'other';

    public function label(): string
    {
        return $this->name;
    }

    /**
     * A Flux badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Meeting => 'blue',
            self::Holiday => 'green',
            self::Deadline => 'amber',
            self::Other => 'zinc',
        };
    }

    /**
     * The chip a day in the month grid wears.
     *
     * Written out in full rather than built from color(): Tailwind reads
     * the source for class names it can find, and never sees one that a
     * template pieces together.
     */
    public function chipClasses(): string
    {
        return match ($this) {
            self::Meeting => 'bg-blue-100 text-blue-800 dark:bg-blue-400/20 dark:text-blue-200',
            self::Holiday => 'bg-green-100 text-green-800 dark:bg-green-400/20 dark:text-green-200',
            self::Deadline => 'bg-amber-100 text-amber-900 dark:bg-amber-400/20 dark:text-amber-100',
            self::Other => 'bg-zinc-200 text-zinc-800 dark:bg-white/15 dark:text-zinc-100',
        };
    }
}
