<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section III of CS Form No. 212.
     *
     * The form gives exactly one line per level, so the table does too:
     * one row per employee per level, and nothing to sort at print time.
     */
    public function up(): void
    {
        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20);
            $table->string('school_name')->nullable();
            $table->string('degree_course')->nullable();
            $table->unsignedSmallInteger('period_from')->nullable();
            $table->unsignedSmallInteger('period_to')->nullable();
            $table->string('highest_level_units')->nullable();
            $table->unsignedSmallInteger('year_graduated')->nullable();
            $table->string('honors')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_educations');
    }
};
