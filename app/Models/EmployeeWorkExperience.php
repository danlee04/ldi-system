<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmployeeWorkExperienceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of Section V of CS Form No. 212.
 *
 * @property int $id
 * @property int $employee_id
 * @property CarbonImmutable $from_date
 * @property CarbonImmutable|null $to_date
 * @property string $position_title
 * @property string $agency_name
 * @property string|null $monthly_salary
 * @property string|null $salary_grade
 * @property string|null $appointment_status
 * @property bool $is_government
 */
class EmployeeWorkExperience extends Model
{
    /** @use HasFactory<EmployeeWorkExperienceFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'from_date',
        'to_date',
        'position_title',
        'agency_name',
        'monthly_salary',
        'salary_grade',
        'appointment_status',
        'is_government',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'is_government' => 'boolean',
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
     * What the "To" column prints. A post they still hold has no end date,
     * and the form wants the word rather than a blank.
     */
    public function endsOn(): string
    {
        return $this->to_date?->format('d/m/Y') ?? 'Present';
    }

    /**
     * The kinds of appointment CSC recognises, in the order they are
     * commonest here.
     *
     * @return list<string>
     */
    public static function appointmentStatuses(): array
    {
        return [
            'Permanent',
            'Temporary',
            'Casual',
            'Contractual',
            'Contract of Service',
            'Job Order',
            'Co-terminous',
            'Provisional',
            'Substitute',
            'Elected',
        ];
    }
}
