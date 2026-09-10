<?php

use App\Actions\Calendar\BuildMonthGrid;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Employee;
use App\Models\LdiTraining;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * An account that reads the calendar but keeps nothing on it.
 */
function reader(): User
{
    $user = User::factory()->employee()->create();

    Employee::factory()->create(['user_id' => $user->id]);

    return $user;
}

test('everybody signed in may read the calendar, and only some may add to it', function () {
    $hr = User::factory()->hr()->create();
    $admin = User::factory()->admin()->create();
    $keeper = User::factory()->employee()->keepsCalendar()->create();
    $reader = reader();

    expect($hr->can('viewAny', Activity::class))->toBeTrue()
        ->and($reader->can('viewAny', Activity::class))->toBeTrue()
        // Adding is the part that is kept.
        ->and($hr->can('create', Activity::class))->toBeTrue()
        ->and($admin->can('create', Activity::class))->toBeTrue()
        ->and($keeper->can('create', Activity::class))->toBeTrue()
        ->and($reader->can('create', Activity::class))->toBeFalse();
});

test('the employee who keeps the calendar can add an activity', function () {
    $this->actingAs(User::factory()->employee()->keepsCalendar()->create());

    Livewire::test('pages::calendar')
        ->call('create')
        ->set('title', 'Management Committee Meeting')
        ->set('type', ActivityType::Meeting->value)
        ->set('date_start', '2026-04-14')
        ->set('date_end', '2026-04-14')
        ->set('time_start', '09:00')
        ->set('time_end', '11:00')
        ->set('location', 'Conference Room')
        ->call('save')
        ->assertHasNoErrors();

    $activity = Activity::first();

    expect($activity->title)->toBe('Management Committee Meeting')
        ->and($activity->type)->toBe(ActivityType::Meeting)
        ->and($activity->timeRange())->toBe('9:00 AM - 11:00 AM');
});

test('a reader is offered no way to add one', function () {
    $this->actingAs(reader());

    Livewire::test('pages::calendar')
        ->assertOk()
        ->assertDontSee('Add activity');
});

test('a reader who calls create anyway is refused', function () {
    $this->actingAs(reader());

    Livewire::test('pages::calendar')
        ->call('create')
        ->assertForbidden();
});

test('an activity that has no time is a whole-day one', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::calendar')
        ->call('create')
        ->set('title', 'Independence Day')
        ->set('type', ActivityType::Holiday->value)
        ->set('date_start', '2026-06-12')
        ->set('date_end', '2026-06-12')
        ->call('save')
        ->assertHasNoErrors();

    expect(Activity::first()->timeRange())->toBeNull();
});

test('an end time before the start is refused', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::calendar')
        ->call('create')
        ->set('title', 'Backwards Meeting')
        ->set('type', ActivityType::Meeting->value)
        ->set('date_start', '2026-04-14')
        ->set('date_end', '2026-04-14')
        ->set('time_start', '14:00')
        ->set('time_end', '09:00')
        ->call('save')
        ->assertHasErrors('time_end');
});

test('the month shows its own activities and the trainings running in it', function () {
    $this->actingAs(User::factory()->hr()->create());

    Activity::factory()->on('2026-04-14')->create(['title' => 'Management Committee Meeting']);
    Activity::factory()->on('2026-05-14')->create(['title' => 'Next Month Meeting']);

    LdiTraining::factory()->create([
        'title' => 'Records Management Seminar',
        'date_start' => '2026-04-20',
        'date_end' => '2026-04-22',
    ]);

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->assertSee('Management Committee Meeting')
        ->assertSee('Records Management Seminar')
        ->assertDontSee('Next Month Meeting');
});

test('an activity that crosses into the month is shown by the month it reaches', function () {
    $this->actingAs(User::factory()->hr()->create());

    Activity::factory()->create([
        'title' => 'Year End Inventory',
        'date_start' => '2026-03-30',
        'date_end' => '2026-04-02',
    ]);

    // It starts in March, so a month asked for by start date would miss it.
    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->assertSee('Year End Inventory');
});

test('the arrows move the month and today comes back', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->call('previousMonth')
        ->assertSet('month', '2026-03')
        ->call('nextMonth')
        ->call('nextMonth')
        ->assertSet('month', '2026-05')
        ->call('today')
        ->assertSet('month', CarbonImmutable::today()->format('Y-m'));
});

