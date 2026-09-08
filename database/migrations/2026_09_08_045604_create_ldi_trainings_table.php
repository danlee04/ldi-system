<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ldi_trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('development_partner');
            $table->string('type_of_training')->nullable();
            $table->date('date_start');
            $table->date('date_end')->index();
            $table->unsignedSmallInteger('hours');
            $table->string('ld_type');
            $table->string('ld_type_other')->nullable();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('target_attendees')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('budget_source')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ldi_trainings');
    }
};
