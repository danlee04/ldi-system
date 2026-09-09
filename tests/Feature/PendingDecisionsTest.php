<?php

use App\Actions\Training\CountPendingDecisions;
use App\Enums\ApprovalLevel;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;

/**
 * A section with its head, and somebody under them.
 *
 * @return array{0: User, 1: Employee}
 */
function sectionUnderAHead(): array
{
    $section = Section::factory()->create();

    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    return [$headUser, Employee::factory()->for($section)->create()];
}

test('a head is counted only what is waiting on them', function () {
    [$headUser, $employee] = sectionUnderAHead();

    TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    // Another section entirely, and one already decided.
    TrainingRecord::factory()->create(['current_level' => ApprovalLevel::SectionHead]);
    TrainingRecord::factory()->for($employee)->approved()->create();

    expect(app(CountPendingDecisions::class)->handle($headUser))->toBe(1);
});

test('a head waiting on nothing is counted zero', function () {
    [$headUser] = sectionUnderAHead();

    expect(app(CountPendingDecisions::class)->handle($headUser))->toBe(0);
});

test('hr is counted everything pending, the unroutable included', function () {
    [, $employee] = sectionUnderAHead();

    TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);
    TrainingRecord::factory()->for($employee)->create(['current_level' => null]);
    TrainingRecord::factory()->for($employee)->approved()->create();

    expect(app(CountPendingDecisions::class)->handle(User::factory()->hr()->create()))->toBe(2);
});

test('an account with no employee record is counted zero', function () {
    TrainingRecord::factory()->create(['current_level' => ApprovalLevel::SectionHead]);

    expect(app(CountPendingDecisions::class)->handle(User::factory()->employee()->create()))->toBe(0);
});

test('the sidebar carries the count beside Approvals', function () {
    [$headUser, $employee] = sectionUnderAHead();

    TrainingRecord::factory()->for($employee)->count(3)->create([
        'current_level' => ApprovalLevel::SectionHead,
    ]);

    $this->actingAs($headUser);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    // The item's own class list mentions data-flux-navlist-badge, so the
    // element itself is what is matched — the marker followed by the count.
    expect($html)->toMatch('/data-flux-navlist-badge>\s*3\s*</');
});

test('the sidebar shows no badge when nothing is waiting', function () {
    [$headUser] = sectionUnderAHead();

    $this->actingAs($headUser);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('data-flux-navlist-badge>');
});

test('an employee who decides on nothing gets no approvals item at all', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('approvals'));
});
