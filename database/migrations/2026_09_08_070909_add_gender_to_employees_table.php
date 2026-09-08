<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The DOH training report counts participants by sex, so the roster has
     * to carry it. Nullable: the legacy roster leaves one person blank.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('suffix');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
