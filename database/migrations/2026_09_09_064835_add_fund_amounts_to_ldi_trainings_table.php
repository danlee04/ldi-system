<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much each fund actually carried.
     *
     * A plan can cost 19,000 while HR's budget covers only the 6,000
     * registration. Naming the second fund was not enough to answer
     * "what did HR fund this year" — the whole 19,000 was landing
     * against HR because the amount was never recorded.
     *
     * Which part of the cost a fund covers is not a rule the system can
     * work out: sometimes HR carries the registration only, sometimes
     * the lot. So the amount is stated rather than inferred.
     *
     * Null means the whole of the plan's budget came from its own
     * source, which is what every existing plan means today.
     */
    public function up(): void
    {
        Schema::table('ldi_trainings', function (Blueprint $table) {
            $table->decimal('budget_amount', 12, 2)->nullable()->after('budget_source');
            $table->decimal('other_budget_amount', 12, 2)->nullable()->after('other_budget_source');
        });
    }

    public function down(): void
    {
        Schema::table('ldi_trainings', function (Blueprint $table) {
            $table->dropColumn(['budget_amount', 'other_budget_amount']);
        });
    }
};
