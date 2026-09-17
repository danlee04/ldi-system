<?php

namespace App\Actions\Ldna;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\LdnaAssessment;
use App\Models\User;
use App\Notifications\SelfRatingSubmitted;
use App\Workflow\LdnaConfirmer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SaveSelfRating
{
    public function __construct(
        private readonly ValidateRatingInput $input,
        private readonly LdnaConfirmer $confirmer,
    ) {}

    /**
     * Records the levels people give themselves.
     *
     * Saving half done is allowed. Submitting asks for every competency
     * and tells whoever confirms it — the first time only. Once submitted
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

        if (($submit || $assessment->isSelfSubmitted()) && ! $this->input->completes($ratings, $levels)) {
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
            $this->tellConfirmer($assessment);
        }
    }

    private function tellConfirmer(LdnaAssessment $assessment): void
    {
        $confirmerId = $this->confirmer->confirmerIdFor($assessment->employee);
        $confirmerUserId = $confirmerId === null ? null : Employee::query()->whereKey($confirmerId)->value('user_id');

        // Nobody confirms them, or the one who does has no account to
        // tell: either way the submission must not go untold, so HR
        // hears it.
        $recipients = $confirmerUserId === null
            ? User::query()->where('role', UserRole::Hr)->where('is_active', true)->get()
            : User::query()->where('is_active', true)->whereKey($confirmerUserId)->get();

        Notification::send($recipients, new SelfRatingSubmitted($assessment));
    }
}
