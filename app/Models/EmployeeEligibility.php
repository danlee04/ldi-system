<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmployeeEligibilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of Section IV of CS Form No. 212.
 *
 * @property int $id
 * @property int $employee_id
 * @property int|null $eligibility_id
 * @property string|null $detail
 * @property string|null $rating
 * @property CarbonImmutable|null $date_of_examination
 * @property string|null $place_of_examination
 * @property string|null $license_number
 * @property CarbonImmutable|null $date_of_validity
 */
class EmployeeEligibility extends Model
{
    /** @use HasFactory<EmployeeEligibilityFactory> */
    use HasFactory;

    /**
     * Laravel's inflector leaves "Eligibilities" alone, so the table is
     * named here rather than guessed.
     */
    protected $table = 'employee_eligibilities';

    protected $fillable = [
        'employee_id',
        'eligibility_id',
        'detail',
        'rating',
        'date_of_examination',
        'place_of_examination',
        'license_number',
        'date_of_validity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_examination' => 'date',
            'date_of_validity' => 'date',
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
     * @return BelongsTo<Eligibility, $this>
     */
    public function eligibility(): BelongsTo
    {
        return $this->belongsTo(Eligibility::class);
    }

    /**
     * What to print: the free text if there is any, otherwise the name of
     * the eligibility that was chosen from the list.
     */
    public function name(): string
    {
        return $this->detail ?: ($this->eligibility->name ?? '');
    }
}
