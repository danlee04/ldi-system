<?php

namespace App\Models;

use App\Enums\EducationLevel;
use Database\Factories\EmployeeEducationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of Section III of CS Form No. 212.
 *
 * @property int $id
 * @property int $employee_id
 * @property EducationLevel $level
 * @property string|null $school_name
 * @property string|null $degree_course
 * @property int|null $period_from
 * @property int|null $period_to
 * @property string|null $highest_level_units
 * @property int|null $year_graduated
 * @property string|null $honors
 */
class EmployeeEducation extends Model
{
    /** @use HasFactory<EmployeeEducationFactory> */
    use HasFactory;

    /**
     * Laravel's inflector treats "Education" as uncountable, so it would
     * look for employee_education. The table is named here instead.
     */
    protected $table = 'employee_educations';

    protected $fillable = [
        'employee_id',
        'level',
        'school_name',
        'degree_course',
        'period_from',
        'period_to',
        'highest_level_units',
        'year_graduated',
        'honors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => EducationLevel::class,
            'period_from' => 'integer',
            'period_to' => 'integer',
            'year_graduated' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
