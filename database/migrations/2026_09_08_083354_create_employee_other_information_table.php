<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section VIII: three lists side by side on the form — skills and
     * hobbies, non-academic distinctions, and memberships. One table with
     * a type keeps them from becoming three near-identical ones.
     */
    public function up(): void
    {
        Schema::create('employee_other_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('description');
            $table->timestamps();

            $table->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_other_information');
    }
};
