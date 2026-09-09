<?php

namespace App\Actions\Pds;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\PersonalDataSheet;

/**
 * How far through their PDS an employee has got, section by section.
 *
 * The form is long and the page that fills it is longer, so the point of
 * this is to answer one question at a glance: what is still missing.
 *
 * Section VI is deliberately absent. Learning and development comes from
 * the training records this system already approves, so it is not theirs
 * to fill and would only read as a chore they cannot finish.
 */
class PersonalDataSheetProgress
{
    /**
     * @return list<array{number: string, label: string, filled: bool}>
     */
    public function handle(Employee $employee): array
    {
        $sheet = $employee->personalDataSheet;

        return [
            [
                'number' => 'I',
                'label' => 'Personal information',
                'filled' => ($sheet?->completeness() ?? 0) > 0,
            ],
            [
                'number' => 'II',
                'label' => 'Family background',
                'filled' => $this->hasFamily($sheet) || $employee->children()->exists(),
            ],
            [
                'number' => 'III',
                'label' => 'Educational background',
                'filled' => $employee->educations()->exists(),
            ],
            [
                'number' => 'IV',
                'label' => 'Civil service eligibility',
                'filled' => $employee->eligibilities()->exists(),
            ],
            [
                'number' => 'V',
                'label' => 'Work experience',
                'filled' => $employee->workExperiences()->exists(),
            ],
            [
                'number' => 'VII',
                'label' => 'Voluntary work',
                'filled' => $employee->voluntaryWorks()->exists(),
            ],
            [
                'number' => 'VIII',
                'label' => 'Other information',
                'filled' => $employee->otherInformation()->exists(),
            ],
            [
                'number' => '34-41',
                'label' => 'Questions on page 4',
                'filled' => $this->hasAnswered($sheet),
            ],
        ];
    }

    /**
     * How much of it is done, as a percentage.
     *
     * @param  list<array{number: string, label: string, filled: bool}>  $sections
     */
    public function percentage(array $sections): int
    {
        if ($sections === []) {
            return 0;
        }

        $filled = count(array_filter($sections, fn (array $section): bool => $section['filled']));

        return (int) round($filled / count($sections) * 100);
    }

    /**
     * What Section VI will print, which is not theirs to fill.
     */
    public function learningAndDevelopment(Employee $employee): int
    {
        return $employee->trainingRecords()->where('status', TrainingStatus::Approved)->count();
    }

    private function hasFamily(?PersonalDataSheet $sheet): bool
    {
        if ($sheet === null) {
            return false;
        }

        return filled($sheet->spouse_last_name)
            || filled($sheet->father_last_name)
            || filled($sheet->mother_last_name);
    }

    /**
     * Page 4 counts as started once any of its questions has an answer —
     * and a no is an answer.
     */
    private function hasAnswered(?PersonalDataSheet $sheet): bool
    {
        if ($sheet === null) {
            return false;
        }

        foreach (self::DISCLOSURES as $question) {
            if ($sheet->{$question} !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @var list<string>
     */
    private const DISCLOSURES = [
        'related_within_third_degree',
        'related_within_fourth_degree',
        'found_guilty_administrative',
        'criminally_charged',
        'convicted_of_crime',
        'separated_from_service',
        'election_candidate',
        'resigned_for_election',
        'immigrant_or_resident',
        'indigenous_member',
        'person_with_disability',
        'solo_parent',
    ];
}
