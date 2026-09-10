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

    /**
     * A training is planned as an LdiTraining rather than an activity, so
     * it has no case here — but it shares this calendar's palette, and the
     * palette is worth having in one place.
     *
     * It wears the Center's own blue, because a training is the commonest
     * thing on this calendar and the thing the whole system is about. That
     * pushes a meeting to violet: the two are the pair a colourblind reader
     * is likeliest to confuse, so the training keeps a dashed edge as well.
     * The dash also says the bar is not the calendar page's to edit.
     */
    public const PLAN_CHIP = 'border border-dashed border-brand-primary bg-brand-primary/10 text-blue-900 dark:bg-brand-primary/30 dark:text-blue-100';

    public const PLAN_DOT = 'bg-brand-primary';

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
            self::Meeting => 'violet',
            self::Holiday => 'green',
            self::Deadline => 'amber',
            self::Other => 'zinc',
        };
    }

    /**
     * The dot a day wears where there is no room for a chip.
     */
    public function dotClasses(): string
    {
        return match ($this) {
            self::Meeting => 'bg-violet-500 dark:bg-violet-400',
            self::Holiday => 'bg-green-500 dark:bg-green-400',
            self::Deadline => 'bg-amber-500 dark:bg-amber-400',
            self::Other => 'bg-zinc-400 dark:bg-zinc-400',
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
            self::Meeting => 'bg-violet-100 text-violet-900 dark:bg-violet-400/25 dark:text-violet-100',
            self::Holiday => 'bg-green-100 text-green-900 dark:bg-green-400/25 dark:text-green-100',
            self::Deadline => 'bg-amber-100 text-amber-900 dark:bg-amber-400/25 dark:text-amber-100',
            self::Other => 'bg-zinc-200 text-zinc-900 dark:bg-white/15 dark:text-zinc-100',
        };
    }
}
