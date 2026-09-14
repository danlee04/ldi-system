<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ldna_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ldna_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            // The position they held when the assessment was made or last
            // refreshed, which is what their technical competencies came from.
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('rated_at')->nullable();
            // Who actually rated. Who ought to is worked out when it is
            // asked, the way the approvals queue does it.
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ldna_cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ldna_assessments');
    }
};
