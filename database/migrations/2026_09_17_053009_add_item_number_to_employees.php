<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plantilla item a person sits in, and an employee number that is no
 * longer compulsory.
 *
 * The item number is kept per employee rather than read off the position,
 * because two people can hold the same position under different items.
 *
 * employee_number becomes nullable because the roster form no longer asks
 * for it. The unique index stays: a blank is not a duplicate, so several
 * rows may carry none while every real number is still unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('item_number')->nullable()->after('position_id');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('employee_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('employee_number')->nullable(false)->change();
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('item_number');
        });
    }
};