test('adding from a day opens the form on that day', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->call('create', '2026-04-23')
        ->assertSet('date_start', '2026-04-23')
        ->assertSet('date_end', '2026-04-23');
});

test('the keeper can edit and remove what is on the calendar', function () {
    $this->actingAs(User::factory()->employee()->keepsCalendar()->create());

    $activity = Activity::factory()->on('2026-04-14')->create(['title' => 'Draft Title']);

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->call('edit', $activity->id)
        ->assertSet('title', 'Draft Title')
        ->set('title', 'Final Title')
        ->call('save')
        ->assertHasNoErrors();

    expect($activity->fresh()->title)->toBe('Final Title');

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->call('confirmDelete', $activity->id)
        ->call('delete');

    expect(Activity::find($activity->id))->toBeNull();
});

test('a reader cannot remove an activity', function () {
    $this->actingAs(reader());

    $activity = Activity::factory()->create();

    Livewire::test('pages::calendar')
        ->call('confirmDelete', $activity->id)
        ->assertForbidden();

    expect(Activity::find($activity->id))->not->toBeNull();
});

test('a month that starts on a sunday is not pushed along', function () {
    // March 2026 starts on a Sunday, which is the case a blank-cell count
    // built with range(1, 0) gets wrong by two days.
    $weeks = app(BuildMonthGrid::class)->handle(CarbonImmutable::parse('2026-03-01'));

    expect($weeks[0][0]?->toDateString())->toBe('2026-03-01')
        ->and($weeks[0][1]?->toDateString())->toBe('2026-03-02');
});

test('a month that starts midweek is padded to its weekday', function () {
    // April 2026 starts on a Wednesday.
    $weeks = app(BuildMonthGrid::class)->handle(CarbonImmutable::parse('2026-04-01'));

    expect($weeks[0][0])->toBeNull()
        ->and($weeks[0][1])->toBeNull()
        ->and($weeks[0][2])->toBeNull()
        ->and($weeks[0][3]?->toDateString())->toBe('2026-04-01')
        // Every week is whole, so the grid never has a ragged last row.
        ->and(collect($weeks)->every(fn (array $week): bool => count($week) === 7))->toBeTrue();
});

/**
 * Every bar the month draws, flattened out of its weeks.
 *
 * @return list<array<string, mixed>>
 */
function barsFor(string $month): array
{
    $weeks = Livewire::test('pages::calendar', ['month' => $month])->instance()->weeks;

    return collect($weeks)->pluck('bars')->flatten(1)->all();
}

test('a run of days is one bar as wide as it lasts', function () {
    $this->actingAs(User::factory()->hr()->create());

    // Monday to Wednesday, inside one week.
    Activity::factory()->create([
        'title' => 'Strategic Planning Workshop',
        'date_start' => '2026-04-13',
        'date_end' => '2026-04-15',
    ]);

    $bars = barsFor('2026-04');

    expect($bars)->toHaveCount(1)
        // Monday is the second column of a week that starts on Sunday.
        ->and($bars[0]['column'])->toBe(2)
        ->and($bars[0]['span'])->toBe(3)
        ->and($bars[0]['opensBefore'])->toBeFalse()
        ->and($bars[0]['runsOn'])->toBeFalse();
});

test('a run that crosses a week is cut at the week and says so', function () {
    $this->actingAs(User::factory()->hr()->create());

    // Friday to the following Tuesday.
    Activity::factory()->create([
        'title' => 'Regional Conference',
        'date_start' => '2026-04-17',
        'date_end' => '2026-04-21',
    ]);

    $bars = barsFor('2026-04');

    expect($bars)->toHaveCount(2)
        // Friday and Saturday close the first week.
        ->and($bars[0]['column'])->toBe(6)
        ->and($bars[0]['span'])->toBe(2)
        ->and($bars[0]['runsOn'])->toBeTrue()
        ->and($bars[0]['opensBefore'])->toBeFalse()
        // Sunday to Tuesday opens the next one.
        ->and($bars[1]['column'])->toBe(1)
        ->and($bars[1]['span'])->toBe(3)
        ->and($bars[1]['opensBefore'])->toBeTrue()
        ->and($bars[1]['runsOn'])->toBeFalse();
});

