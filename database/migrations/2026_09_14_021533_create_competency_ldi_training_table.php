<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which competencies an LDI plan sets out to build — what lets the
        // gap report say whether a gap has a plan answering it.
        Schema::create('competency_ldi_training', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ldi_training_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['competency_id', 'ldi_training_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_ldi_training');
    }
};
