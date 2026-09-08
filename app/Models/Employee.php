<?php

namespace App\Models;

use App\Enums\EligibilityStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'position_id',
        'eligibility_id',
        'eligibility_detail',
        'eligibility_expires_on',
        'section_id',
        'division_id',
        'date_hired',
        'employment_status',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'date_hired' => 'date',
            'eligibility_expires_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            if ($employee->section_id !== null) {
                $employee->division_id = Section::query()
                    ->whereKey($employee->section_id)
                    ->value('division_id');
            }
        });
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Eligibility, $this>
     */
    public function eligibility(): BelongsTo
    {
        return $this->belongsTo(Eligibility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TrainingRecord, $this>
     */
    public function trainingRecords(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])->filter()->join(' '));
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Narrow to a state of civil service eligibility.
     *
     * An employee with no expiry date is not overdue — most eligibilities
     * (CSP, CSSP, career service) never lapse. Only a licence carries one.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeEligibilityStatus(Builder $query, EligibilityStatus $status): void
    {
        match ($status) {
            EligibilityStatus::Expiring => $query
                ->whereNotNull('eligibility_expires_on')
                ->whereBetween('eligibility_expires_on', [today(), today()->addYear()]),
            EligibilityStatus::Expired => $query
                ->whereNotNull('eligibility_expires_on')
                ->where('eligibility_expires_on', '<', today()),
            EligibilityStatus::NoExpiry => $query->whereNull('eligibility_expires_on'),
        };
    }

    /**
     * Employees the given user is allowed to see.
     *
     * Admin and HR see everyone. A division or section head sees their own
     * division or section. Everyone else sees only themselves.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role->seesEverything()) {
            return;
        }

        $employee = $user->employee;

        if ($employee === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        match ($user->role) {
            UserRole::DivisionHead => $query->where('division_id', $employee->division_id),
            UserRole::SectionHead => $query->where('section_id', $employee->section_id),
            default => $query->whereKey($employee->getKey()),
        };
    }
}