test('a run reaching in from the month before starts at the first of the month', function () {
    $this->actingAs(User::factory()->hr()->create());

    Activity::factory()->create([
        'title' => 'Year End Inventory',
        'date_start' => '2026-03-30',
        'date_end' => '2026-04-02',
    ]);

    $bars = barsFor('2026-04');

    // April 2026 opens on a Wednesday, so the bar starts in column four.
    expect($bars)->toHaveCount(1)
        ->and($bars[0]['column'])->toBe(4)
        ->and($bars[0]['span'])->toBe(2)
        ->and($bars[0]['opensBefore'])->toBeTrue();
});

test('two things on the same day sit on their own lines', function () {
    $this->actingAs(User::factory()->hr()->create());

    Activity::factory()->on('2026-04-14')->create(['title' => 'Morning Meeting']);
    Activity::factory()->on('2026-04-14')->create(['title' => 'Afternoon Meeting']);

    $lanes = collect(barsFor('2026-04'))->pluck('lane')->all();

    expect($lanes)->toBe([0, 1]);
});

test('a training and an activity on the same days do not sit on top of each other', function () {
    $this->actingAs(User::factory()->hr()->create());

    Activity::factory()->on('2026-04-14')->create(['title' => 'Management Committee Meeting']);

    LdiTraining::factory()->create([
        'title' => 'Records Management Seminar',
        'date_start' => '2026-04-14',
        'date_end' => '2026-04-14',
    ]);

    $bars = collect(barsFor('2026-04'));

    expect($bars)->toHaveCount(2)
        ->and($bars->pluck('lane')->all())->toBe([0, 1])
        ->and($bars->pluck('kind')->all())->toBe(['activity', 'plan']);
});

test('clicking a bar shows what it is', function () {
    $this->actingAs(User::factory()->hr()->create());

    $activity = Activity::factory()->on('2026-04-14')->timed()->create([
        'title' => 'Management Committee Meeting',
        'location' => 'Conference Room',
    ]);

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->call('show', 'activity', $activity->id)
        ->assertSee('Conference Room')
        ->assertSee('9:00 AM - 11:00 AM');
});

test('the month list under the calendar is gone', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->assertDontSee('This month')
        ->assertDontSee('Nothing is on the calendar this month');
});

test('each kind of entry wears its own colour', function () {
    $this->actingAs(User::factory()->hr()->create());

    Activity::factory()->on('2026-04-13')->create(['type' => ActivityType::Meeting]);
    Activity::factory()->on('2026-04-14')->create(['type' => ActivityType::Holiday]);
    Activity::factory()->on('2026-04-15')->create(['type' => ActivityType::Deadline]);
    Activity::factory()->on('2026-04-16')->create(['type' => ActivityType::Other]);

    LdiTraining::factory()->create(['date_start' => '2026-04-17', 'date_end' => '2026-04-17']);

    $classes = collect(barsFor('2026-04'))->pluck('classes');

    expect($classes)->toHaveCount(5)
        // No two kinds share a fill, which is the whole point of the colour.
        ->and($classes->unique())->toHaveCount(5)
        ->and($classes[0])->toContain('bg-violet-100')
        ->and($classes[1])->toContain('bg-green-100')
        ->and($classes[2])->toContain('bg-amber-100')
        ->and($classes[3])->toContain('bg-zinc-200')
        // A training wears the Center's own blue.
        ->and($classes[4])->toContain('bg-brand-primary')
        // And keeps a dashed edge, so blue and violet are told apart by
        // more than hue.
        ->and($classes[4])->toContain('border-dashed');
});

test('the calendar says what its colours mean', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::calendar', ['month' => '2026-04'])
        ->assertSee('LDI training')
        ->assertSee('Meeting')
        ->assertSee('Holiday')
        ->assertSee('Deadline');
});

test('the calendar reddens the weekend and fills today', function () {
    $this->actingAs(User::factory()->hr()->create());

    $html = Livewire::test('pages::calendar')->html();

    expect($html)
        // Saturday and Sunday, the way an office calendar marks them.
        ->toContain('text-red-600')
        // Today is the whole box, tinted rather than filled so a bar
        // sitting in it is still readable.
        ->toContain('bg-brand-primary/12');
});
