<?php

namespace App\Actions\Reports;

use App\Enums\CompetencyType;
use App\Enums\ProficiencyLevel;
use App\Models\LdnaCycle;
use App\Models\LdnaRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where a cycle found people short, competency by competency.
 *
 * Read from the levels copied into the cycle, never from the framework as
 * it stands, so a year's gaps stay what they were. Only an assessment the
 * head has confirmed counts, and only people still working here: the
 * report is for planning, and a plan is for the people who will attend it.
 */
class LdnaGapReport
{
    /**
     * @return list<array{competency_id: int, competency: string, type: CompetencyType, rated: int, with_gap: int, percentage: float, average_gap: float, plans: int, people: list<array{employee: string, section: string, required: ProficiencyLevel, rating: ProficiencyLevel|null, gap: int}>}>
     */
    public function handle(LdnaCycle $cycle, ?int $divisionId = null, ?int $sectionId = null): array
    {
        $ratings = LdnaRating::query()
            ->whereNotNull('self_level')
            ->whereHas('assessment', fn (Builder $assessment) => $assessment
                ->where('ldna_cycle_id', $cycle->getKey())
                ->whereNotNull('confirmed_at')
                ->whereHas('employee', fn (Builder $employee) => $employee
                    ->where('is_active', true)
                    ->when($divisionId !== null, fn (Builder $query) => $query->where('division_id', $divisionId))
                    ->when($sectionId !== null, fn (Builder $query) => $query->where('section_id', $sectionId))))
            ->with(['competency', 'assessment.employee.section'])
            ->get();

        $plans = $this->plansByCompetency($cycle->year);

        return array_values($ratings
            ->groupBy('competency_id')
            ->map(fn (Collection $group, int $competencyId): array => $this->row($group, $competencyId, $plans[$competencyId] ?? 0))
            ->sortBy([['with_gap', 'desc'], ['competency', 'asc']])
            ->all());
    }

    /**
     * @param  Collection<int, LdnaRating>  $group
     * @return array{competency_id: int, competency: string, type: CompetencyType, rated: int, with_gap: int, percentage: float, average_gap: float, plans: int, people: list<array{employee: string, section: string, required: ProficiencyLevel, rating: ProficiencyLevel|null, gap: int}>}
     */
    private function row(Collection $group, int $competencyId, int $plans): array
    {
        /** @var LdnaRating $first */
        $first = $group->first();

        $short = $group->filter(fn (LdnaRating $rating): bool => $rating->gap() > 0);

        return [
            'competency_id' => $competencyId,
            'competency' => $first->competency->name,
            'type' => $first->competency->type,
            'rated' => $group->count(),
            'with_gap' => $short->count(),
            'percentage' => round($short->count() / $group->count() * 100, 1),
            // The average of those short only: counting everybody who is not
            // would make a deep gap in a few people look like nothing.
            'average_gap' => $short->isEmpty() ? 0.0 : round((float) $short->avg(fn (LdnaRating $rating): int => (int) $rating->gap()), 1),
            'plans' => $plans,
            'people' => array_values($short
                ->map(fn (LdnaRating $rating): array => [
                    'employee' => $rating->assessment->employee->listing_name,
                    'section' => $rating->assessment->employee->section->name ?? '—',
                    'required' => $rating->required_level,
                    'rating' => $rating->self_level,
                    'gap' => (int) $rating->gap(),
                ])
                ->sortByDesc('gap')
                ->all()),
        ];
    }

    /**
     * How many LDI plans starting in the year carry each competency.
     *
     * @return array<int, int>
     */
    private function plansByCompetency(int $year): array
    {
        return DB::table('competency_ldi_training')
            ->join('ldi_trainings', 'ldi_trainings.id', '=', 'competency_ldi_training.ldi_training_id')
            ->join('competencies', 'competencies.id', '=', 'competency_ldi_training.competency_id')
            ->where('competencies.is_active', true)
            ->whereYear('ldi_trainings.date_start', $year)
            ->groupBy('competency_ldi_training.competency_id')
            ->selectRaw('competency_ldi_training.competency_id as competency_id, count(*) as plans')
            ->pluck('plans', 'competency_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
