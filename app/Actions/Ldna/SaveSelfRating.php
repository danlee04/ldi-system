<?php

namespace App\Actions\Ldna;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\User;
use App\Notifications\SelfRatingSubmitted;
use App\Workflow\LdnaRater;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SaveSelfRating
{
    public function __construct(
        private readonly ValidateRatingInput $input,
        private readonly LdnaRater $rater,
    ) {}

    /**
     * Records the levels people give themselves.
     *
     * Saving half done is allowed. Submitting asks for every competency
     * and tells whoever rates them — the first time only. Once submitted
     * it stays whole: a level can be changed until the cycle closes, but
     * not cleared.
     *
     * @param  array<int, string|null>  $levels  a level's value, keyed by rating id
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $by, LdnaAssessment $assessment, array $levels, bool $submit = false): void
    {
        Gate::forUser($by)->authorize('selfRate', $assessment);

        $ratings = $assessment->ratings()->get()->keyBy('id');

        $this->input->handle($ratings, $levels);

        if (($submit || $assessment->isSelfSubmitted()) && ! $this->input->completes($ratings, $levels, 'self_level')) {
            throw ValidationException::withMessages([
                'levels' => __('Give yourself a level on every competency before you submit.'),
            ]);
        }

        $firstSubmission = $submit && ! $assessment->isSelfSubmitted();

        DB::transaction(function () use ($assessment, $ratings, $levels, $firstSubmission): void {
            foreach ($levels as $ratingId => $level) {
                $ratings[$ratingId]->update(['self_level' => filled($level) ? $level : null]);
            }

            if ($firstSubmission) {
                $assessment->update(['self_submitted_at' => now()]);
            }
        });

        if ($firstSubmission) {
            $this->tellRater($assessment);
        }
    }

    private function tellRater(LdnaAssessment $assessment): void
    {
        $raterId = $this->rater->raterIdFor($assessment->employee);

        $recipients = $raterId === null
            ? User::query()->where('role', UserRole::Hr)->where('is_active', true)->get()
            : User::query()->where('is_active', true)->whereKey(Employee::query()->whereKey($raterId)->value('user_id'))->get();

        Notification::send($recipients, new SelfRatingSubmitted($assessment));
    }
}
