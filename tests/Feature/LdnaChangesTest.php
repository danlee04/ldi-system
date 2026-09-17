<?php

use App\Actions\Ldna\RefreshLdnaAssessment;
use App\Actions\Ldna\SyncLdnaCycle;
use App\Enums\ProficiencyLevel;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('sync adds somebody hired since it opened, and tells them', function () {
    Employee::factory()->create();
    $cycle = openLdna();

    $newcomer = Employee::factory()->create(['user_id' => User::factory()->employee()->create()->id]);

    expect(app(SyncLdnaCycle::class)->handle($cycle))->toBe(1)
        ->and(assessmentOf($newcomer, $cycle)->ratings)->toHaveCount(1)
        ->and($newcomer->user->unreadNotifications()->first()->data['kind'])->toBe('ldna_opened');
});

test('sync leaves the assessments already there alone', function () {
    $employee = Employee::factory()->create();
    $cycle = openLdna();
    $assessment = assessmentOf($employee, $cycle);
    $assessment->ratings()->update(['self_level' => ProficiencyLevel::Basic->value]);

    expect(app(SyncLdnaCycle::class)->handle($cycle))->toBe(0)
        ->and($assessment->ratings()->first()->self_level)->toBe(ProficiencyLevel::Basic);
});

test('refresh after a move keeps a rating still asked, at the level it now asks', function () {
    $technical = Competency::factory()->technical()->create();
    $before = Position::factory()->create();
    $after = Position::factory()->create();
    $before->competencies()->attach($technical->id, ['required_level' => ProficiencyLevel::Basic->value]);
    $after->competencies()->attach($technical->id, ['required_level' => ProficiencyLevel::Superior->value]);

    $employee = Employee::factory()->create(['position_id' => $before->id]);
    $cycle = openLdna();
    $assessment = assessmentOf($employee, $cycle);
    $assessment->ratings()->update(['self_level' => ProficiencyLevel::Advanced->value]);
    $assessment->update(['self_submitted_at' => now()]);

    $employee->update(['position_id' => $after->id]);
    app(RefreshLdnaAssessment::class)->handle($assessment);

    $rating = $assessment->ratings()->where('competency_id', $technical->id)->first();

    expect($rating->required_level)->toBe(ProficiencyLevel::Superior)
        ->and($rating->self_level)->toBe(ProficiencyLevel::Advanced)
        ->and($assessment->fresh()->position_id)->toBe($after->id)
        // Nothing new arrived, so what was submitted stays submitted.
        ->and($assessment->fresh()->isSelfSubmitted())->toBeTrue();
});

test('refresh drops what is no longer asked and reopens what was submitted when something new arrives', function () {
    $old = Competency::factory()->technical()->create();
    $new = Competency::factory()->technical()->create();
    $before = Position::factory()->create();
    $after = Position::factory()->create();
    $before->competencies()->attach($old->id, ['required_level' => ProficiencyLevel::Basic->value]);
    $after->competencies()->attach($new->id, ['required_level' => ProficiencyLevel::Basic->value]);

    $employee = Employee::factory()->create(['position_id' => $before->id]);
    $cycle = openLdna();
    $assessment = assessmentOf($employee, $cycle);
    $assessment->update(['self_submitted_at' => now(), 'confirmed_at' => now()]);

    $employee->update(['position_id' => $after->id]);
    app(RefreshLdnaAssessment::class)->handle($assessment);

    $competencyIds = $assessment->ratings()->pluck('competency_id')->all();

    expect($competencyIds)->toContain($new->id)
        ->and($competencyIds)->not->toContain($old->id)
        ->and($assessment->fresh()->isSelfSubmitted())->toBeFalse()
        ->and($assessment->fresh()->isConfirmed())->toBeFalse();
});

test('sync and refresh refuse a cycle that has closed', function () {
    $employee = Employee::factory()->create();
    $cycle = openLdna();
    $cycle->update(['opens_on' => today()->subMonths(2), 'closes_on' => today()->subDay()]);

    expect(fn () => app(SyncLdnaCycle::class)->handle($cycle))->toThrow(ValidationException::class)
        ->and(fn () => app(RefreshLdnaAssessment::class)->handle(assessmentOf($employee, $cycle)->fresh()))->toThrow(ValidationException::class);
});
