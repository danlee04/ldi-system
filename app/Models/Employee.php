<?php

namespace App\Models;

use App\Enums\EligibilityStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'gender',
        'position_id',
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
     * Section IV of their PDS. The form allows several lines, so this is
     * the only place eligibility lives.
     *
     * @return HasMany<EmployeeEligibility, $this>
     */
    public function eligibilities(): HasMany
    {
        return $this->hasMany(EmployeeEligibility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Section I of their PDS. Absent until they start filling it in.
     *
     * @return HasOne<PersonalDataSheet, $this>
     */
    public function personalDataSheet(): HasOne
    {
        return $this->hasOne(PersonalDataSheet::class);
    }

    /**
     * Section III of their PDS, one row per level.
     *
     * @return HasMany<EmployeeEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    /**
     * Question 23 of Section II, oldest child first.
     *
     * @return HasMany<EmployeeChild, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(EmployeeChild::class)->orderBy('date_of_birth');
    }

    /**
     * Section VII of their PDS, most recent first.
     *
     * @return HasMany<EmployeeVoluntaryWork, $this>
     */
    public function voluntaryWorks(): HasMany
    {
        return $this->hasMany(EmployeeVoluntaryWork::class)->orderByDesc('from_date');
    }

    /**
     * Section VIII of their PDS: three lists kept in one relation.
     *
     * @return HasMany<EmployeeOtherInformation, $this>
     */
    public function otherInformation(): HasMany
    {
        return $this->hasMany(EmployeeOtherInformation::class);
    }

    /**
     * Section V of their PDS, most recent posting first — the order the
     * form asks for.
     *
     * @return HasMany<EmployeeWorkExperience, $this>
     */
    public function workExperiences(): HasMany
    {
        return $this->hasMany(EmployeeWorkExperience::class)->orderByDesc('from_date');
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
     * Surname first, the way a roster is read: "ABAO, LLOYD B."
     *
     * Lists are ordered by last name, and printing the first name first
     * makes that order look arbitrary to anybody scanning the column.
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function listingName(): Attribute
    {
        return Attribute::get(function (): string {
            $rest = collect([
                $this->first_name,
                $this->middle_name ? mb_substr($this->middle_name, 0, 1).'.' : null,
                $this->suffix,
            ])->filter()->join(' ');

            return $this->last_name.', '.$rest;
        });
    }

    /**
     * Section IV in one line: the first eligibility, plus a count of the
     * rest. A roster column has no room for four of them.
     */
    public function eligibilitySummary(): string
    {
        $names = $this->eligibilities
            ->map(fn (EmployeeEligibility $eligibility): string => $eligibility->name())
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return '—';
        }

        return $names->count() > 1
            ? $names->first().' +'.($names->count() - 1)
            : (string) $names->first();
    }

    /**
     * The soonest date any of their eligibilities lapses, if one does.
     */
    public function eligibilityExpiresOn(): ?CarbonImmutable
    {
        $soonest = null;

        foreach ($this->eligibilities as $eligibility) {
            $date = $eligibility->date_of_validity;

            if ($date !== null && ($soonest === null || $date->lessThan($soonest))) {
                $soonest = $date;
            }
        }

        return $soonest;
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
        $lapsing = fn (Builder $eligibility) => $eligibility->whereNotNull('date_of_validity');

        match ($status) {
            EligibilityStatus::Expiring => $query->whereHas(
                'eligibilities',
                fn (Builder $e) => $lapsing($e)->whereBetween('date_of_validity', [today(), today()->addYear()]),
            ),
            EligibilityStatus::Expired => $query->whereHas(
                'eligibilities',
                fn (Builder $e) => $lapsing($e)->where('date_of_validity', '<', today()),
            ),
            // Nothing on record that lapses: either no eligibility at all,
            // or only ones that never expire.
            EligibilityStatus::NoExpiry => $query->whereDoesntHave('eligibilities', $lapsing),
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
