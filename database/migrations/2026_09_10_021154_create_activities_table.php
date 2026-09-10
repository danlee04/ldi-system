<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type')->index();
            $table->date('date_start');
            // A month is read by the day a thing ends as often as by the day
            // it starts, so both ends are worth an index.
            $table->date('date_end')->index();
            // Null on both means the whole day, which is most of them.
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
