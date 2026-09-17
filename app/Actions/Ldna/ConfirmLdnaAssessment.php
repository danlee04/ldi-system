<?php

namespace App\Actions\Ldna;

use App\Models\LdnaAssessment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConfirmLdnaAssessment
{
    public function __construct(private readonly ValidateRatingInput $input) {}

    /**
     * The head reads what somebody said about themselves and agrees to it.
     *
     * Nobody is rated here: the levels are the person's own and are not
     * touched. All the head adds is any remark, and the stamp that lets
     * the assessment count toward the gap.
     *
     * Nothing can be confirmed before it is submitted — a draft is theirs
     * to change, and confirming one would freeze an answer they had not
     * finished giving.
     *
     * @param  array<int, string|null>  $remarks  keyed by rating id
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $by, LdnaAssessment $assessment, array $remarks = [], bool $confirm = false): void
    {
        Gate::forUser($by)->authorize('confirm', $assessment);

        $ratings = $assessment->ratings()->get()->keyBy('id');

        $this->input->remarks($ratings, $remarks);

        if ($confirm && ! $assessment->isSelfSubmitted()) {
            throw ValidationException::withMessages([
                'confirm' => __('They have not submitted their assessment yet.'),
            ]);
        }

        DB::transaction(function () use ($by, $assessment, $ratings, $remarks, $confirm): void {
            foreach ($remarks as $id => $remark) {
                $ratings[$id]->update(['remarks' => filled($remark) ? $remark : null]);
            }

            if ($confirm) {
                $assessment->update(['confirmed_at' => now(), 'confirmed_by' => $by->getKey()]);
            }
        });
    }
}
