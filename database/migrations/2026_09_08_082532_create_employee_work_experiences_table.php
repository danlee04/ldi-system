<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section V of CS Form No. 212, one row per posting.
     *
     * The form asks for private employment too, so this is a work history
     * and not a list of appointments in this agency.
     */
    public function up(): void
    {
        Schema::create('employee_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            // Null means they are still in the post: the form prints "Present".
            $table->date('to_date')->nullable();
            $table->string('position_title');
            $table->string('agency_name');
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->string('salary_grade', 20)->nullable();
            $table->string('appointment_status', 60)->nullable();
            $table->boolean('is_government')->default(false);
            $table->timestamps();

            $table->index(['employee_id', 'from_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_experiences');
    }
};
