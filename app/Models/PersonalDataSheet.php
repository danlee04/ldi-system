<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PersonalDataSheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sections I and II of CS Form No. 212 — what only the employee can
 * tell us, and their immediate family.
 *
 * @property int $id
 * @property int $employee_id
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $place_of_birth
 * @property string|null $civil_status
 * @property string|null $civil_status_other
 * @property string|null $citizenship
 * @property string|null $dual_citizenship_country
 * @property string|null $height_m
 * @property string|null $weight_kg
 * @property string|null $blood_type
 * @property string|null $umid_id_no
 * @property string|null $pagibig_id_no
 * @property string|null $philhealth_no
 * @property string|null $philsys_card_number
 * @property string|null $tin_no
 * @property string|null $agency_employee_no
 * @property string|null $residential_house_block_lot
 * @property string|null $residential_street
 * @property string|null $residential_subdivision
 * @property string|null $residential_barangay
 * @property string|null $residential_city
 * @property string|null $residential_province
 * @property string|null $residential_zip
 * @property string|null $permanent_house_block_lot
 * @property string|null $permanent_street
 * @property string|null $permanent_subdivision
 * @property string|null $permanent_barangay
 * @property string|null $permanent_city
 * @property string|null $permanent_province
 * @property string|null $permanent_zip
 * @property string|null $telephone_no
 * @property string|null $mobile_no
 * @property string|null $email_address
 * @property string|null $spouse_last_name
 * @property string|null $spouse_first_name
 * @property string|null $spouse_middle_name
 * @property string|null $spouse_suffix
 * @property string|null $spouse_occupation
 * @property string|null $spouse_employer
 * @property string|null $spouse_business_address
 * @property string|null $spouse_telephone_no
 * @property string|null $father_last_name
 * @property string|null $father_first_name
 * @property string|null $father_middle_name
 * @property string|null $father_suffix
 * @property string|null $mother_last_name
 * @property string|null $mother_first_name
 * @property string|null $mother_middle_name
 * @property bool|null $related_within_third_degree
 * @property bool|null $related_within_fourth_degree
 * @property bool|null $found_guilty_administrative
 * @property bool|null $criminally_charged
 * @property bool|null $convicted_of_crime
 * @property bool|null $separated_from_service
 * @property bool|null $election_candidate
 * @property bool|null $resigned_for_election
 * @property bool|null $immigrant_or_resident
 * @property bool|null $indigenous_member
 * @property bool|null $person_with_disability
 * @property bool|null $solo_parent
 * @property CarbonImmutable|null $criminally_charged_date_filed
 * @property string|null $related_within_third_degree_detail
 * @property string|null $related_within_fourth_degree_detail
 * @property string|null $found_guilty_administrative_detail
 * @property string|null $criminally_charged_detail
 * @property string|null $criminally_charged_status
 * @property string|null $convicted_of_crime_detail
 * @property string|null $separated_from_service_detail
 * @property string|null $election_candidate_detail
 * @property string|null $resigned_for_election_detail
 * @property string|null $immigrant_or_resident_country
 * @property string|null $indigenous_group
 * @property string|null $pwd_id_no
 * @property string|null $solo_parent_id_no
 * @property string|null $government_id_type
 * @property string|null $government_id_number
 * @property string|null $government_id_issued
 * @property string|null $dual_citizenship_basis
 */
class PersonalDataSheet extends Model
{
    /** @use HasFactory<PersonalDataSheetFactory> */
    use HasFactory;

    /**
     * Everything the employee fills in themselves.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'date_of_birth',
        'place_of_birth',
        'civil_status',
        'civil_status_other',
        'citizenship',
        'dual_citizenship_country',
        'height_m',
        'weight_kg',
        'blood_type',
        'umid_id_no',
        'pagibig_id_no',
        'philhealth_no',
        'philsys_card_number',
        'tin_no',
        'agency_employee_no',
        'residential_house_block_lot',
        'residential_street',
        'residential_subdivision',
        'residential_barangay',
        'residential_city',
        'residential_province',
        'residential_zip',
        'permanent_house_block_lot',
        'permanent_street',
        'permanent_subdivision',
        'permanent_barangay',
        'permanent_city',
        'permanent_province',
        'permanent_zip',
        'telephone_no',
        'mobile_no',
        'email_address',

        // Section II, which is one to a person just as Section I is.
        'spouse_last_name',
        'spouse_first_name',
        'spouse_middle_name',
        'spouse_suffix',
        'spouse_occupation',
        'spouse_employer',
        'spouse_business_address',
        'spouse_telephone_no',
        'father_last_name',
        'father_first_name',
        'father_middle_name',
        'father_suffix',
        'mother_last_name',
        'mother_first_name',
        'mother_middle_name',

        // Page 4: questions 34 to 40, the government ID at 42, and the
        // second half of question 16.
        'convicted_of_crime',
        'convicted_of_crime_detail',
        'criminally_charged',
        'criminally_charged_date_filed',
        'criminally_charged_detail',
        'criminally_charged_status',
        'dual_citizenship_basis',
        'election_candidate',
        'election_candidate_detail',
        'found_guilty_administrative',
        'found_guilty_administrative_detail',
        'government_id_issued',
        'government_id_number',
        'government_id_type',
        'immigrant_or_resident',
        'immigrant_or_resident_country',
        'indigenous_group',
        'indigenous_member',
        'person_with_disability',
        'pwd_id_no',
        'related_within_fourth_degree',
        'related_within_fourth_degree_detail',
        'related_within_third_degree',
        'related_within_third_degree_detail',
        'resigned_for_election',
        'resigned_for_election_detail',
        'separated_from_service',
        'separated_from_service_detail',
        'solo_parent',
        'solo_parent_id_no',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'related_within_third_degree' => 'boolean',
            'related_within_fourth_degree' => 'boolean',
            'found_guilty_administrative' => 'boolean',
            'criminally_charged' => 'boolean',
            'convicted_of_crime' => 'boolean',
            'separated_from_service' => 'boolean',
            'election_candidate' => 'boolean',
            'resigned_for_election' => 'boolean',
            'immigrant_or_resident' => 'boolean',
            'indigenous_member' => 'boolean',
            'person_with_disability' => 'boolean',
            'solo_parent' => 'boolean',
            'criminally_charged_date_filed' => 'date',
            'date_of_birth' => 'date',
            'height_m' => 'decimal:2',
            'weight_kg' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The five the form prints. There is no box for anything else,
     * so an option that is not here could never reach CSC.
     *
     * @return list<string>
     */
    public static function civilStatuses(): array
    {
        return ['Single', 'Married', 'Widowed', 'Separated', 'Others'];
    }

    /**
     * How far through Section I this employee is, so the page can say what
     * is still missing instead of leaving them guessing.
     */
    public function completeness(): int
    {
        $required = [
            'date_of_birth', 'place_of_birth', 'civil_status', 'citizenship',
            'height_m', 'weight_kg', 'blood_type',
            'residential_house_block_lot', 'residential_barangay', 'residential_city', 'residential_province',
            'permanent_house_block_lot', 'permanent_barangay', 'permanent_city', 'permanent_province',
            'mobile_no', 'email_address',
        ];

        $filled = collect($required)->filter(fn (string $field): bool => filled($this->{$field}))->count();

        return (int) round($filled / count($required) * 100);
    }
}
