<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section II of CS Form No. 212, less the children.
     *
     * A spouse and two parents are one to a person, so they sit beside
     * Section I rather than in a table of their own.
     */
    public function up(): void
    {
        Schema::table('personal_data_sheets', function (Blueprint $table) {
            $table->string('spouse_last_name')->nullable();
            $table->string('spouse_first_name')->nullable();
            $table->string('spouse_middle_name')->nullable();
            $table->string('spouse_suffix', 20)->nullable();
            $table->string('spouse_occupation')->nullable();
            $table->string('spouse_employer')->nullable();
            $table->string('spouse_business_address')->nullable();
            $table->string('spouse_telephone_no', 40)->nullable();
            $table->string('father_last_name')->nullable();
            $table->string('father_first_name')->nullable();
            $table->string('father_middle_name')->nullable();
            $table->string('father_suffix', 20)->nullable();
            $table->string('mother_last_name')->nullable();
            $table->string('mother_first_name')->nullable();
            $table->string('mother_middle_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_data_sheets', function (Blueprint $table) {
            $table->dropColumn([
                'spouse_last_name', 'spouse_first_name', 'spouse_middle_name', 'spouse_suffix',
                'spouse_occupation', 'spouse_employer', 'spouse_business_address', 'spouse_telephone_no',
                'father_last_name', 'father_first_name', 'father_middle_name', 'father_suffix',
                'mother_last_name', 'mother_first_name', 'mother_middle_name',
            ]);
        });
    }
};
