<?php

namespace App\Actions\Ldna;

use App\Models\LdnaAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefreshLdnaAssessment
{
    public function __construct(private readonly BuildCompetencyProfile $profile) {}

    /**
     * Brings one person's assessment in line with where they stand now —
     * after a move to another position, or being named a head.
     *
     * A competency they still have keeps its ratings, against the level it
     * now asks. One they no longer have goes. A new one arrives unrated,
     * which reopens anything submitted, since it is no longer whole.
     *
     * This action takes no User to check, so the caller must already have
     * confirmed HR or admin before calling it.
     *
     * @throws ValidationException once the cycle has closed
     */
    public function handle(LdnaAssessment $assessment): void
    {
        $assessment->cycle->ensureNotClosed();

        $employee = $assessment->employee;
        $profile = $this->profile->handle($employee);

        DB::transaction(function () use ($assessment, $employee, $profile): void {
            $assessment->ratings()->whereNotIn('competency_id', array_keys($profile))->delete();

            $kept = $assessment->ratings()->pluck('competency_id')->map(fn (mixed $id): int => (int) $id)->all();
            $added = false;

            foreach ($profile as $competencyId => $level) {
                if (in_array($competencyId, $kept, true)) {
                    $assessment->ratings()->where('competency_id', $competencyId)->update(['required_level' => $level->value]);

                    continue;
                }

                $assessment->ratings()->create(['competency_id' => $competencyId, 'required_level' => $level]);
                $added = true;
            }

            $assessment->update([
                'position_id' => $employee->position_id,
                ...($added ? ['self_submitted_at' => null, 'rated_at' => null, 'rated_by' => null] : []),
            ]);
        });
    }
}
