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
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
