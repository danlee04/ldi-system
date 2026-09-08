<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('date_start');
            $table->date('date_end')->index();
            $table->unsignedSmallInteger('hours');
            $table->string('ld_type');
            $table->string('ld_type_other')->nullable();
            $table->string('conducted_by');
            $table->string('location')->nullable();
            $table->decimal('expenses', 10, 2)->nullable();
            $table->decimal('registration_fee', 10, 2)->nullable();
            $table->decimal('tev', 10, 2)->nullable();
            $table->float('cpd_units')->nullable();
            $table->string('status')->index();
            $table->string('current_level')->nullable()->index();
            $table->foreignId('submitted_by')->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};
