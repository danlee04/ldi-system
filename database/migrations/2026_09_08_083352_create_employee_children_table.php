<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Question 23 of Section II: the children, oldest first.
     */
    public function up(): void
    {
        Schema::create('employee_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date_of_birth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_children');
    }
};
