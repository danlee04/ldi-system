<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ldna_cycles', function (Blueprint $table) {
            $table->id();
            // The year being planned for: LDNA 2027 is carried out late in 2026.
            $table->unsignedSmallInteger('year')->unique();
            $table->date('opens_on');
            $table->date('closes_on');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ldna_cycles');
    }
};
