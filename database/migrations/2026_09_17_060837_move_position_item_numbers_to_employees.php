<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The plantilla item belongs to the seat, not to the position.
 *
 * A position carried a single item number, which only ever told the truth
 * when exactly one person held that position. Five Nurse I sharing one
 * item number is one seat's number standing in for five.
 *
 * So the number is moved onto the person, and only where there is no doubt
 * whose it is: one holder still on the roster, and no item number on them
 * yet. Anything with several holders is left behind deliberately — HR is
 * the only one who knows which of the five sits in which item, and the
 * next migration drops the column rather than let a wrong guess outlive it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $items = DB::table('positions')
            ->whereNotNull('item_number')
            ->where('item_number', '<>', '')
            ->pluck('item_number', 'id');

        foreach ($items as $positionId => $item) {
            $holders = DB::table('employees')
                ->where('position_id', $positionId)
                ->whereNull('deleted_at')
                ->pluck('id');

            if ($holders->count() !== 1) {
                continue;
            }

            // Never over an item somebody already sits in, and never over
            // one already recorded against this person.
            $taken = DB::table('employees')
                ->where('item_number', $item)
                ->whereNull('deleted_at')
                ->exists();

            if ($taken) {
                continue;
            }

            DB::table('employees')
                ->where('id', $holders->first())
                ->whereNull('item_number')
                ->update(['item_number' => $item]);
        }
    }

    /**
     * Nothing to undo: the column these came from is dropped by the
     * migration after this one, and putting the numbers back on positions
     * would restore the very confusion this removes.
     */
    public function down(): void {}
};
