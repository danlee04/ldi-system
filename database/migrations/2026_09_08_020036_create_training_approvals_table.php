<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_record_id')->constrained()->cascadeOnDelete();
            $table->string('level');
            $table->foreignId('approver_user_id')->constrained('users');
            $table->string('decision');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_approvals');
    }
};
