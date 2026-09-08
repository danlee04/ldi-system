<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmployeeChildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of question 23, Section II of CS Form No. 212.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $full_name
 * @property CarbonImmutable|null $date_of_birth
 */
class EmployeeChild extends Model
{
    /** @use HasFactory<EmployeeChildFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'full_name',
        'date_of_birth',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
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
