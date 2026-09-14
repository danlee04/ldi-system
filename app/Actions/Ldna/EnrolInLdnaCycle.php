<?php

namespace App\Actions\Ldna;

use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\LdnaCycle;
use Illuminate\Support\Facades\DB;

class EnrolInLdnaCycle
{
    public function __construct(private readonly BuildCompetencyProfile $profile) {}

    /**
     * Makes a person's assessment for the cycle, copying in the level each
     * of their competencies asks for today.
     *
     * The copy is the point. The framework can change next year without
     * moving this year's gaps.
     */
    public function handle(LdnaCycle $cycle, Employee $employee): LdnaAssessment
    {
        return DB::transaction(function () use ($cycle, $employee): LdnaAssessment {
            $assessment = $cycle->assessments()->create([
                'employee_id' => $employee->getKey(),
                'position_id' => $employee->position_id,
            ]);

            foreach ($this->profile->handle($employee) as $competencyId => $level) {
                $assessment->ratings()->create([
                    'competency_id' => $competencyId,
                    'required_level' => $level,
                ]);
            }

            return $assessment;
        });
    }
}
