<?php

namespace App\Models;

use App\Enums\OtherInformationType;
use Database\Factories\EmployeeOtherInformationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of one of the three lists in Section VIII.
 *
 * @property int $id
 * @property int $employee_id
 * @property OtherInformationType $type
 * @property string $description
 */
class EmployeeOtherInformation extends Model
{
    /** @use HasFactory<EmployeeOtherInformationFactory> */
    use HasFactory;

    /**
     * Laravel's inflector treats "Information" as uncountable, so the
     * table is named here rather than guessed.
     */
    protected $table = 'employee_other_information';

    protected $fillable = [
        'employee_id',
        'type',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OtherInformationType::class,
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
