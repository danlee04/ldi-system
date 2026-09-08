<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The development partner finances the training; the facilitator runs
     * it. Only the facilitator belongs on a PDS as "Conducted/Sponsored by".
     */
    public function up(): void
    {
        Schema::table('ldi_trainings', function (Blueprint $table) {
            $table->string('facilitator')->after('development_partner');
            $table->string('training_communication')->nullable()->after('type_of_training');
            $table->float('cpd_units')->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('ldi_trainings', function (Blueprint $table) {
            $table->dropColumn(['facilitator', 'training_communication', 'cpd_units']);
        });
    }
};
