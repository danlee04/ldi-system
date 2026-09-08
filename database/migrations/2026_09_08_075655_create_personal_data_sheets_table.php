<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section I of CS Form No. 212 (Revised 2026).
     *
     * One row per employee, and every field is nullable: a PDS is filled
     * in over time by the person it belongs to, not in one sitting.
     *
     * Name, sex and suffix are not repeated here — they already live on
     * the employee record, and two copies would disagree.
     */
    public function up(): void
    {
        Schema::create('personal_data_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();

            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('civil_status', 30)->nullable();
            $table->string('civil_status_other')->nullable();
            $table->string('citizenship', 60)->nullable();
            $table->string('dual_citizenship_country')->nullable();

            $table->decimal('height_m', 4, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('blood_type', 10)->nullable();

            $table->string('umid_id_no', 40)->nullable();
            $table->string('pagibig_id_no', 40)->nullable();
            $table->string('philhealth_no', 40)->nullable();
            $table->string('philsys_card_number', 40)->nullable();
            $table->string('tin_no', 40)->nullable();
            $table->string('agency_employee_no', 40)->nullable();

            foreach (['residential', 'permanent'] as $address) {
                $table->string($address.'_house_block_lot')->nullable();
                $table->string($address.'_street')->nullable();
                $table->string($address.'_subdivision')->nullable();
                $table->string($address.'_barangay')->nullable();
                $table->string($address.'_city')->nullable();
                $table->string($address.'_province')->nullable();
                $table->string($address.'_zip', 10)->nullable();
            }

            $table->string('telephone_no', 40)->nullable();
            $table->string('mobile_no', 40)->nullable();
            $table->string('email_address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_data_sheets');
    }
};
