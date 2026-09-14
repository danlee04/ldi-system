<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ldna_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ldna_assessment_id')->constrained()->cascadeOnDelete();
            // No cascade: a competency somebody was rated on is deactivated,
            // never deleted, so the year it was used in keeps its answer.
            $table->foreignId('competency_id')->constrained();
            // Copied from the framework when the assessment was made. The
            // framework can change next year without moving this year's gap.
            $table->string('required_level');
            $table->string('self_level')->nullable();
            $table->string('supervisor_level')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['ldna_assessment_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ldna_ratings');
    }
};
