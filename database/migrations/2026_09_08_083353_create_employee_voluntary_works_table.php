<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section VII: voluntary work in civic or non-government organisations.
     */
    public function up(): void
    {
        Schema::create('employee_voluntary_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // The form asks for the name and the address in one box.
            $table->string('organization');
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->unsignedSmallInteger('hours')->nullable();
            $table->string('position')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'from_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_voluntary_works');
    }
};
