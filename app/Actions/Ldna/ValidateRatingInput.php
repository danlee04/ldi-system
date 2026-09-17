<?php

namespace App\Actions\Ldna;

use App\Enums\ProficiencyLevel;
use App\Models\LdnaRating;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Checks the levels sent for an assessment before any is written: each
 * must belong to it, and be one of the four levels or blank.
 */
class ValidateRatingInput
{
    /**
     * @param  Collection<int, LdnaRating>  $ratings  the assessment's ratings, keyed by id
     * @param  array<int, string|null>  $levels  keyed by rating id
     *
     * @throws ValidationException
     */
    public function handle(Collection $ratings, array $levels): void
    {
        foreach ($levels as $ratingId => $level) {
            if (! $ratings->has($ratingId)) {
                throw ValidationException::withMessages(['levels' => __('That competency is not part of this assessment.')]);
            }

            if (filled($level) && ProficiencyLevel::tryFrom((string) $level) === null) {
                throw ValidationException::withMessages(['levels' => __('Pick one of the four levels.')]);
            }
        }
    }

    /**
     * Whether every rating would carry a level once these are written.
     *
     * @param  Collection<int, LdnaRating>  $ratings  keyed by id
     * @param  array<int, string|null>  $levels  keyed by rating id
     */
    public function completes(Collection $ratings, array $levels): bool
    {
        return $ratings->every(function (LdnaRating $rating, int $id) use ($levels): bool {
            if (array_key_exists($id, $levels)) {
                return filled($levels[$id]);
            }

            return $rating->self_level !== null;
        });
    }

    /**
     * @param  Collection<int, LdnaRating>  $ratings  keyed by id
     * @param  array<int, string|null>  $remarks  keyed by rating id
     *
     * @throws ValidationException
     */
    public function remarks(Collection $ratings, array $remarks): void
    {
        foreach ($remarks as $ratingId => $remark) {
            if (! $ratings->has($ratingId)) {
                throw ValidationException::withMessages(['levels' => __('That competency is not part of this assessment.')]);
            }

            if (mb_strlen((string) $remark) > 2000) {
                throw ValidationException::withMessages(['remarks' => __('Keep each remark under 2,000 characters.')]);
            }
        }
    }
}
