<?php

namespace App\Actions\Ldna;

use App\Models\LdnaAssessment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveSupervisorRating
{
    public function __construct(private readonly ValidateRatingInput $input) {}

    /**
     * Records the levels a supervisor finds, with any remarks.
     *
     * Whether the person has rated themselves does not matter: only this
     * rating makes a gap, and a gap must not wait on somebody who never
     * filled in their own. Submitting asks for every competency and
     * records who rated. Once submitted it stays whole.
     *
     * @param  array<int, string|null>  $levels  a level's value, keyed by rating id
     * @param  array<int, string|null>  $remarks  keyed by rating id
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $by, LdnaAssessment $assessment, array $levels, array $remarks = [], bool $submit = false): void
    {
        Gate::forUser($by)->authorize('rate', $assessment);

        $ratings = $assessment->ratings()->get()->keyBy('id');

        $this->input->handle($ratings, $levels);
        $this->input->remarks($ratings, $remarks);

        if (($submit || $assessment->isRated()) && ! $this->input->completes($ratings, $levels, 'supervisor_level')) {
            throw ValidationException::withMessages([
                'levels' => __('Rate every competency before you submit.'),
            ]);
        }

        DB::transaction(function () use ($by, $assessment, $ratings, $levels, $remarks, $submit): void {
            foreach ($ratings as $id => $rating) {
                $changes = [];

                if (array_key_exists($id, $levels)) {
                    $changes['supervisor_level'] = filled($levels[$id]) ? $levels[$id] : null;
                }

                if (array_key_exists($id, $remarks)) {
                    $changes['remarks'] = filled($remarks[$id]) ? $remarks[$id] : null;
                }

                if ($changes !== []) {
                    $rating->update($changes);
                }
            }

            if ($submit) {
                $assessment->update(['rated_at' => now(), 'rated_by' => $by->getKey()]);
            }
        });
    }
}
