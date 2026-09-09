<?php

namespace App\Models;

use Database\Factories\EmployeeReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of question 41 of CS Form No. 212.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $full_name
 * @property string|null $address
 * @property string|null $contact
 */
class EmployeeReference extends Model
{
    /** @use HasFactory<EmployeeReferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'full_name',
        'address',
        'contact',
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
