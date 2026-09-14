<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->string('level');
            $table->text('description');
            $table->timestamps();

            $table->unique(['competency_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_indicators');
    }
};
