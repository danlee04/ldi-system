<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Technical competencies only: which positions need one, and how well.
        Schema::create('competency_position', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->string('required_level');
            $table->timestamps();

            $table->unique(['position_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_position');
    }
};
