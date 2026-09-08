<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_caps', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('budget_source');
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['year', 'budget_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_caps');
    }
};
