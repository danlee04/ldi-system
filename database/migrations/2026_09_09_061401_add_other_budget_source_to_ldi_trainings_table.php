<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A second fund on a plan.
     *
     * Some plans are paid from more than one pocket: HR carries an
     * individual employee's registration, while a whole-agency training
     * may be carried by HR entirely, or by another fund alongside it.
     *
     * Which pocket paid which peso is not something the plan records, so
     * this names the second fund and nothing more. Splitting the spend
     * would need an amount per source, which nobody enters today.
     */
    public function up(): void
    {
        Schema::table('ldi_trainings', function (Blueprint $table) {
            $table->string('other_budget_source')->nullable()->after('budget_source');
        });
    }

    public function down(): void
    {
        Schema::table('ldi_trainings', function (Blueprint $table) {
            $table->dropColumn('other_budget_source');
        });
    }
};
