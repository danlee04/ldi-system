<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmployeeVoluntaryWorkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of Section VII of CS Form No. 212.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $organization
 * @property CarbonImmutable $from_date
 * @property CarbonImmutable|null $to_date
 * @property int|null $hours
 * @property string|null $position
 */
class EmployeeVoluntaryWork extends Model
{
    /** @use HasFactory<EmployeeVoluntaryWorkFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization',
        'from_date',
        'to_date',
        'hours',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'hours' => 'integer',
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
