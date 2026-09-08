<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('eligibility_id')->nullable()->after('position_id')->constrained()->nullOnDelete();
            $table->string('eligibility_detail')->nullable()->after('eligibility_id');
            $table->date('eligibility_expires_on')->nullable()->after('eligibility_detail')->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eligibility_id');
            $table->dropColumn(['eligibility_detail', 'eligibility_expires_on']);
        });
    }
};
