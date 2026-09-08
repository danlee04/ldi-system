<?php

namespace App\Models;

use Database\Factories\EligibilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Eligibility extends Model
{
    /** @use HasFactory<EligibilityFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * The lines of Section IV that named this eligibility.
     *
     * @return HasMany<EmployeeEligibility, $this>
     */
    public function employeeEligibilities(): HasMany
    {
        return $this->hasMany(EmployeeEligibility::class);
    }
}
