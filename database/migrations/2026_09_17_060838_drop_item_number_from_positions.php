<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One place for the plantilla item, and it is the employee.
 *
 * The migration before this one moved every number that could be said to
 * belong to somebody. What is left here belonged to no one person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->dropColumn('item_number');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->string('item_number')->nullable()->after('title');
        });
    }
};
