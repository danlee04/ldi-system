<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // The face belongs to the person, not to their sign-in: an
            // employee outlives the account, and CS Form 212 has a photo
            // box this can fill one day.
            $table->string('photo_path')->nullable()->after('suffix');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
