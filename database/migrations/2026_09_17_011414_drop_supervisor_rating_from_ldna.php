<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The needs assessment is a self assessment. Nobody rates anybody else.
 *
 * The head still has a part in it — they read what the person said about
 * themselves and confirm it, which is what lets it count toward the gap —
 * so the two stamps stay. They are renamed for what they now record: a
 * confirmation, not a rating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ldna_ratings', function (Blueprint $table): void {
            $table->dropColumn('supervisor_level');
        });

        Schema::table('ldna_assessments', function (Blueprint $table): void {
            $table->renameColumn('rated_at', 'confirmed_at');
        });

        Schema::table('ldna_assessments', function (Blueprint $table): void {
            $table->renameColumn('rated_by', 'confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('ldna_assessments', function (Blueprint $table): void {
            $table->renameColumn('confirmed_at', 'rated_at');
        });

        Schema::table('ldna_assessments', function (Blueprint $table): void {
            $table->renameColumn('confirmed_by', 'rated_by');
        });

        Schema::table('ldna_ratings', function (Blueprint $table): void {
            $table->string('supervisor_level')->nullable()->after('self_level');
        });
    }
};
