<?php

use App\Actions\Training\DecideOnTrainingRecord;
use App\Actions\Training\SubmitTrainingRecord;
use App\Enums\ApprovalDecision;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Notifications\TrainingAwaitsDecision;
use App\Notifications\TrainingDecided;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * An employee under a section head, with the head's account.
 *
 * @return array{0: Employee, 1: Employee, 2: User}
 */
function underASectionHead(): array
{
    $headUser = User::factory()->sectionHead()->create();
    $section = Section::factory()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create(['user_id' => User::factory()->employee()->create()->id]);

    return [$employee, $head, $headUser];
}

test('submitting tells the head who has to decide', function () {
    [$employee, , $headUser] = underASectionHead();

    app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    expect($headUser->unreadNotifications()->count())->toBe(1)
        ->and($headUser->unreadNotifications()->first()->data['kind'])->toBe('awaiting');
});

test('a record nobody can approve tells nobody', function () {
    $employee = Employee::factory()->create(['user_id' => User::factory()->employee()->create()->id]);

    app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    expect(DatabaseNotification::count())->toBe(0);
});

test('approving at the last level tells the employee', function () {
    [$employee, , $headUser] = underASectionHead();

    $record = app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    app(DecideOnTrainingRecord::class)->handle($record, $headUser, ApprovalDecision::Approved);

    $told = $employee->user->unreadNotifications()->first();

    expect($told)->not->toBeNull()
        ->and($told->type)->toBe(TrainingDecided::class)
        ->and($told->data['decision'])->toBe('approved');
});

test('a rejection carries its reason to the employee', function () {
    [$employee, , $headUser] = underASectionHead();

    $record = app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    app(DecideOnTrainingRecord::class)->handle(
        $record,
        $headUser,
        ApprovalDecision::Rejected,
        'Outside the year\'s plan',
    );

    $told = $employee->user->unreadNotifications()->first();

    expect($told->data['decision'])->toBe('rejected')
        ->and($told->data['reason'])->toBe('Outside the year\'s plan');
});

test('passing a record up tells the division head and nobody else', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $sectionHeadUser = User::factory()->sectionHead()->create();
    $sectionHead = Employee::factory()->for($section)->create(['user_id' => $sectionHeadUser->id]);
    $section->update(['section_head_employee_id' => $sectionHead->id]);

    $divisionHeadUser = User::factory()->divisionHead()->create();
    $divisionHead = Employee::factory()->for($section)->create(['user_id' => $divisionHeadUser->id]);
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    $employee = Employee::factory()->for($section)->create(['user_id' => User::factory()->employee()->create()->id]);

    $record = app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    app(DecideOnTrainingRecord::class)->handle($record, $sectionHeadUser, ApprovalDecision::Approved);

    expect($divisionHeadUser->unreadNotifications()->count())->toBe(1)
        ->and($divisionHeadUser->unreadNotifications()->first()->type)->toBe(TrainingAwaitsDecision::class)
        // Still pending, so the employee has nothing to hear yet.
        ->and($employee->user->unreadNotifications()->count())->toBe(0);
});

test('the bell counts what is unread', function () {
    [$employee, , $headUser] = underASectionHead();

    $this->actingAs($headUser);

    app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    Livewire::test('notifications')
        ->assertSee('1')
        ->assertSee('Awaiting your decision');
});

test('opening a notification marks it read and goes where it points', function () {
    [$employee, , $headUser] = underASectionHead();

    $this->actingAs($headUser);

    app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    $id = $headUser->unreadNotifications()->first()->id;

    Livewire::test('notifications')
        ->call('open', $id)
        ->assertRedirect(route('approvals'));

    expect($headUser->fresh()->unreadNotifications()->count())->toBe(0);
});

test('marking all read empties the bell', function () {
    [$employee, , $headUser] = underASectionHead();

    $this->actingAs($headUser);

    foreach (range(1, 3) as $ignored) {
        app(SubmitTrainingRecord::class)->handle(
            $employee,
            TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
            $employee->user,
        );
    }

    Livewire::test('notifications')
        ->call('markAllRead')
        ->assertSee('Nothing new.');

    expect($headUser->fresh()->unreadNotifications()->count())->toBe(0);
});

test('one person cannot open another person notification', function () {
    [$employee, , $headUser] = underASectionHead();

    app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    $id = $headUser->unreadNotifications()->first()->id;

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('notifications')->call('open', $id);

    expect($headUser->fresh()->unreadNotifications()->count())->toBe(1);
});

test('the count is layered over the bell rather than sitting inside it', function () {
    [$employee, , $headUser] = underASectionHead();

    $this->actingAs($headUser);

    app(SubmitTrainingRecord::class)->handle(
        $employee,
        TrainingRecord::factory()->raw(['employee_id' => null, 'submitted_by' => null]),
        $employee->user,
    );

    $html = Livewire::test('notifications')->html();
    $opens = strpos($html, '<button');
    $button = substr($html, $opens, strpos($html, '</button>') - $opens);

    // Flux wraps slot content in a span that stays in the button's flex
    // row, which knocked the bell off centre. The button holds the icon
    // and nothing else; the count is layered over it from outside.
    expect($button)->not->toContain('<span')
        ->and($button)->toContain('<svg')
        ->and($html)->toContain('rounded-full');
});

test('a very large count is shortened rather than stretching the bell', function () {
    [$employee, , $headUser] = underASectionHead();

    $this->actingAs($headUser);

    foreach (range(1, 100) as $ignored) {
        $headUser->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => TrainingAwaitsDecision::class,
            'data' => ['title' => 'Sample', 'kind' => 'awaiting', 'route' => 'approvals'],
        ]);
    }

    Livewire::test('notifications')->assertSee('99+');
});
