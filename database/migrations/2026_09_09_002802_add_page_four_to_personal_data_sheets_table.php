<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Page 4 of CS Form No. 212: questions 34 to 40, and the government
     * ID at 42.
     *
     * Every question is a yes or a no with details behind it. The columns
     * are nullable booleans on purpose — until the employee answers, the
     * form should show neither box ticked rather than guess at "no".
     */
    public function up(): void
    {
        Schema::table('personal_data_sheets', function (Blueprint $table) {
            $table->boolean('related_within_third_degree')->nullable();
            $table->string('related_within_third_degree_detail')->nullable();
            $table->boolean('related_within_fourth_degree')->nullable();
            $table->string('related_within_fourth_degree_detail')->nullable();

            $table->boolean('found_guilty_administrative')->nullable();
            $table->string('found_guilty_administrative_detail')->nullable();
            $table->boolean('criminally_charged')->nullable();
            $table->string('criminally_charged_detail')->nullable();
            $table->date('criminally_charged_date_filed')->nullable();
            $table->string('criminally_charged_status')->nullable();

            $table->boolean('convicted_of_crime')->nullable();
            $table->string('convicted_of_crime_detail')->nullable();
            $table->boolean('separated_from_service')->nullable();
            $table->string('separated_from_service_detail')->nullable();

            $table->boolean('election_candidate')->nullable();
            $table->string('election_candidate_detail')->nullable();
            $table->boolean('resigned_for_election')->nullable();
            $table->string('resigned_for_election_detail')->nullable();

            $table->boolean('immigrant_or_resident')->nullable();
            $table->string('immigrant_or_resident_country')->nullable();

            $table->boolean('indigenous_member')->nullable();
            $table->string('indigenous_group')->nullable();
            $table->boolean('person_with_disability')->nullable();
            $table->string('pwd_id_no', 60)->nullable();
            $table->boolean('solo_parent')->nullable();
            $table->string('solo_parent_id_no', 60)->nullable();

            $table->string('government_id_type')->nullable();
            $table->string('government_id_number', 60)->nullable();
            $table->string('government_id_issued')->nullable();

            // Question 16 is a pair of boxes, not free text: the second one
            // asks whether dual citizenship came by birth or naturalisation.
            $table->string('dual_citizenship_basis', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_data_sheets', function (Blueprint $table) {
            $table->dropColumn([
                'related_within_third_degree', 'related_within_third_degree_detail',
                'related_within_fourth_degree', 'related_within_fourth_degree_detail',
                'found_guilty_administrative', 'found_guilty_administrative_detail',
                'criminally_charged', 'criminally_charged_detail',
                'criminally_charged_date_filed', 'criminally_charged_status',
                'convicted_of_crime', 'convicted_of_crime_detail',
                'separated_from_service', 'separated_from_service_detail',
                'election_candidate', 'election_candidate_detail',
                'resigned_for_election', 'resigned_for_election_detail',
                'immigrant_or_resident', 'immigrant_or_resident_country',
                'indigenous_member', 'indigenous_group',
                'person_with_disability', 'pwd_id_no',
                'solo_parent', 'solo_parent_id_no',
                'government_id_type', 'government_id_number', 'government_id_issued',
                'dual_citizenship_basis',
            ]);
        });
    }
};
