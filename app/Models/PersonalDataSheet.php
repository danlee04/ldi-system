<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PersonalDataSheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Section I of CS Form No. 212 — what only the employee can tell us.
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * The civil status options the form offers, in its own order.
     *
     * @return list<string>
     */
    public static function civilStatuses(): array
    {
        return ['Single', 'Married', 'Widow/er', 'Separated', 'Solo Parent', 'Others'];
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
