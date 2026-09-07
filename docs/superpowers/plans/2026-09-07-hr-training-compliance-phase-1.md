# HR Training Compliance Tracker — Implementation Plan (Phase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sistemang panubaybay sa training compliance — employee registry, training records na may PDS L&D fields, manual assignment ng HR, monitoring ng walang training sa taon, at buod ng aktibidad kada buwan.

**Architecture:** Hybrid. Ang CRUD ay manipis na Livewire single-file component na diretsong gumagamit ng Eloquent. Ang dalawang operasyong may tunay na tuntunin (bulk assign, pagrecord ng natapos) ay nasa Action class. Ang reports ay hiwalay na klase na nagsasauli ng plain data — hindi Blade, hindi component method — para magamit ulit ng print view ngayon at ng LDNA at budget sa susunod na phase.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4 (single-file components), Flux 2 (free tier), Fortify, Pest 5, SQLite sa dev.

**Spec:** `docs/superpowers/specs/2026-09-07-hr-training-compliance-design.md`

## Global Constraints

- **Walang bagong composer o npm dependency** nang walang pahintulot ng user.
- **Livewire pages:** nasa `resources/views/pages/` bilang `⚡name.blade.php`, tinutukoy bilang `pages::folder.name`, naka-route via `Route::livewire('path', 'pages::folder.name')`. **HUWAG gamitin ang `php artisan make:livewire --sfc` para dito** — sumusulat ito sa `resources/views/components/pages/...` na maling namespace. Gawin nang manual ang file. Sundan ang `resources/views/pages/settings/⚡profile.blade.php` bilang huwaran.
- **Flux 2 free tier lang ang naka-install** (`livewire/flux`, walang `flux-pro`). Available: `input`, `select`, `textarea`, `checkbox`, `radio`, `switch`, `button`, `table`, `modal`, `badge`, `card`, `callout`, `pagination`, `navlist`, `sidebar`, `heading`, `text`, `field`, `separator`, `dropdown`, `menu`, `toast`, `tooltip`, `breadcrumbs`. **Walang `flux:date-picker`** — gamitin ang `<flux:input type="date">`.
- **PHP style:** laging may curly braces; explicit return type sa lahat ng method; type hint sa lahat ng parameter; constructor property promotion; TitleCase ang enum keys; PHPDoc array shapes kaysa inline comments.
- **Wika ng UI:** English labels na nakabalot sa `__()` para matranslate mamaya. (Bukas na tanong 11.1 sa spec; ito ang default.)
- **Petsa:** ang training ay bumibilang sa taon ng `to_date` nito.
- **Pint:** patakbuhin ang `vendor/bin/pint --dirty --format agent` bago ang bawat commit.
- **Tests:** Pest. Patakbuhin ang pinakamakitid na set: `php artisan test --compact <path>`.
- **Assumption na naka-flag sa spec (11.4):** ang `hours` ay per training, hindi per kalahok. Kung magbago ito, lilipat ang column sa `training_assignments`.

---

## File Structure

**Bago:**

```
app/Enums/Sex.php                        4 values ng PDS at UI labels
app/Enums/EmploymentStatus.php
app/Enums/LdType.php                     4 types ng CS Form 212
app/Enums/UserRole.php

app/Models/Division.php
app/Models/Section.php
app/Models/Position.php
app/Models/Employee.php                  scopes: active, visibleTo, withoutCompletedTrainingIn
app/Models/Training.php                  scopes: heldIn, heldDuring
app/Models/TrainingAssignment.php        scopes: pending, completed, overdue

app/Actions/Training/AssignTrainingToEmployees.php
app/Actions/Training/RecordTrainingCompletion.php

app/Reports/EmployeesWithoutTrainingReport.php
app/Reports/MonthlyTrainingActivityReport.php

app/Policies/EmployeePolicy.php
app/Policies/TrainingPolicy.php

database/migrations/  (5 bago + 1 pagbabago sa users)
database/factories/   (6 bago)
database/seeders/OrgStructureSeeder.php
app/Console/Commands/CreateHrAdmin.php

resources/views/pages/⚡dashboard.blade.php
resources/views/pages/employees/⚡index.blade.php
resources/views/pages/employees/⚡form.blade.php
resources/views/pages/employees/⚡show.blade.php
resources/views/pages/trainings/⚡index.blade.php
resources/views/pages/trainings/⚡form.blade.php
resources/views/pages/trainings/⚡show.blade.php
resources/views/pages/reports/⚡monthly-activity.blade.php
resources/views/pages/reports/⚡no-training.blade.php
resources/views/pages/setup/⚡divisions.blade.php
resources/views/pages/setup/⚡sections.blade.php
resources/views/pages/setup/⚡positions.blade.php
resources/views/layouts/print.blade.php
```

**Babaguhin:**

```
.env                                     APP_NAME
config/fortify.php:164                   tanggalin ang Features::registration()
app/Models/User.php                      role cast, employee relation, isHrAdmin()
database/factories/UserFactory.php       role default at states
routes/web.php                           lahat ng bagong route
resources/views/layouts/app/sidebar.blade.php   navigation
```

**Bakit ganito ang hati:** ang reports ay hiwalay sa components dahil tatlong bagay ang gagamit sa kanila (screen, print view, at ang LDNA sa Phase 2). Ang actions ay hiwalay dahil may tuntuning kailangang subukan nang walang UI. Ang lahat ng iba ay manipis na CRUD kung saan ang dagdag na layer ay boilerplate lang.

---

## Task 1: Housekeeping — version control, pagkakakilanlan, saradong registration

**Files:**
- Modify: `.env`
- Modify: `config/fortify.php:164`
- Create: `tests/Feature/Auth/RegistrationDisabledTest.php`

**Interfaces:**
- Consumes: wala
- Produces: git repository na may baseline commit; saradong public registration

- [ ] **Step 1: Simulan ang version control**

Wala pang git ang project na ito. Nasa lugar na ang `.gitignore` ng Laravel.

```bash
git init
git add -A
git commit -m "chore: baseline Laravel Livewire starter kit"
```

- [ ] **Step 2: Itakda ang pangalan ng app**

Sa `.env`, palitan ang `APP_NAME=Laravel`:

```
APP_NAME="HR Training"
```

**Napagdesisyunan na ang database:** MySQL 8.4, schema na `ldi_db`, na-migrate na noong 2026-09-07 (users, cache, jobs, passkeys, two-factor). Huwag ibalik sa SQLite. Ang test suite ay tumatakbo sa in-memory SQLite (`phpunit.xml`), kaya hindi nagagalaw ng `php artisan test` ang `ldi_db`. Ibig sabihin din nito: dapat portable sa SQLite at MySQL ang bawat migration — iwasan ang syntax na MySQL lang.

- [ ] **Step 3: Isulat ang failing test para sa saradong registration**

Gumawa ng `tests/Feature/Auth/RegistrationDisabledTest.php`:

```php
<?php

use Laravel\Fortify\Features;

test('public registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('the registration route does not exist', function () {
    expect(Route::has('register'))->toBeFalse();
});
```

Idagdag ang import sa itaas ng file:

```php
use Illuminate\Support\Facades\Route;
```

- [ ] **Step 4: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/Auth/RegistrationDisabledTest.php
```

Inaasahan: FAIL — buhay pa ang registration.

- [ ] **Step 5: Isara ang registration**

Sa `config/fortify.php`, tanggalin ang linyang `Features::registration(),` sa `features` array. Ang HR admin ang gumagawa ng account, hindi ang publiko.

- [ ] **Step 6: Patakbuhin ang buong auth suite**

```bash
php artisan test --compact tests/Feature/Auth
```

Inaasahan: PASS. Ang `RegistrationTest.php` ay awtomatikong lalaktawan — may `skipUnlessFortifyHas()` na ito sa `beforeEach`.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "chore: close public registration and set app name"
```

---

## Task 2: Enums at org reference tables

**Files:**
- Create: `app/Enums/Sex.php`, `app/Enums/EmploymentStatus.php`, `app/Enums/LdType.php`, `app/Enums/UserRole.php`
- Create: `app/Models/Division.php`, `app/Models/Section.php`, `app/Models/Position.php`
- Create: 3 migration, 3 factory
- Test: `tests/Feature/OrgStructureTest.php`

**Interfaces:**
- Consumes: wala
- Produces: `Division` (name, code; `sections()`), `Section` (division_id, name, code; `division()`, `employees()`), `Position` (title, salary_grade); enums na `Sex`, `EmploymentStatus`, `LdType`, `UserRole`, bawat isa may `label(): string`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/OrgStructureTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Section;
use Illuminate\Database\QueryException;

test('a section belongs to a division', function () {
    $division = Division::factory()->create(['name' => 'Finance and Administrative Division', 'code' => 'FAD']);
    $section = Section::factory()->for($division)->create(['name' => 'Human Resource Section', 'code' => 'HRS']);

    expect($section->division->code)->toBe('FAD')
        ->and($division->sections)->toHaveCount(1);
});

test('section codes are unique', function () {
    Section::factory()->create(['code' => 'HRS']);

    expect(fn () => Section::factory()->create(['code' => 'HRS']))
        ->toThrow(QueryException::class);
});

test('deleting a division deletes its sections', function () {
    $division = Division::factory()->create();
    Section::factory()->for($division)->create();

    $division->delete();

    expect(Section::count())->toBe(0);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/OrgStructureTest.php
```

Inaasahan: FAIL — `Class "App\Models\Division" not found`.

- [ ] **Step 3: Gawin ang mga enum**

```bash
php artisan make:enum Sex --string --no-interaction
php artisan make:enum EmploymentStatus --string --no-interaction
php artisan make:enum LdType --string --no-interaction
php artisan make:enum UserRole --string --no-interaction
```

`app/Enums/Sex.php`:

```php
<?php

namespace App\Enums;

enum Sex: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
```

`app/Enums/EmploymentStatus.php`:

```php
<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Casual = 'casual';
    case Contractual = 'contractual';
    case CoTerminous = 'co_terminous';
    case JobOrder = 'job_order';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
```

`app/Enums/LdType.php` — ito ang apat na uri sa CS Form 212, huwag dagdagan:

```php
<?php

namespace App\Enums;

enum LdType: string
{
    case Managerial = 'managerial';
    case Supervisory = 'supervisory';
    case Technical = 'technical';
    case Foundation = 'foundation';

    public function label(): string
    {
        return $this->name;
    }
}
```

`app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case HrAdmin = 'hr_admin';
    case DivisionHead = 'division_head';
    case SectionHead = 'section_head';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
```

- [ ] **Step 4: Gawin ang mga migration**

```bash
php artisan make:migration create_divisions_table --no-interaction
php artisan make:migration create_sections_table --no-interaction
php artisan make:migration create_positions_table --no-interaction
```

Laman ng `up()` ng bawat isa:

```php
Schema::create('divisions', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->unique();
    $table->timestamps();
});
```

```php
Schema::create('sections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('division_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('code')->unique();
    $table->timestamps();
});
```

```php
Schema::create('positions', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->unsignedTinyInteger('salary_grade')->nullable();
    $table->timestamps();
});
```

Ang `foreignId()->constrained()` ay gumagawa na ng index — hindi na kailangan ng hiwalay na `index()`.

- [ ] **Step 5: Gawin ang mga model at factory**

```bash
php artisan make:model Division --factory --no-interaction
php artisan make:model Section --factory --no-interaction
php artisan make:model Position --factory --no-interaction
```

`app/Models/Division.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    /** @use HasFactory<\Database\Factories\DivisionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code'];

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
```

`app/Models/Section.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    /** @use HasFactory<\Database\Factories\SectionFactory> */
    use HasFactory;

    protected $fillable = ['division_id', 'name', 'code'];

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
```

`app/Models/Position.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    /** @use HasFactory<\Database\Factories\PositionFactory> */
    use HasFactory;

    protected $fillable = ['title', 'salary_grade'];

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
```

Ang `employees()` sa `Section` at `Position` ay tumutukoy sa `Employee` na gagawin sa Task 3. Ayos lang — hindi ito ini-resolve hangga't hindi tinatawag, at hindi hinahawakan ng test ng task na ito.

Mga factory:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Division>
 */
class DivisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Division',
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'name' => fake()->unique()->words(2, true).' Section',
            'code' => strtoupper(fake()->unique()->lexify('????')),
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'salary_grade' => fake()->numberBetween(1, 33),
        ];
    }
}
```

- [ ] **Step 6: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/OrgStructureTest.php
```

Inaasahan: PASS, tatlong test.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add org structure enums, tables and models"
```

---

## Task 3: Employees

**Files:**
- Create: migration `create_employees_table`, `app/Models/Employee.php`, `database/factories/EmployeeFactory.php`
- Test: `tests/Feature/EmployeeTest.php`

**Interfaces:**
- Consumes: `Division`, `Section`, `Position` mula sa Task 2
- Produces: `Employee` na may `section()`, `position()`, `user()`, `trainingAssignments()`, accessor na `full_name`, at `scopeActive()`. Ang division ay naaabot sa pamamagitan ng `$employee->section->division` — walang hiwalay na relation, para iisa lang ang daan. Ang `EmployeeFactory` ay may state na `separated()`.

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/EmployeeTest.php`:

```php
<?php

use App\Models\Employee;
use App\Models\Section;

test('an employee reaches its division through its section', function () {
    $section = Section::factory()->create();
    $employee = Employee::factory()->for($section)->create();

    expect($employee->section->division->id)->toBe($section->division_id);
});

test('full name joins the parts and omits blanks', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Cruz',
        'name_extension' => null,
    ]);

    expect($employee->full_name)->toBe('Maria Santos Cruz');
});

test('full name includes the name extension when present', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Jose',
        'middle_name' => null,
        'last_name' => 'Rizal',
        'name_extension' => 'Jr.',
    ]);

    expect($employee->full_name)->toBe('Jose Rizal Jr.');
});

test('the active scope excludes separated employees', function () {
    Employee::factory()->count(2)->create();
    Employee::factory()->separated()->create();

    expect(Employee::count())->toBe(3)
        ->and(Employee::active()->count())->toBe(2);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/EmployeeTest.php
```

Inaasahan: FAIL — `Class "App\Models\Employee" not found`.

- [ ] **Step 3: Gawin ang migration**

```bash
php artisan make:migration create_employees_table --no-interaction
```

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
    $table->string('employee_no')->unique();
    $table->string('first_name');
    $table->string('middle_name')->nullable();
    $table->string('last_name');
    $table->string('name_extension')->nullable();
    $table->string('sex');
    $table->date('date_of_birth');
    $table->foreignId('section_id')->constrained();
    $table->foreignId('position_id')->constrained();
    $table->string('employment_status');
    $table->date('date_hired');
    $table->date('separated_at')->nullable()->index();
    $table->timestamps();
});
```

Hiwalay ang pangalan sa apat na bahagi dahil ganoon hinihingi ng PDS. Walang `division_id` — galing ito sa section, kaya walang pwedeng mag-inconsistent.

- [ ] **Step 4: Gawin ang model at factory**

```bash
php artisan make:model Employee --factory --no-interaction
```

`app/Models/Employee.php`:

```php
<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\Sex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_no',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'sex',
        'date_of_birth',
        'section_id',
        'position_id',
        'employment_status',
        'date_hired',
        'separated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'employment_status' => EmploymentStatus::class,
            'date_of_birth' => 'date',
            'date_hired' => 'date',
            'separated_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TrainingAssignment, $this>
     */
    public function trainingAssignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->name_extension,
        ])->filter()->join(' '));
    }

    /**
     * Employees who have not been separated.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('separated_at');
    }
}
```

`database/factories/EmployeeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\Sex;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'employee_no' => fake()->unique()->numerify('EMP-#####'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'last_name' => fake()->lastName(),
            'name_extension' => null,
            'sex' => fake()->randomElement(Sex::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-22 years'),
            'section_id' => Section::factory(),
            'position_id' => Position::factory(),
            'employment_status' => EmploymentStatus::Permanent,
            'date_hired' => fake()->dateTimeBetween('-20 years', '-1 year'),
            'separated_at' => null,
        ];
    }

    /**
     * An employee who has resigned or retired.
     */
    public function separated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'separated_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ]);
    }
}
```

- [ ] **Step 5: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/EmployeeTest.php
```

Inaasahan: PASS, apat na test.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add employee records"
```

---

## Task 4: Roles, saklaw, at policies

**Files:**
- Create: migration `add_role_to_users_table`, `app/Policies/EmployeePolicy.php`, `app/Policies/TrainingPolicy.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Models/Employee.php` (dagdag na scope)
- Test: `tests/Feature/AccessScopeTest.php`

**Interfaces:**
- Consumes: `Employee`, `Section`, `Division`, `UserRole`
- Produces: `User::$role` (`UserRole`), `User::employee()`, `User::isHrAdmin(): bool`; `Employee::scopeVisibleTo(Builder $query, User $user): void`; `UserFactory` states `hrAdmin()`, `divisionHead()`, `sectionHead()`; policies na nagbabawal sa write maliban sa HR admin

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/AccessScopeTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('an hr admin sees every employee', function () {
    Employee::factory()->count(3)->create();
    $hr = User::factory()->hrAdmin()->create();

    expect(Employee::visibleTo($hr)->count())->toBe(3);
});

test('a division head sees only employees in their own division', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();
    $otherSection = Section::factory()->create();

    Employee::factory()->count(2)->for($ownSection)->create();
    Employee::factory()->for($otherSection)->create();

    $head = User::factory()->divisionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $head->id]);

    expect(Employee::visibleTo($head)->count())->toBe(3);
});

test('a section head sees only employees in their own section', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();
    $siblingSection = Section::factory()->for($division)->create();

    Employee::factory()->for($ownSection)->create();
    Employee::factory()->for($siblingSection)->create();

    $head = User::factory()->sectionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $head->id]);

    expect(Employee::visibleTo($head)->count())->toBe(2);
});

test('a head without an employee record sees nothing', function () {
    Employee::factory()->count(2)->create();
    $orphan = User::factory()->sectionHead()->create();

    expect(Employee::visibleTo($orphan)->count())->toBe(0);
});

test('only an hr admin may create or update employees', function () {
    $hr = User::factory()->hrAdmin()->create();
    $head = User::factory()->divisionHead()->create();
    $employee = Employee::factory()->create();

    expect($hr->can('create', Employee::class))->toBeTrue()
        ->and($hr->can('update', $employee))->toBeTrue()
        ->and($head->can('create', Employee::class))->toBeFalse()
        ->and($head->can('update', $employee))->toBeFalse();
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/AccessScopeTest.php
```

Inaasahan: FAIL — walang `hrAdmin()` state ang `UserFactory`.

- [ ] **Step 3: Idagdag ang role column**

```bash
php artisan make:migration add_role_to_users_table --no-interaction
```

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
```

Walang default — sadya. Ang bawat account ay may tahasang role, at ang HR admin lang ang gumagawa ng account.

- [ ] **Step 4: Baguhin ang User model**

Sa `app/Models/User.php`, idagdag ang `'role'` sa `$fillable`, ang cast, at ang dalawang method. Idagdag ang mga import na `App\Enums\UserRole` at `Illuminate\Database\Eloquent\Relations\HasOne`.

Sa loob ng `casts()` method, idagdag:

```php
'role' => UserRole::class,
```

Idagdag ang mga method na ito sa klase:

```php
/**
 * @return HasOne<Employee, $this>
 */
public function employee(): HasOne
{
    return $this->hasOne(Employee::class);
}

public function isHrAdmin(): bool
{
    return $this->role === UserRole::HrAdmin;
}
```

- [ ] **Step 5: Baguhin ang UserFactory**

Sa `database/factories/UserFactory.php`, idagdag ang `'role' => UserRole::HrAdmin,` sa `definition()` at ang tatlong state. Import ang `App\Enums\UserRole`.

```php
public function hrAdmin(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::HrAdmin]);
}

public function divisionHead(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::DivisionHead]);
}

public function sectionHead(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::SectionHead]);
}
```

Ang HR admin ang default dahil doon nakatuon ang halos lahat ng test.

- [ ] **Step 6: Idagdag ang visibleTo scope**

Sa `app/Models/Employee.php`, idagdag ang scope na ito at ang import ng `App\Enums\UserRole`:

```php
/**
 * Employees the given user is allowed to see.
 *
 * HR admins see everyone. A division or section head sees their own
 * division or section, resolved through their own employee record.
 */
public function scopeVisibleTo(Builder $query, User $user): void
{
    if ($user->role === UserRole::HrAdmin) {
        return;
    }

    $employee = $user->employee()->with('section')->first();

    if ($employee === null) {
        $query->whereRaw('1 = 0');

        return;
    }

    if ($user->role === UserRole::DivisionHead) {
        $query->whereHas('section', fn (Builder $section) => $section->where('division_id', $employee->section->division_id));

        return;
    }

    $query->where('section_id', $employee->section_id);
}
```

- [ ] **Step 7: Gawin ang mga policy**

```bash
php artisan make:policy EmployeePolicy --model=Employee --no-interaction
```

Awtomatikong natutuklasan ng Laravel ang policy sa `app/Policies` — walang kailangang irehistro. Ang `TrainingPolicy` ay gagawin sa Task 5 kasama ng model nito.

**Paglihis sa spec:** nagbanggit ang spec ng `TrainingAssignmentPolicy`. Hindi ito ginawa — ang pag-assign at pagrecord ng natapos ay parehong pagbabago sa isang training, kaya `TrainingPolicy::assign()` ang humahawak sa dalawa. Isang klase na mas kaunti, at nasa iisang lugar ang tuntunin.

`app/Policies/EmployeePolicy.php` — palitan ang buong laman:

```php
<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return Employee::query()->visibleTo($user)->whereKey($employee->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->isHrAdmin();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isHrAdmin();
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->isHrAdmin();
    }
}
```

- [ ] **Step 8: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/AccessScopeTest.php
```

Inaasahan: PASS, limang test.

- [ ] **Step 9: Patakbuhin ang naunang mga test para sa regression**

```bash
php artisan test --compact
```

Inaasahan: PASS lahat. Kung may bumagsak dahil sa kulang na `role`, ang factory ang kulang ng default.

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add user roles, employee visibility scoping and policies"
```

---
## Task 5: Trainings at training assignments

**Files:**
- Create: migration `create_trainings_table`, migration `create_training_assignments_table`
- Create: `app/Models/Training.php`, `app/Models/TrainingAssignment.php`, `app/Policies/TrainingPolicy.php`
- Create: `database/factories/TrainingFactory.php`, `database/factories/TrainingAssignmentFactory.php`
- Test: `tests/Feature/TrainingTest.php`

**Interfaces:**
- Consumes: `Employee`, `User`, `LdType`
- Produces: `Training` (title, from_date, to_date, hours, ld_type, conducted_by; `assignments()`; `scopeHeldIn(Builder, int $year)`, `scopeHeldDuring(Builder, int $year, int $month)`); `TrainingAssignment` (`training()`, `employee()`, `assignedBy()`; `scopePending()`, `scopeCompleted()`, `scopeOverdue()`); `TrainingAssignmentFactory` states `completed()`, `overdue()`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/TrainingTest.php`:

```php
<?php

use App\Enums\LdType;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingAssignment;
use Illuminate\Database\QueryException;

test('a training records the five PDS learning and development fields', function () {
    $training = Training::factory()->create([
        'title' => 'Records Management Seminar',
        'from_date' => '2026-03-02',
        'to_date' => '2026-03-04',
        'hours' => 24,
        'ld_type' => LdType::Technical,
        'conducted_by' => 'Civil Service Commission',
    ]);

    expect($training->ld_type)->toBe(LdType::Technical)
        ->and($training->hours)->toBe(24)
        ->and($training->to_date->toDateString())->toBe('2026-03-04');
});

test('a training belongs to the year its to_date falls in', function () {
    Training::factory()->create(['from_date' => '2025-12-30', 'to_date' => '2025-12-31']);
    Training::factory()->create(['from_date' => '2025-12-31', 'to_date' => '2026-01-01']);

    expect(Training::heldIn(2025)->count())->toBe(1)
        ->and(Training::heldIn(2026)->count())->toBe(1);
});

test('heldDuring narrows to a single month', function () {
    Training::factory()->create(['from_date' => '2026-03-01', 'to_date' => '2026-03-03']);
    Training::factory()->create(['from_date' => '2026-04-01', 'to_date' => '2026-04-03']);

    expect(Training::heldDuring(2026, 3)->count())->toBe(1);
});

test('an employee cannot be assigned the same training twice', function () {
    $training = Training::factory()->create();
    $employee = Employee::factory()->create();

    TrainingAssignment::factory()->for($training)->for($employee)->create();

    expect(fn () => TrainingAssignment::factory()->for($training)->for($employee)->create())
        ->toThrow(QueryException::class);
});

test('assignment scopes separate pending, completed and overdue', function () {
    TrainingAssignment::factory()->create(['due_date' => now()->addWeek()]);
    TrainingAssignment::factory()->completed()->create();
    TrainingAssignment::factory()->overdue()->create();

    expect(TrainingAssignment::pending()->count())->toBe(2)
        ->and(TrainingAssignment::completed()->count())->toBe(1)
        ->and(TrainingAssignment::overdue()->count())->toBe(1);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/TrainingTest.php
```

Inaasahan: FAIL — `Class "App\Models\Training" not found`.

- [ ] **Step 3: Gawin ang mga migration**

```bash
php artisan make:migration create_trainings_table --no-interaction
php artisan make:migration create_training_assignments_table --no-interaction
```

```php
Schema::create('trainings', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->date('from_date');
    $table->date('to_date')->index();
    $table->unsignedSmallInteger('hours');
    $table->string('ld_type');
    $table->string('conducted_by');
    $table->timestamps();
});
```

```php
Schema::create('training_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('training_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('assigned_by')->constrained('users');
    $table->date('due_date')->nullable();
    $table->date('completed_at')->nullable()->index();
    $table->timestamps();

    $table->unique(['training_id', 'employee_id']);
});
```

Ang unique sa `(training_id, employee_id)` ang pumipigil sa doble-doble kapag inulit ang bulk assign.

- [ ] **Step 4: Gawin ang mga model**

```bash
php artisan make:model Training --factory --no-interaction
php artisan make:model TrainingAssignment --factory --no-interaction
```

`app/Models/Training.php`:

```php
<?php

namespace App\Models;

use App\Enums\LdType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'from_date',
        'to_date',
        'hours',
        'ld_type',
        'conducted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'hours' => 'integer',
            'ld_type' => LdType::class,
        ];
    }

    /**
     * @return HasMany<TrainingAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    /**
     * Trainings that ended in the given year.
     */
    public function scopeHeldIn(Builder $query, int $year): void
    {
        $query->whereYear('to_date', $year);
    }

    /**
     * Trainings that ended in the given month.
     */
    public function scopeHeldDuring(Builder $query, int $year, int $month): void
    {
        $query->whereYear('to_date', $year)->whereMonth('to_date', $month);
    }
}
```

`app/Models/TrainingAssignment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'training_id',
        'employee_id',
        'assigned_by',
        'due_date',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Training, $this>
     */
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }
}
```

- [ ] **Step 5: Gawin ang mga factory**

`database/factories/TrainingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\LdType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<\App\Models\Training>
 */
class TrainingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = Carbon::instance(fake()->dateTimeBetween('-2 years', 'now'));

        return [
            'title' => fake()->sentence(4),
            'from_date' => $from,
            'to_date' => $from->copy()->addDays(fake()->numberBetween(0, 4)),
            'hours' => fake()->numberBetween(8, 40),
            'ld_type' => fake()->randomElement(LdType::cases()),
            'conducted_by' => fake()->company(),
        ];
    }
}
```

`database/factories/TrainingAssignmentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TrainingAssignment>
 */
class TrainingAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_id' => Training::factory(),
            'employee_id' => Employee::factory(),
            'assigned_by' => User::factory(),
            'due_date' => null,
            'completed_at' => null,
        ];
    }

    /**
     * An assignment the employee has finished.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => now()->subDays(7),
        ]);
    }

    /**
     * An unfinished assignment whose due date has passed.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'due_date' => now()->subDay(),
            'completed_at' => null,
        ]);
    }
}
```

- [ ] **Step 6: Gawin ang TrainingPolicy**

```bash
php artisan make:policy TrainingPolicy --model=Training --no-interaction
```

Palitan ang buong laman ng `app/Policies/TrainingPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Training;
use App\Models\User;

class TrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Training $training): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isHrAdmin();
    }

    public function update(User $user, Training $training): bool
    {
        return $user->isHrAdmin();
    }

    public function delete(User $user, Training $training): bool
    {
        return $user->isHrAdmin();
    }

    /**
     * Assigning a training and recording completion are both HR-only.
     */
    public function assign(User $user, Training $training): bool
    {
        return $user->isHrAdmin();
    }
}
```

- [ ] **Step 7: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/TrainingTest.php
```

Inaasahan: PASS, limang test.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add trainings and training assignments"
```

---

## Task 6: Actions — bulk assign at pagrecord ng natapos

**Files:**
- Create: `app/Actions/Training/AssignTrainingToEmployees.php`, `app/Actions/Training/RecordTrainingCompletion.php`
- Test: `tests/Feature/AssignTrainingToEmployeesTest.php`, `tests/Feature/RecordTrainingCompletionTest.php`

**Interfaces:**
- Consumes: `Training`, `Employee`, `TrainingAssignment`, `User`
- Produces:
  - `AssignTrainingToEmployees::handle(Training $training, array $employeeIds, User $assignedBy, ?Carbon $dueDate = null): int` — nagsasauli ng bilang ng aktwal na nagawang assignment
  - `RecordTrainingCompletion::handle(Training $training, Employee $employee, Carbon $completedOn, User $recordedBy): TrainingAssignment`

- [ ] **Step 1: Isulat ang failing test para sa bulk assign**

Gumawa ng `tests/Feature/AssignTrainingToEmployeesTest.php`:

```php
<?php

use App\Actions\Training\AssignTrainingToEmployees;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;

test('it assigns a training to many employees at once', function () {
    $training = Training::factory()->create();
    $employees = Employee::factory()->count(3)->create();
    $hr = User::factory()->hrAdmin()->create();

    $assigned = app(AssignTrainingToEmployees::class)
        ->handle($training, $employees->pluck('id')->all(), $hr);

    expect($assigned)->toBe(3)
        ->and(TrainingAssignment::count())->toBe(3);
});

test('running it twice creates no duplicates', function () {
    $training = Training::factory()->create();
    $employees = Employee::factory()->count(2)->create();
    $hr = User::factory()->hrAdmin()->create();
    $action = app(AssignTrainingToEmployees::class);

    $action->handle($training, $employees->pluck('id')->all(), $hr);
    $secondRun = $action->handle($training, $employees->pluck('id')->all(), $hr);

    expect($secondRun)->toBe(0)
        ->and(TrainingAssignment::count())->toBe(2);
});

test('separated employees are skipped', function () {
    $training = Training::factory()->create();
    $active = Employee::factory()->create();
    $separated = Employee::factory()->separated()->create();
    $hr = User::factory()->hrAdmin()->create();

    $assigned = app(AssignTrainingToEmployees::class)
        ->handle($training, [$active->id, $separated->id], $hr);

    expect($assigned)->toBe(1)
        ->and(TrainingAssignment::where('employee_id', $separated->id)->exists())->toBeFalse();
});

test('it stores the due date and who assigned it', function () {
    $training = Training::factory()->create();
    $employee = Employee::factory()->create();
    $hr = User::factory()->hrAdmin()->create();

    app(AssignTrainingToEmployees::class)
        ->handle($training, [$employee->id], $hr, now()->addMonth());

    $assignment = TrainingAssignment::first();

    expect($assignment->assigned_by)->toBe($hr->id)
        ->and($assignment->due_date->toDateString())->toBe(now()->addMonth()->toDateString());
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/AssignTrainingToEmployeesTest.php
```

Inaasahan: FAIL — `Target class [App\Actions\Training\AssignTrainingToEmployees] does not exist`.

- [ ] **Step 3: Isulat ang AssignTrainingToEmployees**

```bash
php artisan make:class Actions/Training/AssignTrainingToEmployees --no-interaction
```

```php
<?php

namespace App\Actions\Training;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssignTrainingToEmployees
{
    /**
     * Assign one training to many employees at once.
     *
     * Separated employees and employees who already have the training are
     * skipped, so the action is safe to repeat.
     *
     * @param  array<int, int>  $employeeIds
     * @return int  the number of assignments actually created
     */
    public function handle(Training $training, array $employeeIds, User $assignedBy, ?Carbon $dueDate = null): int
    {
        $assignable = Employee::query()
            ->active()
            ->whereIn('id', $employeeIds)
            ->whereDoesntHave(
                'trainingAssignments',
                fn (Builder $assignment) => $assignment->where('training_id', $training->id),
            )
            ->pluck('id');

        if ($assignable->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($assignable, $training, $assignedBy, $dueDate): int {
            $now = now();

            TrainingAssignment::insert($assignable->map(fn (int $employeeId): array => [
                'training_id' => $training->id,
                'employee_id' => $employeeId,
                'assigned_by' => $assignedBy->id,
                'due_date' => $dueDate?->toDateString(),
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return $assignable->count();
        });
    }
}
```

- [ ] **Step 4: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/AssignTrainingToEmployeesTest.php
```

Inaasahan: PASS, apat na test.

- [ ] **Step 5: Isulat ang failing test para sa pagrecord ng natapos**

Gumawa ng `tests/Feature/RecordTrainingCompletionTest.php`:

```php
<?php

use App\Actions\Training\RecordTrainingCompletion;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;

test('it marks an existing assignment as completed', function () {
    $training = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04']);
    $employee = Employee::factory()->create();
    $hr = User::factory()->hrAdmin()->create();
    TrainingAssignment::factory()->for($training)->for($employee)->create();

    $assignment = app(RecordTrainingCompletion::class)
        ->handle($training, $employee, now()->parse('2026-03-04'), $hr);

    expect($assignment->completed_at->toDateString())->toBe('2026-03-04')
        ->and(TrainingAssignment::count())->toBe(1);
});

test('it records attendance for an employee who was never assigned', function () {
    $training = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04']);
    $employee = Employee::factory()->create();
    $hr = User::factory()->hrAdmin()->create();

    app(RecordTrainingCompletion::class)
        ->handle($training, $employee, now()->parse('2026-03-04'), $hr);

    expect(TrainingAssignment::count())->toBe(1)
        ->and(TrainingAssignment::first()->assigned_by)->toBe($hr->id);
});

test('it rejects a completion date in the future', function () {
    $training = Training::factory()->create(['from_date' => now()->subWeek(), 'to_date' => now()->subDay()]);
    $employee = Employee::factory()->create();
    $hr = User::factory()->hrAdmin()->create();

    expect(fn () => app(RecordTrainingCompletion::class)
        ->handle($training, $employee, now()->addDay(), $hr))
        ->toThrow(InvalidArgumentException::class);
});

test('it rejects a completion date before the training started', function () {
    $training = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04']);
    $employee = Employee::factory()->create();
    $hr = User::factory()->hrAdmin()->create();

    expect(fn () => app(RecordTrainingCompletion::class)
        ->handle($training, $employee, now()->parse('2026-03-01'), $hr))
        ->toThrow(InvalidArgumentException::class);
});
```

Ang test na "in the future" ay gumagamit ng relatibong petsa dahil ang hinaharap ay gumagalaw; ang iba ay nakapirming petsa para malinaw ang boundary.

- [ ] **Step 6: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/RecordTrainingCompletionTest.php
```

Inaasahan: FAIL — wala pa ang klase.

- [ ] **Step 7: Isulat ang RecordTrainingCompletion**

```bash
php artisan make:class Actions/Training/RecordTrainingCompletion --no-interaction
```

```php
<?php

namespace App\Actions\Training;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class RecordTrainingCompletion
{
    /**
     * Mark a training as completed by an employee.
     *
     * When the employee was never assigned, an already-completed assignment
     * is created instead. This is how externally attended trainings enter
     * the system.
     *
     * @throws InvalidArgumentException when the completion date is impossible
     */
    public function handle(Training $training, Employee $employee, Carbon $completedOn, User $recordedBy): TrainingAssignment
    {
        if ($completedOn->isFuture()) {
            throw new InvalidArgumentException('A training cannot be completed in the future.');
        }

        if ($completedOn->lt($training->from_date)) {
            throw new InvalidArgumentException('A training cannot be completed before it started.');
        }

        $assignment = TrainingAssignment::query()->firstOrNew([
            'training_id' => $training->id,
            'employee_id' => $employee->id,
        ]);

        if ($assignment->assigned_by === null) {
            $assignment->assigned_by = $recordedBy->id;
        }

        $assignment->completed_at = $completedOn;
        $assignment->save();

        return $assignment;
    }
}
```

- [ ] **Step 8: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/RecordTrainingCompletionTest.php
```

Inaasahan: PASS, apat na test.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add training assignment and completion actions"
```

---

## Task 7: Report — mga empleyadong walang training

**Files:**
- Create: `app/Reports/EmployeesWithoutTrainingReport.php`
- Modify: `app/Models/Employee.php` (dagdag na scope)
- Test: `tests/Feature/EmployeesWithoutTrainingReportTest.php`

**Interfaces:**
- Consumes: `Employee`, `Training`, `TrainingAssignment`, `User`
- Produces:
  - `Employee::scopeWithoutCompletedTrainingIn(Builder $query, int $year): void`
  - `new EmployeesWithoutTrainingReport(int $year, ?User $scopedTo = null)` na may `employees(): Collection<int, Employee>`, `countByDivision(): Collection<string, int>`, `total(): int`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/EmployeesWithoutTrainingReportTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Reports\EmployeesWithoutTrainingReport;

test('it lists only employees with no completed training that year', function () {
    $trained = Employee::factory()->create();
    Employee::factory()->count(2)->create();

    $training = Training::factory()->create(['from_date' => '2026-05-04', 'to_date' => '2026-05-06']);
    TrainingAssignment::factory()->for($training)->for($trained)->completed()->create();

    $report = new EmployeesWithoutTrainingReport(2026);

    expect($report->total())->toBe(2)
        ->and($report->employees()->pluck('id'))->not->toContain($trained->id);
});

test('an assignment that is not completed does not count as training', function () {
    $employee = Employee::factory()->create();
    $training = Training::factory()->create(['from_date' => '2026-05-04', 'to_date' => '2026-05-06']);
    TrainingAssignment::factory()->for($training)->for($employee)->create();

    expect((new EmployeesWithoutTrainingReport(2026))->total())->toBe(1);
});

test('a training counts for the year it ended in, not the year it started', function () {
    $employee = Employee::factory()->create();
    $training = Training::factory()->create(['from_date' => '2025-12-31', 'to_date' => '2026-01-01']);
    TrainingAssignment::factory()->for($training)->for($employee)->completed()->create();

    expect((new EmployeesWithoutTrainingReport(2026))->total())->toBe(0)
        ->and((new EmployeesWithoutTrainingReport(2025))->total())->toBe(1);
});

test('separated employees are not reported', function () {
    Employee::factory()->separated()->create();

    expect((new EmployeesWithoutTrainingReport(2026))->total())->toBe(0);
});

test('it counts the untrained per division', function () {
    $division = Division::factory()->create(['name' => 'Finance Division']);
    $section = Section::factory()->for($division)->create();
    Employee::factory()->count(2)->for($section)->create();

    $counts = (new EmployeesWithoutTrainingReport(2026))->countByDivision();

    expect($counts['Finance Division'])->toBe(2);
});

test('a section head only sees their own section', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();
    $otherSection = Section::factory()->for($division)->create();

    Employee::factory()->for($otherSection)->create();

    $head = User::factory()->sectionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $head->id]);

    expect((new EmployeesWithoutTrainingReport(2026, $head))->total())->toBe(1);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/EmployeesWithoutTrainingReportTest.php
```

Inaasahan: FAIL — wala pa ang klase.

- [ ] **Step 3: Idagdag ang scope sa Employee**

Sa `app/Models/Employee.php`:

```php
/**
 * Employees with no completed training that ended in the given year.
 */
public function scopeWithoutCompletedTrainingIn(Builder $query, int $year): void
{
    $query->whereDoesntHave('trainingAssignments', function (Builder $assignment) use ($year): void {
        $assignment->completed()
            ->whereHas('training', fn (Builder $training) => $training->heldIn($year));
    });
}
```

- [ ] **Step 4: Isulat ang report**

```bash
php artisan make:class Reports/EmployeesWithoutTrainingReport --no-interaction
```

```php
<?php

namespace App\Reports;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeesWithoutTrainingReport
{
    public function __construct(
        private readonly int $year,
        private readonly ?User $scopedTo = null,
    ) {}

    /**
     * Active employees with no completed training held in the report year.
     *
     * @return Collection<int, Employee>
     */
    public function employees(): Collection
    {
        return $this->query()
            ->with(['section.division', 'position'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Head count of untrained employees per division name.
     *
     * @return Collection<string, int>
     */
    public function countByDivision(): Collection
    {
        return $this->employees()
            ->groupBy(fn (Employee $employee): string => $employee->section->division->name)
            ->map(fn (Collection $group): int => $group->count())
            ->sortKeys();
    }

    public function total(): int
    {
        return $this->query()->count();
    }

    /**
     * @return Builder<Employee>
     */
    private function query(): Builder
    {
        $query = Employee::query()
            ->active()
            ->withoutCompletedTrainingIn($this->year);

        if ($this->scopedTo instanceof User) {
            $query->visibleTo($this->scopedTo);
        }

        return $query;
    }
}
```

- [ ] **Step 5: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/EmployeesWithoutTrainingReportTest.php
```

Inaasahan: PASS, anim na test.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add employees-without-training report"
```

---

## Task 8: Report — buod ng aktibidad kada buwan

**Files:**
- Create: `app/Reports/MonthlyTrainingActivityReport.php`
- Test: `tests/Feature/MonthlyTrainingActivityReportTest.php`

**Interfaces:**
- Consumes: `TrainingAssignment`, `Training`, `Employee`, `User`
- Produces: `new MonthlyTrainingActivityReport(int $year, int $month, ?User $scopedTo = null)` na may:
  - `trainings(): Collection<int, array{training: Training, participants: int, hours: int}>`
  - `totals(): array{trainings: int, participants: int, hours: int}`
  - `byDivision(): Collection<string, array{participants: int, hours: int}>`

Ang buong report ay hinuhugot sa **natapos na assignment**, hindi sa lahat ng training. Ang training na walang naitalang kalahok ay hindi lumalabas — aktibidad ang sinusukat nito, hindi katalogo. Ganito rin gumagana ang scoping: ang division head ay nakakakita lang ng training na dinaluhan ng tao nila.

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/MonthlyTrainingActivityReportTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Reports\MonthlyTrainingActivityReport;

test('it totals trainings, participants and hours for the month', function () {
    $training = Training::factory()->create([
        'from_date' => '2026-03-02',
        'to_date' => '2026-03-04',
        'hours' => 24,
    ]);
    $employees = Employee::factory()->count(3)->create();

    foreach ($employees as $employee) {
        TrainingAssignment::factory()->for($training)->for($employee)->completed()->create();
    }

    $totals = (new MonthlyTrainingActivityReport(2026, 3))->totals();

    expect($totals['trainings'])->toBe(1)
        ->and($totals['participants'])->toBe(3)
        ->and($totals['hours'])->toBe(72);
});

test('it ignores trainings from other months', function () {
    $march = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04', 'hours' => 8]);
    $april = Training::factory()->create(['from_date' => '2026-04-02', 'to_date' => '2026-04-04', 'hours' => 8]);

    TrainingAssignment::factory()->for($march)->completed()->create();
    TrainingAssignment::factory()->for($april)->completed()->create();

    expect((new MonthlyTrainingActivityReport(2026, 3))->totals()['trainings'])->toBe(1);
});

test('it ignores assignments that were never completed', function () {
    $training = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04', 'hours' => 8]);
    TrainingAssignment::factory()->for($training)->create();

    expect((new MonthlyTrainingActivityReport(2026, 3))->totals()['participants'])->toBe(0);
});

test('it groups participants and hours by division', function () {
    $finance = Division::factory()->create(['name' => 'Finance Division']);
    $operations = Division::factory()->create(['name' => 'Operations Division']);
    $financeSection = Section::factory()->for($finance)->create();
    $operationsSection = Section::factory()->for($operations)->create();

    $training = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04', 'hours' => 16]);

    TrainingAssignment::factory()->for($training)
        ->for(Employee::factory()->for($financeSection))->completed()->create();
    TrainingAssignment::factory()->for($training)
        ->for(Employee::factory()->for($financeSection))->completed()->create();
    TrainingAssignment::factory()->for($training)
        ->for(Employee::factory()->for($operationsSection))->completed()->create();

    $byDivision = (new MonthlyTrainingActivityReport(2026, 3))->byDivision();

    expect($byDivision['Finance Division'])->toBe(['participants' => 2, 'hours' => 32])
        ->and($byDivision['Operations Division'])->toBe(['participants' => 1, 'hours' => 16]);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/MonthlyTrainingActivityReportTest.php
```

Inaasahan: FAIL — wala pa ang klase.

- [ ] **Step 3: Isulat ang report**

```bash
php artisan make:class Reports/MonthlyTrainingActivityReport --no-interaction
```

```php
<?php

namespace App\Reports;

use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthlyTrainingActivityReport
{
    /**
     * @var Collection<int, TrainingAssignment>|null
     */
    private ?Collection $assignments = null;

    public function __construct(
        private readonly int $year,
        private readonly int $month,
        private readonly ?User $scopedTo = null,
    ) {}

    /**
     * Trainings attended during the month, with attendance counts.
     *
     * @return Collection<int, array{training: Training, participants: int, hours: int}>
     */
    public function trainings(): Collection
    {
        return $this->completedAssignments()
            ->groupBy('training_id')
            ->map(function (Collection $group): array {
                $training = $group->first()->training;

                return [
                    'training' => $training,
                    'participants' => $group->count(),
                    'hours' => $training->hours * $group->count(),
                ];
            })
            ->sortBy(fn (array $row): string => $row['training']->from_date->toDateString())
            ->values();
    }

    /**
     * @return array{trainings: int, participants: int, hours: int}
     */
    public function totals(): array
    {
        $rows = $this->trainings();

        return [
            'trainings' => $rows->count(),
            'participants' => (int) $rows->sum('participants'),
            'hours' => (int) $rows->sum('hours'),
        ];
    }

    /**
     * Attendance and training hours per division name.
     *
     * @return Collection<string, array{participants: int, hours: int}>
     */
    public function byDivision(): Collection
    {
        return $this->completedAssignments()
            ->groupBy(fn (TrainingAssignment $assignment): string => $assignment->employee->section->division->name)
            ->map(fn (Collection $group): array => [
                'participants' => $group->count(),
                'hours' => (int) $group->sum(fn (TrainingAssignment $assignment): int => $assignment->training->hours),
            ])
            ->sortKeys();
    }

    /**
     * Every completed assignment for a training that ended in the report month.
     *
     * @return Collection<int, TrainingAssignment>
     */
    private function completedAssignments(): Collection
    {
        if ($this->assignments instanceof Collection) {
            return $this->assignments;
        }

        $query = TrainingAssignment::query()
            ->completed()
            ->whereHas('training', fn (Builder $training) => $training->heldDuring($this->year, $this->month))
            ->with(['training', 'employee.section.division']);

        if ($this->scopedTo instanceof User) {
            $query->whereHas('employee', fn (Builder $employee) => $employee->visibleTo($this->scopedTo));
        }

        return $this->assignments = $query->get();
    }
}
```

- [ ] **Step 4: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/MonthlyTrainingActivityReportTest.php
```

Inaasahan: PASS, apat na test.

- [ ] **Step 5: Patakbuhin ang buong suite**

```bash
php artisan test --compact
```

Inaasahan: PASS lahat. Tapos na ang buong backend; UI na ang natitira.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add monthly training activity report"
```

---
## Livewire page conventions (basahin bago ang Task 9)

Bawat page ay isang single-file component. Ang hugis, batay sa `resources/views/pages/settings/⚡profile.blade.php`:

```blade
<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Page title')] class extends Component {
    public string $example = '';

    public function save(): void
    {
        //
    }
}; ?>

<div>
    {{-- markup dito --}}
</div>
```

Mahahalagang tuntunin:

- Ang file ay `resources/views/pages/<folder>/⚡<name>.blade.php`, tinutukoy bilang `pages::<folder>.<name>`.
- **Huwag ibalot ang page sa `<x-layouts::app>`.** Ang default na `component_layout` ng Livewire ay `layouts::app` at awtomatiko itong nilalapat ng `Route::livewire()`. Nasa layout na ang `<flux:main>`.
- Isang root element lang ang markup.
- Ang mga route parameter ay ipinapasa sa `mount()`.
- Sa test: `Livewire::test('pages::employees.index')`.

---

## Task 9: Setup screens — divisions, sections, positions

**Files:**
- Create: `resources/views/pages/setup/⚡divisions.blade.php`, `⚡sections.blade.php`, `⚡positions.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SetupScreensTest.php`

**Interfaces:**
- Consumes: `Division`, `Section`, `Position`, `User::isHrAdmin()`
- Produces: mga route na `setup.divisions`, `setup.sections`, `setup.positions`

Ang reference data ay walang policy class. Isang linyang `abort_unless(auth()->user()->isHrAdmin(), 403)` sa `mount()` at sa bawat mutating action ang ginagamit — mas kaunti kaysa tatlong halos magkaparehong policy class para sa tatlong table na HR lang naman ang humahawak.

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/SetupScreensTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

test('an hr admin can add a division', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    Livewire::test('pages::setup.divisions')
        ->set('name', 'Finance and Administrative Division')
        ->set('code', 'FAD')
        ->call('save')
        ->assertHasNoErrors();

    expect(Division::where('code', 'FAD')->exists())->toBeTrue();
});

test('division codes must be unique', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());
    Division::factory()->create(['code' => 'FAD']);

    Livewire::test('pages::setup.divisions')
        ->set('name', 'Another Division')
        ->set('code', 'FAD')
        ->call('save')
        ->assertHasErrors('code');
});

test('an hr admin can add a section under a division', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());
    $division = Division::factory()->create();

    Livewire::test('pages::setup.sections')
        ->set('divisionId', $division->id)
        ->set('name', 'Human Resource Section')
        ->set('code', 'HRS')
        ->call('save')
        ->assertHasNoErrors();

    expect(Section::where('code', 'HRS')->first()->division_id)->toBe($division->id);
});

test('an hr admin can add a position', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    Livewire::test('pages::setup.positions')
        ->set('title', 'Administrative Officer V')
        ->set('salaryGrade', 18)
        ->call('save')
        ->assertHasNoErrors();

    expect(Position::where('title', 'Administrative Officer V')->first()->salary_grade)->toBe(18);
});

test('a section head cannot open the setup screens', function () {
    $this->actingAs(User::factory()->sectionHead()->create());

    $this->get(route('setup.divisions'))->assertForbidden();
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/SetupScreensTest.php
```

Inaasahan: FAIL — walang route na `setup.divisions`.

- [ ] **Step 3: Gawin ang divisions page**

Gumawa ng `resources/views/pages/setup/⚡divisions.blade.php`:

```blade
<?php

use App\Models\Division;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Divisions')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed]
    public function divisions(): Collection
    {
        return Division::query()->withCount('sections')->orderBy('name')->get();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);

        $division = Division::findOrFail($id);

        $this->editingId = $division->id;
        $this->name = $division->name;
        $this->code = $division->code;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('divisions', 'code')->ignore($this->editingId)],
        ]);

        Division::updateOrCreate(['id' => $this->editingId], $validated);

        $this->resetForm();
        unset($this->divisions);

        Flux::toast(variant: 'success', text: __('Division saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'code');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Divisions') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-4 md:flex-row md:items-end">
            <flux:input wire:model="name" :label="__('Name')" class="flex-1" required />
            <flux:input wire:model="code" :label="__('Code')" class="md:w-40" required />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('Update') : __('Add') }}
                </flux:button>

                @if ($editingId)
                    <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                @endif
            </div>
        </form>
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Sections') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->divisions as $division)
                <flux:table.row :key="$division->id">
                    <flux:table.cell>{{ $division->name }}</flux:table.cell>
                    <flux:table.cell>{{ $division->code }}</flux:table.cell>
                    <flux:table.cell>{{ $division->sections_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $division->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No divisions yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 4: Gawin ang sections page**

Gumawa ng `resources/views/pages/setup/⚡sections.blade.php`:

```blade
<?php

use App\Models\Division;
use App\Models\Section;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sections')] class extends Component {
    public ?int $editingId = null;

    public ?int $divisionId = null;

    public string $name = '';

    public string $code = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);
    }

    /**
     * @return Collection<int, Section>
     */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()->with('division')->withCount('employees')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed]
    public function divisions(): Collection
    {
        return Division::query()->orderBy('name')->get();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);

        $section = Section::findOrFail($id);

        $this->editingId = $section->id;
        $this->divisionId = $section->division_id;
        $this->name = $section->name;
        $this->code = $section->code;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);

        $validated = $this->validate([
            'divisionId' => ['required', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sections', 'code')->ignore($this->editingId)],
        ]);

        Section::updateOrCreate(['id' => $this->editingId], [
            'division_id' => $validated['divisionId'],
            'name' => $validated['name'],
            'code' => $validated['code'],
        ]);

        $this->resetForm();
        unset($this->sections);

        Flux::toast(variant: 'success', text: __('Section saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'divisionId', 'name', 'code');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Sections') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-4 md:flex-row md:items-end">
            <flux:select wire:model="divisionId" :label="__('Division')" class="flex-1" required>
                <flux:select.option value="">{{ __('Select a division') }}</flux:select.option>
                @foreach ($this->divisions as $division)
                    <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="name" :label="__('Name')" class="flex-1" required />
            <flux:input wire:model="code" :label="__('Code')" class="md:w-40" required />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('Update') : __('Add') }}
                </flux:button>

                @if ($editingId)
                    <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                @endif
            </div>
        </form>
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Employees') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->sections as $section)
                <flux:table.row :key="$section->id">
                    <flux:table.cell>{{ $section->name }}</flux:table.cell>
                    <flux:table.cell>{{ $section->division->name }}</flux:table.cell>
                    <flux:table.cell>{{ $section->code }}</flux:table.cell>
                    <flux:table.cell>{{ $section->employees_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $section->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No sections yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 5: Gawin ang positions page**

Gumawa ng `resources/views/pages/setup/⚡positions.blade.php`:

```blade
<?php

use App\Models\Position;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Positions')] class extends Component {
    public ?int $editingId = null;

    public string $title = '';

    public ?int $salaryGrade = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);
    }

    /**
     * @return Collection<int, Position>
     */
    #[Computed]
    public function positions(): Collection
    {
        return Position::query()->withCount('employees')->orderBy('title')->get();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);

        $position = Position::findOrFail($id);

        $this->editingId = $position->id;
        $this->title = $position->title;
        $this->salaryGrade = $position->salary_grade;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isHrAdmin(), 403);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'salaryGrade' => ['nullable', 'integer', 'between:1,33'],
        ]);

        Position::updateOrCreate(['id' => $this->editingId], [
            'title' => $validated['title'],
            'salary_grade' => $validated['salaryGrade'],
        ]);

        $this->resetForm();
        unset($this->positions);

        Flux::toast(variant: 'success', text: __('Position saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'title', 'salaryGrade');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Positions') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-4 md:flex-row md:items-end">
            <flux:input wire:model="title" :label="__('Title')" class="flex-1" required />
            <flux:input wire:model="salaryGrade" :label="__('Salary grade')" type="number" min="1" max="33" class="md:w-40" />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('Update') : __('Add') }}
                </flux:button>

                @if ($editingId)
                    <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                @endif
            </div>
        </form>
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Salary grade') }}</flux:table.column>
            <flux:table.column>{{ __('Employees') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->positions as $position)
                <flux:table.row :key="$position->id">
                    <flux:table.cell>{{ $position->title }}</flux:table.cell>
                    <flux:table.cell>{{ $position->salary_grade ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $position->employees_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $position->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('No positions yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 6: Irehistro ang mga route**

Sa `routes/web.php`, sa loob ng umiiral na `Route::middleware(['auth', 'verified'])->group(...)`:

```php
Route::livewire('setup/divisions', 'pages::setup.divisions')->name('setup.divisions');
Route::livewire('setup/sections', 'pages::setup.sections')->name('setup.sections');
Route::livewire('setup/positions', 'pages::setup.positions')->name('setup.positions');
```

- [ ] **Step 7: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/SetupScreensTest.php
```

Inaasahan: PASS, limang test.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add reference data setup screens"
```

---

## Task 10: Employee screens

**Files:**
- Create: `resources/views/pages/employees/⚡index.blade.php`, `⚡form.blade.php`, `⚡show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/EmployeeScreensTest.php`

**Interfaces:**
- Consumes: `Employee`, `Section`, `Position`, enums, `EmployeePolicy`
- Produces: mga route na `employees.index`, `employees.create`, `employees.edit`, `employees.show`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/EmployeeScreensTest.php`:

```php
<?php

use App\Enums\EmploymentStatus;
use App\Enums\Sex;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Livewire\Livewire;

test('the index lists only visible active employees', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $active = Employee::factory()->create(['last_name' => 'Bonifacio']);
    Employee::factory()->separated()->create(['last_name' => 'Aguinaldo']);

    Livewire::test('pages::employees.index')
        ->assertSee('Bonifacio')
        ->assertDontSee('Aguinaldo');
});

test('the index can filter down to employees without training this year', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $trained = Employee::factory()->create(['last_name' => 'Mabini']);
    Employee::factory()->create(['last_name' => 'Jacinto']);

    $training = Training::factory()->create([
        'from_date' => now()->startOfYear(),
        'to_date' => now()->startOfYear()->addDay(),
    ]);
    TrainingAssignment::factory()->for($training)->for($trained)->completed()->create();

    Livewire::test('pages::employees.index')
        ->set('trainingStatus', 'untrained')
        ->assertSee('Jacinto')
        ->assertDontSee('Mabini');
});

test('an hr admin can create an employee', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());
    $section = Section::factory()->create();
    $position = Position::factory()->create();

    Livewire::test('pages::employees.form')
        ->set('employee_no', 'EMP-00001')
        ->set('first_name', 'Andres')
        ->set('last_name', 'Bonifacio')
        ->set('sex', Sex::Male->value)
        ->set('date_of_birth', '1990-11-30')
        ->set('section_id', $section->id)
        ->set('position_id', $position->id)
        ->set('employment_status', EmploymentStatus::Permanent->value)
        ->set('date_hired', '2015-01-05')
        ->call('save')
        ->assertHasNoErrors();

    expect(Employee::where('employee_no', 'EMP-00001')->exists())->toBeTrue();
});

test('employee numbers must be unique', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());
    Employee::factory()->create(['employee_no' => 'EMP-00001']);
    $section = Section::factory()->create();
    $position = Position::factory()->create();

    Livewire::test('pages::employees.form')
        ->set('employee_no', 'EMP-00001')
        ->set('first_name', 'Andres')
        ->set('last_name', 'Bonifacio')
        ->set('sex', Sex::Male->value)
        ->set('date_of_birth', '1990-11-30')
        ->set('section_id', $section->id)
        ->set('position_id', $position->id)
        ->set('employment_status', EmploymentStatus::Permanent->value)
        ->set('date_hired', '2015-01-05')
        ->call('save')
        ->assertHasErrors('employee_no');
});

test('a division head cannot reach the employee form', function () {
    $this->actingAs(User::factory()->divisionHead()->create());

    $this->get(route('employees.create'))->assertForbidden();
});

test('the show page lists the employee training history', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $employee = Employee::factory()->create();
    $training = Training::factory()->create(['title' => 'Records Management Seminar']);
    TrainingAssignment::factory()->for($training)->for($employee)->completed()->create();

    $this->get(route('employees.show', $employee))
        ->assertOk()
        ->assertSee('Records Management Seminar');
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/EmployeeScreensTest.php
```

Inaasahan: FAIL — walang route na `employees.index`.

- [ ] **Step 3: Gawin ang index page**

Gumawa ng `resources/views/pages/employees/⚡index.blade.php`:

```blade
<?php

use App\Models\Employee;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Employees')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $sectionId = null;

    /**
     * Either an empty string for everyone, or "untrained".
     */
    #[Url]
    public string $trainingStatus = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSectionId(): void
    {
        $this->resetPage();
    }

    public function updatedTrainingStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    #[Computed]
    public function employees(): LengthAwarePaginator
    {
        return Employee::query()
            ->visibleTo(auth()->user())
            ->active()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';

                $query->where(function (Builder $match) use ($term): void {
                    $match->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('employee_no', 'like', $term);
                });
            })
            ->when($this->sectionId !== null, fn (Builder $query) => $query->where('section_id', $this->sectionId))
            ->when($this->trainingStatus === 'untrained', fn (Builder $query) => $query->withoutCompletedTrainingIn(now()->year))
            ->with(['section.division', 'position'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);
    }

    /**
     * @return Collection<int, Section>
     */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()->orderBy('name')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Employees') }}</flux:heading>

        @can('create', App\Models\Employee::class)
            <flux:button :href="route('employees.create')" variant="primary" wire:navigate>
                {{ __('Add employee') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-4 md:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name or employee number')" class="flex-1" />

        <flux:select wire:model.live="sectionId" class="md:w-64">
            <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
            @foreach ($this->sections as $section)
                <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="trainingStatus" class="md:w-64">
            <flux:select.option value="">{{ __('All employees') }}</flux:select.option>
            <flux:select.option value="untrained">{{ __('No training this year') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->employees">
        <flux:table.columns>
            <flux:table.column>{{ __('Employee no.') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Position') }}</flux:table.column>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->employees as $employee)
                <flux:table.row :key="$employee->id">
                    <flux:table.cell>{{ $employee->employee_no }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('employees.show', $employee)" wire:navigate>
                            {{ $employee->full_name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->position->title }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->section->name }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->section->division->name }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No employees found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 4: Gawin ang form page**

Gumawa ng `resources/views/pages/employees/⚡form.blade.php`. Ang isang page na ito ang humahawak ng create at edit — ang route ang nagpapasa ng `Employee` kung meron.

```blade
<?php

use App\Enums\EmploymentStatus;
use App\Enums\Sex;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Employee')] class extends Component {
    public ?Employee $employee = null;

    public string $employee_no = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $name_extension = '';

    public string $sex = '';

    public string $date_of_birth = '';

    public ?int $section_id = null;

    public ?int $position_id = null;

    public string $employment_status = '';

    public string $date_hired = '';

    public string $separated_at = '';

    public function mount(?Employee $employee = null): void
    {
        if ($employee?->exists) {
            $this->authorize('update', $employee);

            $this->employee = $employee;
            $this->employee_no = $employee->employee_no;
            $this->first_name = $employee->first_name;
            $this->middle_name = $employee->middle_name ?? '';
            $this->last_name = $employee->last_name;
            $this->name_extension = $employee->name_extension ?? '';
            $this->sex = $employee->sex->value;
            $this->date_of_birth = $employee->date_of_birth->toDateString();
            $this->section_id = $employee->section_id;
            $this->position_id = $employee->position_id;
            $this->employment_status = $employee->employment_status->value;
            $this->date_hired = $employee->date_hired->toDateString();
            $this->separated_at = $employee->separated_at?->toDateString() ?? '';

            return;
        }

        $this->authorize('create', Employee::class);
    }

    /**
     * @return Collection<int, Section>
     */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()->with('division')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Position>
     */
    #[Computed]
    public function positions(): Collection
    {
        return Position::query()->orderBy('title')->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'employee_no' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_no')->ignore($this->employee?->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'section_id' => ['required', 'exists:sections,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'date_hired' => ['required', 'date'],
            'separated_at' => ['nullable', 'date', 'after_or_equal:date_hired'],
        ]);

        $validated['middle_name'] = $validated['middle_name'] ?: null;
        $validated['name_extension'] = $validated['name_extension'] ?: null;
        $validated['separated_at'] = $validated['separated_at'] ?: null;

        if ($this->employee instanceof Employee) {
            $this->authorize('update', $this->employee);
            $this->employee->update($validated);
        } else {
            $this->authorize('create', Employee::class);
            $this->employee = Employee::create($validated);
        }

        Flux::toast(variant: 'success', text: __('Employee saved.'));

        $this->redirectRoute('employees.show', $this->employee, navigate: true);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">
        {{ $employee ? __('Edit employee') : __('Add employee') }}
    </flux:heading>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="employee_no" :label="__('Employee number')" required />
                <flux:select wire:model="employment_status" :label="__('Employment status')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (App\Enums\EmploymentStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <flux:input wire:model="first_name" :label="__('First name')" required />
                <flux:input wire:model="middle_name" :label="__('Middle name')" />
                <flux:input wire:model="last_name" :label="__('Last name')" required />
                <flux:input wire:model="name_extension" :label="__('Extension')" :placeholder="__('Jr., Sr., III')" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="sex" :label="__('Sex')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (App\Enums\Sex::cases() as $sex)
                        <flux:select.option :value="$sex->value">{{ $sex->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="date_of_birth" :label="__('Date of birth')" type="date" required />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="section_id" :label="__('Section')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach ($this->sections as $section)
                        <flux:select.option :value="$section->id">
                            {{ $section->division->name }} — {{ $section->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="position_id" :label="__('Position')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach ($this->positions as $position)
                        <flux:select.option :value="$position->id">{{ $position->title }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="date_hired" :label="__('Date hired')" type="date" required />
                <flux:input wire:model="separated_at" :label="__('Separated on')" type="date"
                    :description="__('Leave blank while the employee is still in service.')" />
            </div>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button :href="route('employees.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
```

- [ ] **Step 5: Gawin ang show page**

Gumawa ng `resources/views/pages/employees/⚡show.blade.php`:

```blade
<?php

use App\Models\Employee;
use App\Models\TrainingAssignment;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Employee')] class extends Component {
    public Employee $employee;

    public function mount(Employee $employee): void
    {
        $this->authorize('view', $employee);

        $this->employee = $employee->load(['section.division', 'position']);
    }

    /**
     * The employee's whole training history, newest first.
     *
     * This listing is what PDS page 4 will be built from in Phase 4.
     *
     * @return Collection<int, TrainingAssignment>
     */
    #[Computed]
    public function assignments(): Collection
    {
        return $this->employee->trainingAssignments()
            ->with('training')
            ->get()
            ->sortByDesc(fn (TrainingAssignment $assignment): string => $assignment->training->to_date->toDateString())
            ->values();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $employee->full_name }}</flux:heading>
            <flux:text>{{ $employee->position->title }} — {{ $employee->section->name }}</flux:text>
        </div>

        @can('update', $employee)
            <flux:button :href="route('employees.edit', $employee)" wire:navigate>{{ __('Edit') }}</flux:button>
        @endcan
    </div>

    <flux:card class="grid gap-4 md:grid-cols-3">
        <div>
            <flux:text size="sm">{{ __('Employee number') }}</flux:text>
            <flux:heading size="lg">{{ $employee->employee_no }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Division') }}</flux:text>
            <flux:heading size="lg">{{ $employee->section->division->name }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Employment status') }}</flux:text>
            <flux:heading size="lg">{{ $employee->employment_status->label() }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Date hired') }}</flux:text>
            <flux:heading size="lg">{{ $employee->date_hired->format('d M Y') }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Date of birth') }}</flux:text>
            <flux:heading size="lg">{{ $employee->date_of_birth->format('d M Y') }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Sex') }}</flux:text>
            <flux:heading size="lg">{{ $employee->sex->label() }}</flux:heading>
        </div>
    </flux:card>

    @if ($employee->separated_at)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Separated on :date', ['date' => $employee->separated_at->format('d M Y')]) }}
        </flux:callout>
    @endif

    <flux:heading size="lg">{{ __('Training history') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Conducted by') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->assignments as $assignment)
                <flux:table.row :key="$assignment->id">
                    <flux:table.cell>{{ $assignment->training->title }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $assignment->training->from_date->format('d M Y') }} –
                        {{ $assignment->training->to_date->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $assignment->training->hours }}</flux:table.cell>
                    <flux:table.cell>{{ $assignment->training->ld_type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $assignment->training->conducted_by }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($assignment->completed_at)
                            <flux:badge color="green">{{ __('Completed') }}</flux:badge>
                        @else
                            <flux:badge color="zinc">{{ __('Pending') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No training on record.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 6: Irehistro ang mga route**

Sa `routes/web.php`, sa loob ng auth group. **Mahalaga ang pagkakasunod** — dapat nauuna ang `employees/create` kaysa `employees/{employee}`, kung hindi ay susubukan nitong hanapin ang employee na ang id ay "create".

```php
Route::livewire('employees', 'pages::employees.index')->name('employees.index');
Route::livewire('employees/create', 'pages::employees.form')->name('employees.create');
Route::livewire('employees/{employee}', 'pages::employees.show')->name('employees.show');
Route::livewire('employees/{employee}/edit', 'pages::employees.form')->name('employees.edit');
```

- [ ] **Step 7: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/EmployeeScreensTest.php
```

Inaasahan: PASS, anim na test.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add employee screens"
```

---
## Task 11: Training screens

**Files:**
- Create: `resources/views/pages/trainings/⚡index.blade.php`, `⚡form.blade.php`, `⚡show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TrainingScreensTest.php`

**Interfaces:**
- Consumes: `Training`, `Employee`, `TrainingAssignment`, `AssignTrainingToEmployees`, `RecordTrainingCompletion`, `TrainingPolicy`
- Produces: mga route na `trainings.index`, `trainings.create`, `trainings.show`, `trainings.edit`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/TrainingScreensTest.php`:

```php
<?php

use App\Enums\LdType;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Livewire\Livewire;

test('an hr admin can create a training with the PDS fields', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    Livewire::test('pages::trainings.form')
        ->set('title', 'Records Management Seminar')
        ->set('from_date', '2026-03-02')
        ->set('to_date', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasNoErrors();

    expect(Training::where('title', 'Records Management Seminar')->first()->hours)->toBe(24);
});

test('the end date cannot come before the start date', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    Livewire::test('pages::trainings.form')
        ->set('title', 'Records Management Seminar')
        ->set('from_date', '2026-03-04')
        ->set('to_date', '2026-03-02')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasErrors('to_date');
});

test('an hr admin can bulk assign a training', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $training = Training::factory()->create();
    $employees = Employee::factory()->count(3)->create();

    Livewire::test('pages::trainings.show', ['training' => $training])
        ->set('selectedEmployeeIds', $employees->pluck('id')->all())
        ->call('assign')
        ->assertHasNoErrors();

    expect(TrainingAssignment::where('training_id', $training->id)->count())->toBe(3);
});

test('an hr admin can mark a participant as completed', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $training = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04']);
    $employee = Employee::factory()->create();
    TrainingAssignment::factory()->for($training)->for($employee)->create();

    Livewire::test('pages::trainings.show', ['training' => $training])
        ->set('completionDate', '2026-03-04')
        ->call('markCompleted', $employee->id)
        ->assertHasNoErrors();

    expect(TrainingAssignment::first()->completed_at->toDateString())->toBe('2026-03-04');
});

test('an impossible completion date is reported as a form error', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $training = Training::factory()->create(['from_date' => '2026-03-02', 'to_date' => '2026-03-04']);
    $employee = Employee::factory()->create();
    TrainingAssignment::factory()->for($training)->for($employee)->create();

    Livewire::test('pages::trainings.show', ['training' => $training])
        ->set('completionDate', '2026-03-01')
        ->call('markCompleted', $employee->id)
        ->assertHasErrors('completionDate');
});

test('a division head cannot assign a training', function () {
    $this->actingAs(User::factory()->divisionHead()->create());

    $training = Training::factory()->create();
    $employee = Employee::factory()->create();

    Livewire::test('pages::trainings.show', ['training' => $training])
        ->set('selectedEmployeeIds', [$employee->id])
        ->call('assign')
        ->assertForbidden();
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/TrainingScreensTest.php
```

Inaasahan: FAIL — walang component na `pages::trainings.form`.

- [ ] **Step 3: Gawin ang index page**

Gumawa ng `resources/views/pages/trainings/⚡index.blade.php`:

```blade
<?php

use App\Enums\LdType;
use App\Models\Training;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Trainings')] class extends Component {
    use WithPagination;

    #[Url]
    public int $year;

    #[Url]
    public string $ldType = '';

    public function mount(): void
    {
        $this->year = now()->year;
    }

    public function updatedYear(): void
    {
        $this->resetPage();
    }

    public function updatedLdType(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Training>
     */
    #[Computed]
    public function trainings(): LengthAwarePaginator
    {
        return Training::query()
            ->heldIn($this->year)
            ->when($this->ldType !== '', fn (Builder $query) => $query->where('ld_type', $this->ldType))
            ->withCount(['assignments as completed_count' => fn (Builder $query) => $query->whereNotNull('completed_at')])
            ->withCount('assignments')
            ->orderByDesc('from_date')
            ->paginate(25);
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function years(): array
    {
        return range(now()->year, now()->year - 10);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Trainings') }}</flux:heading>

        @can('create', App\Models\Training::class)
            <flux:button :href="route('trainings.create')" variant="primary" wire:navigate>
                {{ __('Add training') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-col gap-4 md:flex-row">
        <flux:select wire:model.live="year" class="md:w-40">
            @foreach ($this->years as $year)
                <flux:select.option :value="$year">{{ $year }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="ldType" class="md:w-64">
            <flux:select.option value="">{{ __('All types of LD') }}</flux:select.option>
            @foreach (App\Enums\LdType::cases() as $type)
                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->trainings">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Completed') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->trainings as $training)
                <flux:table.row :key="$training->id">
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $training)" wire:navigate>
                            {{ $training->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $training->from_date->format('d M Y') }} – {{ $training->to_date->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $training->hours }}</flux:table.cell>
                    <flux:table.cell>{{ $training->ld_type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $training->completed_count }} / {{ $training->assignments_count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No trainings for this year.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 4: Gawin ang form page**

Gumawa ng `resources/views/pages/trainings/⚡form.blade.php`:

```blade
<?php

use App\Enums\LdType;
use App\Models\Training;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Training')] class extends Component {
    public ?Training $training = null;

    public string $title = '';

    public string $from_date = '';

    public string $to_date = '';

    public ?int $hours = null;

    public string $ld_type = '';

    public string $conducted_by = '';

    public function mount(?Training $training = null): void
    {
        if ($training?->exists) {
            $this->authorize('update', $training);

            $this->training = $training;
            $this->title = $training->title;
            $this->from_date = $training->from_date->toDateString();
            $this->to_date = $training->to_date->toDateString();
            $this->hours = $training->hours;
            $this->ld_type = $training->ld_type->value;
            $this->conducted_by = $training->conducted_by;

            return;
        }

        $this->authorize('create', Training::class);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'hours' => ['required', 'integer', 'min:1', 'max:9999'],
            'ld_type' => ['required', Rule::enum(LdType::class)],
            'conducted_by' => ['required', 'string', 'max:255'],
        ]);

        if ($this->training instanceof Training) {
            $this->authorize('update', $this->training);
            $this->training->update($validated);
        } else {
            $this->authorize('create', Training::class);
            $this->training = Training::create($validated);
        }

        Flux::toast(variant: 'success', text: __('Training saved.'));

        $this->redirectRoute('trainings.show', $this->training, navigate: true);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ $training ? __('Edit training') : __('Add training') }}</flux:heading>

    <flux:callout icon="information-circle">
        {{ __('These are the Learning and Development fields of the Personal Data Sheet. Fill them in exactly as they should appear there.') }}
    </flux:callout>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="title" :label="__('Title of learning and development intervention')" required />

            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="from_date" :label="__('From')" type="date" required />
                <flux:input wire:model="to_date" :label="__('To')" type="date" required />
                <flux:input wire:model="hours" :label="__('Number of hours')" type="number" min="1" required />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model="ld_type" :label="__('Type of LD')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (App\Enums\LdType::cases() as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="conducted_by" :label="__('Conducted or sponsored by')" required />
            </div>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button :href="route('trainings.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
```

- [ ] **Step 5: Gawin ang show page**

Gumawa ng `resources/views/pages/trainings/⚡show.blade.php`:

```blade
<?php

use App\Actions\Training\AssignTrainingToEmployees;
use App\Actions\Training\RecordTrainingCompletion;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingAssignment;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Training')] class extends Component {
    public Training $training;

    /**
     * @var array<int, int>
     */
    public array $selectedEmployeeIds = [];

    public string $dueDate = '';

    public string $completionDate = '';

    public function mount(Training $training): void
    {
        $this->authorize('view', $training);

        $this->training = $training;
        $this->completionDate = $training->to_date->toDateString();
    }

    /**
     * @return Collection<int, TrainingAssignment>
     */
    #[Computed]
    public function assignments(): Collection
    {
        return $this->training->assignments()
            ->with('employee.section')
            ->get()
            ->sortBy(fn (TrainingAssignment $assignment): string => $assignment->employee->last_name)
            ->values();
    }

    /**
     * Active employees who do not have this training yet.
     *
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function assignableEmployees(): Collection
    {
        return Employee::query()
            ->active()
            ->whereDoesntHave(
                'trainingAssignments',
                fn (Builder $assignment) => $assignment->where('training_id', $this->training->id),
            )
            ->with('section')
            ->orderBy('last_name')
            ->get();
    }

    public function assign(): void
    {
        $this->authorize('assign', $this->training);

        $this->validate([
            'selectedEmployeeIds' => ['required', 'array', 'min:1'],
            'selectedEmployeeIds.*' => ['integer', 'exists:employees,id'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $assigned = app(AssignTrainingToEmployees::class)->handle(
            $this->training,
            $this->selectedEmployeeIds,
            auth()->user(),
            $this->dueDate !== '' ? Carbon::parse($this->dueDate) : null,
        );

        $this->reset('selectedEmployeeIds', 'dueDate');
        unset($this->assignments, $this->assignableEmployees);

        Flux::toast(variant: 'success', text: trans_choice(':count employee assigned.|:count employees assigned.', $assigned, ['count' => $assigned]));
        Flux::modal('assign-employees')->close();
    }

    public function markCompleted(int $employeeId): void
    {
        $this->authorize('assign', $this->training);

        $this->validate(['completionDate' => ['required', 'date']]);

        try {
            app(RecordTrainingCompletion::class)->handle(
                $this->training,
                Employee::findOrFail($employeeId),
                Carbon::parse($this->completionDate),
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('completionDate', $exception->getMessage());

            return;
        }

        unset($this->assignments);

        Flux::toast(variant: 'success', text: __('Completion recorded.'));
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">{{ $training->title }}</flux:heading>
            <flux:text>
                {{ $training->from_date->format('d M Y') }} – {{ $training->to_date->format('d M Y') }} ·
                {{ $training->hours }} {{ __('hours') }} · {{ $training->ld_type->label() }} ·
                {{ $training->conducted_by }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            @can('update', $training)
                <flux:button :href="route('trainings.edit', $training)" wire:navigate>{{ __('Edit') }}</flux:button>
            @endcan

            @can('assign', $training)
                <flux:modal.trigger name="assign-employees">
                    <flux:button variant="primary">{{ __('Assign employees') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    @can('assign', $training)
        <flux:card class="flex flex-col gap-4 md:flex-row md:items-end">
            <flux:input wire:model="completionDate" :label="__('Completion date')" type="date" class="md:w-64" />
            <flux:text size="sm">{{ __('Used when marking a participant as completed below.') }}</flux:text>
        </flux:card>
    @endcan

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Due') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->assignments as $assignment)
                <flux:table.row :key="$assignment->id">
                    <flux:table.cell>{{ $assignment->employee->full_name }}</flux:table.cell>
                    <flux:table.cell>{{ $assignment->employee->section->name }}</flux:table.cell>
                    <flux:table.cell>{{ $assignment->due_date?->format('d M Y') ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($assignment->completed_at)
                            <flux:badge color="green">
                                {{ __('Completed :date', ['date' => $assignment->completed_at->format('d M Y')]) }}
                            </flux:badge>
                        @else
                            <flux:badge color="zinc">{{ __('Pending') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if (! $assignment->completed_at)
                            @can('assign', $training)
                                <flux:button size="sm" wire:click="markCompleted({{ $assignment->employee_id }})">
                                    {{ __('Mark completed') }}
                                </flux:button>
                            @endcan
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nobody has been assigned yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:error name="completionDate" />

    <flux:modal name="assign-employees" class="md:w-2xl">
        <form wire:submit="assign" class="space-y-6">
            <flux:heading size="lg">{{ __('Assign employees') }}</flux:heading>

            <flux:input wire:model="dueDate" :label="__('Due date (optional)')" type="date" />

            <flux:error name="selectedEmployeeIds" />

            <div class="max-h-80 space-y-2 overflow-y-auto">
                @forelse ($this->assignableEmployees as $employee)
                    <flux:checkbox
                        wire:model="selectedEmployeeIds"
                        :value="$employee->id"
                        :label="$employee->full_name.' — '.$employee->section->name"
                    />
                @empty
                    <flux:text>{{ __('Every active employee already has this training.') }}</flux:text>
                @endforelse
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">{{ __('Assign') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
```

- [ ] **Step 6: Irehistro ang mga route**

Sa `routes/web.php`, sa loob ng auth group. Gaya ng employees, nauuna ang `create` kaysa `{training}`:

```php
Route::livewire('trainings', 'pages::trainings.index')->name('trainings.index');
Route::livewire('trainings/create', 'pages::trainings.form')->name('trainings.create');
Route::livewire('trainings/{training}', 'pages::trainings.show')->name('trainings.show');
Route::livewire('trainings/{training}/edit', 'pages::trainings.form')->name('trainings.edit');
```

- [ ] **Step 7: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/TrainingScreensTest.php
```

Inaasahan: PASS, anim na test.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add training screens with bulk assign and completion"
```

---

## Task 12: Report screens at print view

**Files:**
- Create: `resources/views/pages/reports/⚡monthly-activity.blade.php`, `⚡no-training.blade.php`
- Create: `resources/views/layouts/print.blade.php`, `resources/views/reports/monthly-activity-print.blade.php`, `resources/views/reports/no-training-print.blade.php`
- Create: `app/Http/Controllers/TrainingReportPrintController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ReportScreensTest.php`

**Interfaces:**
- Consumes: `MonthlyTrainingActivityReport`, `EmployeesWithoutTrainingReport`
- Produces: mga route na `reports.monthly-activity`, `reports.no-training`, `reports.monthly-activity.print`, `reports.no-training.print`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/ReportScreensTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Livewire\Livewire;

test('the monthly activity screen shows the trainings held that month', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $training = Training::factory()->create([
        'title' => 'Records Management Seminar',
        'from_date' => '2026-03-02',
        'to_date' => '2026-03-04',
        'hours' => 24,
    ]);
    TrainingAssignment::factory()->for($training)->completed()->create();

    Livewire::test('pages::reports.monthly-activity')
        ->set('year', 2026)
        ->set('month', 3)
        ->assertSee('Records Management Seminar');
});

test('the monthly activity screen hides trainings from other months', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $training = Training::factory()->create([
        'title' => 'April Only Seminar',
        'from_date' => '2026-04-02',
        'to_date' => '2026-04-04',
    ]);
    TrainingAssignment::factory()->for($training)->completed()->create();

    Livewire::test('pages::reports.monthly-activity')
        ->set('year', 2026)
        ->set('month', 3)
        ->assertDontSee('April Only Seminar');
});

test('the no-training screen lists employees with nothing completed that year', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $division = Division::factory()->create(['name' => 'Finance Division']);
    $section = Section::factory()->for($division)->create();
    Employee::factory()->for($section)->create(['last_name' => 'Jacinto']);

    Livewire::test('pages::reports.no-training')
        ->set('year', 2026)
        ->assertSee('Jacinto')
        ->assertSee('Finance Division');
});

test('the print views render', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $this->get(route('reports.monthly-activity.print', ['year' => 2026, 'month' => 3]))->assertOk();
    $this->get(route('reports.no-training.print', ['year' => 2026]))->assertOk();
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/ReportScreensTest.php
```

Inaasahan: FAIL — wala pa ang mga component.

- [ ] **Step 3: Gawin ang monthly activity page**

Gumawa ng `resources/views/pages/reports/⚡monthly-activity.blade.php`:

```blade
<?php

use App\Reports\MonthlyTrainingActivityReport;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Monthly training activity')] class extends Component {
    #[Url]
    public int $year;

    #[Url]
    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    #[Computed]
    public function report(): MonthlyTrainingActivityReport
    {
        return new MonthlyTrainingActivityReport($this->year, $this->month, auth()->user());
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function years(): array
    {
        return range(now()->year, now()->year - 10);
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    #[Computed]
    public function months(): Collection
    {
        return collect(range(1, 12))->map(fn (int $month): array => [
            'value' => $month,
            'label' => now()->startOfYear()->addMonths($month - 1)->format('F'),
        ]);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Monthly training activity') }}</flux:heading>

        <flux:button
            :href="route('reports.monthly-activity.print', ['year' => $year, 'month' => $month])"
            target="_blank"
            icon="printer"
        >
            {{ __('Print') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-4 md:flex-row">
        <flux:select wire:model.live="month" class="md:w-48">
            @foreach ($this->months as $month)
                <flux:select.option :value="$month['value']">{{ $month['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="year" class="md:w-40">
            @foreach ($this->years as $year)
                <flux:select.option :value="$year">{{ $year }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @php($totals = $this->report->totals())

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text size="sm">{{ __('Trainings') }}</flux:text>
            <flux:heading size="xl">{{ $totals['trainings'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Participants') }}</flux:text>
            <flux:heading size="xl">{{ $totals['participants'] }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm">{{ __('Training hours') }}</flux:text>
            <flux:heading size="xl">{{ $totals['hours'] }}</flux:heading>
        </flux:card>
    </div>

    <flux:heading size="lg">{{ __('Trainings held') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Participants') }}</flux:table.column>
            <flux:table.column>{{ __('Total hours') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->report->trainings() as $row)
                <flux:table.row :key="$row['training']->id">
                    <flux:table.cell>{{ $row['training']->title }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $row['training']->from_date->format('d M Y') }} –
                        {{ $row['training']->to_date->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $row['training']->ld_type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $row['participants'] }}</flux:table.cell>
                    <flux:table.cell>{{ $row['hours'] }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No training activity this month.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:heading size="lg">{{ __('By division') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Participants') }}</flux:table.column>
            <flux:table.column>{{ __('Training hours') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->report->byDivision() as $division => $figures)
                <flux:table.row :key="$division">
                    <flux:table.cell>{{ $division }}</flux:table.cell>
                    <flux:table.cell>{{ $figures['participants'] }}</flux:table.cell>
                    <flux:table.cell>{{ $figures['hours'] }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">{{ __('Nothing to show.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 4: Gawin ang no-training page**

Gumawa ng `resources/views/pages/reports/⚡no-training.blade.php`:

```blade
<?php

use App\Reports\EmployeesWithoutTrainingReport;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Employees without training')] class extends Component {
    #[Url]
    public int $year;

    public function mount(): void
    {
        $this->year = now()->year;
    }

    #[Computed]
    public function report(): EmployeesWithoutTrainingReport
    {
        return new EmployeesWithoutTrainingReport($this->year, auth()->user());
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function years(): array
    {
        return range(now()->year, now()->year - 10);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Employees without training') }}</flux:heading>

        <flux:button :href="route('reports.no-training.print', ['year' => $year])" target="_blank" icon="printer">
            {{ __('Print') }}
        </flux:button>
    </div>

    <flux:select wire:model.live="year" class="md:w-40">
        @foreach ($this->years as $year)
            <flux:select.option :value="$year">{{ $year }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:card>
        <flux:text size="sm">{{ __('Employees with no completed training in :year', ['year' => $year]) }}</flux:text>
        <flux:heading size="xl">{{ $this->report->total() }}</flux:heading>
    </flux:card>

    <flux:heading size="lg">{{ __('By division') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Untrained') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->report->countByDivision() as $division => $count)
                <flux:table.row :key="$division">
                    <flux:table.cell>{{ $division }}</flux:table.cell>
                    <flux:table.cell>{{ $count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2">{{ __('Everyone has training this year.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:heading size="lg">{{ __('Employees') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Employee no.') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Position') }}</flux:table.column>
            <flux:table.column>{{ __('Section') }}</flux:table.column>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->report->employees() as $employee)
                <flux:table.row :key="$employee->id">
                    <flux:table.cell>{{ $employee->employee_no }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('employees.show', $employee)" wire:navigate>
                            {{ $employee->full_name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->position->title }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->section->name }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->section->division->name }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Everyone has training this year.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 5: Gawin ang print layout at views**

Gumawa ng `resources/views/layouts/print.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 24px 0 8px; }
        p.meta { margin: 0 0 16px; color: #444; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>{{ $heading }}</h1>
    <p class="meta">{{ $subheading }}</p>

    {{ $slot }}
</body>
</html>
```

Gumawa ng `resources/views/reports/monthly-activity-print.blade.php`:

```blade
<x-layouts.print
    :title="__('Monthly training activity')"
    :heading="config('app.name').' — '.__('Monthly Training Activity Report')"
    :subheading="$periodLabel"
>
    <table>
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Inclusive dates') }}</th>
                <th>{{ __('Type of LD') }}</th>
                <th>{{ __('Conducted by') }}</th>
                <th>{{ __('Participants') }}</th>
                <th>{{ __('Total hours') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->trainings() as $row)
                <tr>
                    <td>{{ $row['training']->title }}</td>
                    <td>{{ $row['training']->from_date->format('d M Y') }} – {{ $row['training']->to_date->format('d M Y') }}</td>
                    <td>{{ $row['training']->ld_type->label() }}</td>
                    <td>{{ $row['training']->conducted_by }}</td>
                    <td>{{ $row['participants'] }}</td>
                    <td>{{ $row['hours'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">{{ __('No training activity this month.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ __('By division') }}</h2>

    <table>
        <thead>
            <tr>
                <th>{{ __('Division') }}</th>
                <th>{{ __('Participants') }}</th>
                <th>{{ __('Training hours') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->byDivision() as $division => $figures)
                <tr>
                    <td>{{ $division }}</td>
                    <td>{{ $figures['participants'] }}</td>
                    <td>{{ $figures['hours'] }}</td>
                </tr>
            @empty
                <tr><td colspan="3">{{ __('Nothing to show.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</x-layouts.print>
```

Gumawa ng `resources/views/reports/no-training-print.blade.php`:

```blade
<x-layouts.print
    :title="__('Employees without training')"
    :heading="config('app.name').' — '.__('Employees Without Training')"
    :subheading="__('Calendar year :year', ['year' => $year])"
>
    <table>
        <thead>
            <tr>
                <th>{{ __('Employee no.') }}</th>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Position') }}</th>
                <th>{{ __('Section') }}</th>
                <th>{{ __('Division') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->employees() as $employee)
                <tr>
                    <td>{{ $employee->employee_no }}</td>
                    <td>{{ $employee->full_name }}</td>
                    <td>{{ $employee->position->title }}</td>
                    <td>{{ $employee->section->name }}</td>
                    <td>{{ $employee->section->division->name }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('Everyone has training this year.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</x-layouts.print>
```

- [ ] **Step 6: Gawin ang print controller**

```bash
php artisan make:controller TrainingReportPrintController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Reports\EmployeesWithoutTrainingReport;
use App\Reports\MonthlyTrainingActivityReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TrainingReportPrintController extends Controller
{
    public function monthlyActivity(Request $request): View
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $period = Carbon::create($validated['year'], $validated['month'], 1);

        return view('reports.monthly-activity-print', [
            'report' => new MonthlyTrainingActivityReport($validated['year'], $validated['month'], $request->user()),
            'periodLabel' => $period->format('F Y'),
        ]);
    }

    public function employeesWithoutTraining(Request $request): View
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        return view('reports.no-training-print', [
            'report' => new EmployeesWithoutTrainingReport($validated['year'], $request->user()),
            'year' => $validated['year'],
        ]);
    }
}
```

- [ ] **Step 7: Irehistro ang mga route**

Sa `routes/web.php`, sa loob ng auth group. Idagdag ang import na `use App\Http\Controllers\TrainingReportPrintController;` sa itaas ng file.

```php
Route::livewire('reports/monthly-activity', 'pages::reports.monthly-activity')->name('reports.monthly-activity');
Route::livewire('reports/no-training', 'pages::reports.no-training')->name('reports.no-training');

Route::get('reports/monthly-activity/print', [TrainingReportPrintController::class, 'monthlyActivity'])
    ->name('reports.monthly-activity.print');
Route::get('reports/no-training/print', [TrainingReportPrintController::class, 'employeesWithoutTraining'])
    ->name('reports.no-training.print');
```

- [ ] **Step 8: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/ReportScreensTest.php
```

Inaasahan: PASS, apat na test.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add report screens and printable views"
```

---

## Task 13: Dashboard at navigation

**Files:**
- Create: `resources/views/pages/⚡dashboard.blade.php`
- Delete: `resources/views/dashboard.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app/sidebar.blade.php`
- Test: `tests/Feature/DashboardTest.php` (dagdagan ang umiiral)

**Interfaces:**
- Consumes: `Employee`, `TrainingAssignment`, `EmployeesWithoutTrainingReport`
- Produces: ang route na `dashboard` ay Livewire component na

- [ ] **Step 1: Dagdagan ang umiiral na test**

Idagdag sa `tests/Feature/DashboardTest.php`:

```php
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\Training;
use App\Models\TrainingAssignment;

test('the dashboard counts active employees and those without training', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $trained = Employee::factory()->create();
    Employee::factory()->count(2)->create();
    Employee::factory()->separated()->create();

    $training = Training::factory()->create([
        'from_date' => now()->startOfYear(),
        'to_date' => now()->startOfYear()->addDay(),
    ]);
    TrainingAssignment::factory()->for($training)->for($trained)->completed()->create();

    Livewire::test('pages::dashboard')
        ->assertSet('activeEmployees', 3)
        ->assertSet('untrainedEmployees', 2);
});

test('the dashboard lists overdue assignments', function () {
    $this->actingAs(User::factory()->hrAdmin()->create());

    $employee = Employee::factory()->create(['last_name' => 'Jacinto']);
    $training = Training::factory()->create(['title' => 'Overdue Seminar']);
    TrainingAssignment::factory()->for($training)->for($employee)->overdue()->create();

    Livewire::test('pages::dashboard')->assertSee('Overdue Seminar');
});
```

Idagdag ang import na `use Livewire\Livewire;` sa itaas ng file.

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/DashboardTest.php
```

Inaasahan: FAIL — hindi Livewire component ang `dashboard`.

- [ ] **Step 3: Gawin ang dashboard page**

Gumawa ng `resources/views/pages/⚡dashboard.blade.php`:

```blade
<?php

use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Reports\EmployeesWithoutTrainingReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public int $activeEmployees = 0;

    public int $untrainedEmployees = 0;

    public function mount(): void
    {
        $this->activeEmployees = Employee::query()->visibleTo(auth()->user())->active()->count();
        $this->untrainedEmployees = (new EmployeesWithoutTrainingReport(now()->year, auth()->user()))->total();
    }

    /**
     * @return Collection<string, int>
     */
    #[Computed]
    public function untrainedByDivision(): Collection
    {
        return (new EmployeesWithoutTrainingReport(now()->year, auth()->user()))->countByDivision();
    }

    /**
     * @return Collection<int, TrainingAssignment>
     */
    #[Computed]
    public function overdue(): Collection
    {
        return TrainingAssignment::query()
            ->overdue()
            ->whereHas('employee', fn (Builder $employee) => $employee->visibleTo(auth()->user())->active())
            ->with(['training', 'employee'])
            ->orderBy('due_date')
            ->limit(20)
            ->get();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Training compliance :year', ['year' => now()->year]) }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text size="sm">{{ __('Active employees') }}</flux:text>
            <flux:heading size="xl">{{ $activeEmployees }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text size="sm">{{ __('No training this year') }}</flux:text>
            <flux:heading size="xl">{{ $untrainedEmployees }}</flux:heading>
            <flux:link :href="route('reports.no-training')" wire:navigate>{{ __('View report') }}</flux:link>
        </flux:card>

        <flux:card>
            <flux:text size="sm">{{ __('Overdue assignments') }}</flux:text>
            <flux:heading size="xl">{{ $this->overdue->count() }}</flux:heading>
        </flux:card>
    </div>

    <flux:heading size="lg">{{ __('Untrained by division') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Division') }}</flux:table.column>
            <flux:table.column>{{ __('Untrained') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->untrainedByDivision as $division => $count)
                <flux:table.row :key="$division">
                    <flux:table.cell>{{ $division }}</flux:table.cell>
                    <flux:table.cell>{{ $count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2">{{ __('Everyone has training this year.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:heading size="lg">{{ __('Overdue assignments') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Training') }}</flux:table.column>
            <flux:table.column>{{ __('Due') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->overdue as $assignment)
                <flux:table.row :key="$assignment->id">
                    <flux:table.cell>{{ $assignment->employee->full_name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $assignment->training)" wire:navigate>
                            {{ $assignment->training->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $assignment->due_date->format('d M Y') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">{{ __('Nothing is overdue.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 4: Palitan ang dashboard route**

Sa `routes/web.php`, palitan ang `Route::view('dashboard', 'dashboard')->name('dashboard');` ng:

```php
Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
```

Tapos burahin ang lumang view:

```bash
rm resources/views/dashboard.blade.php
```

- [ ] **Step 5: Idagdag ang navigation**

Sa `resources/views/layouts/app/sidebar.blade.php`, palitan ang umiiral na `Platform` group ng:

```blade
<flux:sidebar.group :heading="__('Monitoring')" class="grid">
    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="users" :href="route('employees.index')" :current="request()->routeIs('employees.*')" wire:navigate>
        {{ __('Employees') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="academic-cap" :href="route('trainings.index')" :current="request()->routeIs('trainings.*')" wire:navigate>
        {{ __('Trainings') }}
    </flux:sidebar.item>
</flux:sidebar.group>

<flux:sidebar.group :heading="__('Reports')" class="grid">
    <flux:sidebar.item icon="calendar" :href="route('reports.monthly-activity')" :current="request()->routeIs('reports.monthly-activity')" wire:navigate>
        {{ __('Monthly activity') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="exclamation-triangle" :href="route('reports.no-training')" :current="request()->routeIs('reports.no-training')" wire:navigate>
        {{ __('Without training') }}
    </flux:sidebar.item>
</flux:sidebar.group>

@if (auth()->user()->isHrAdmin())
    <flux:sidebar.group :heading="__('Setup')" class="grid">
        <flux:sidebar.item icon="building-office" :href="route('setup.divisions')" :current="request()->routeIs('setup.divisions')" wire:navigate>
            {{ __('Divisions') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="rectangle-group" :href="route('setup.sections')" :current="request()->routeIs('setup.sections')" wire:navigate>
            {{ __('Sections') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="briefcase" :href="route('setup.positions')" :current="request()->routeIs('setup.positions')" wire:navigate>
            {{ __('Positions') }}
        </flux:sidebar.item>
    </flux:sidebar.group>
@endif
```

Tanggalin din ang `Repository` at `Documentation` na link ng starter kit sa ibabang `flux:sidebar.nav` — hindi na ito kabilang sa isang HR system.

- [ ] **Step 6: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/DashboardTest.php
```

Inaasahan: PASS, apat na test.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add compliance dashboard and navigation"
```

---

## Task 14: Seeder at unang HR admin

**Files:**
- Create: `database/seeders/OrgStructureSeeder.php`, `app/Console/Commands/CreateHrAdmin.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/CreateHrAdminTest.php`

**Interfaces:**
- Consumes: `Division`, `Section`, `Position`, `User`, `UserRole`
- Produces: ang command na `php artisan hr:create-admin`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/CreateHrAdminTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an hr administrator', function () {
    $this->artisan('hr:create-admin', [
        '--name' => 'HR Officer',
        '--email' => 'hr@example.test',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $user = User::where('email', 'hr@example.test')->first();

    expect($user->role)->toBe(UserRole::HrAdmin)
        ->and(Hash::check('secret-password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('it refuses a duplicate email address', function () {
    User::factory()->create(['email' => 'hr@example.test']);

    $this->artisan('hr:create-admin', [
        '--name' => 'HR Officer',
        '--email' => 'hr@example.test',
        '--password' => 'secret-password',
    ])->assertFailed();
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/CreateHrAdminTest.php
```

Inaasahan: FAIL — walang command na `hr:create-admin`.

- [ ] **Step 3: Gawin ang command**

```bash
php artisan make:command CreateHrAdmin --no-interaction
```

```php
<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateHrAdmin extends Command
{
    protected $signature = 'hr:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create an HR administrator account';

    public function handle(): int
    {
        $attributes = [
            'name' => $this->option('name') ?: text('Name', required: true),
            'email' => $this->option('email') ?: text('Email address', required: true),
            'password' => $this->option('password') ?: password('Password', required: true),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'role' => UserRole::HrAdmin,
            'email_verified_at' => now(),
        ]);

        $this->info("HR administrator [{$attributes['email']}] created.");

        return self::SUCCESS;
    }
}
```

Ang `email_verified_at` ay tahasang itinatakda dahil ang account ay gawa ng administrator — walang verification email na ipapadala.

Siguraduhing nasa `$fillable` ng `User` ang `'role'` at `'email_verified_at'`. Kung wala ang `email_verified_at`, itakda ito matapos ang `create()`.

- [ ] **Step 4: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/CreateHrAdminTest.php
```

Inaasahan: PASS, dalawang test.

- [ ] **Step 5: Gawin ang org structure seeder**

```bash
php artisan make:seeder OrgStructureSeeder --no-interaction
```

**Palitan ang halimbawang datos ng aktwal na org chart ng ahensya.** Ito ang bukas na tanong 11.2 sa spec — ito lang ang hugis.

```php
<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Database\Seeder;

class OrgStructureSeeder extends Seeder
{
    /**
     * Seed the divisions, sections and positions.
     *
     * Replace the arrays below with the agency's real organisational chart.
     *
     * @var array<string, array{code: string, sections: array<string, string>}>
     */
    private const DIVISIONS = [
        'Office of the Director' => [
            'code' => 'OD',
            'sections' => [
                'Executive Staff' => 'OD-EXE',
            ],
        ],
        'Finance and Administrative Division' => [
            'code' => 'FAD',
            'sections' => [
                'Human Resource Section' => 'FAD-HRS',
                'Accounting Section' => 'FAD-ACC',
                'Records Section' => 'FAD-REC',
            ],
        ],
        'Operations Division' => [
            'code' => 'OPS',
            'sections' => [
                'Field Operations Section' => 'OPS-FLD',
                'Monitoring Section' => 'OPS-MON',
            ],
        ],
    ];

    /**
     * @var array<string, int>
     */
    private const POSITIONS = [
        'Administrative Aide IV' => 4,
        'Administrative Assistant II' => 8,
        'Administrative Officer III' => 14,
        'Administrative Officer V' => 18,
        'Project Development Officer II' => 15,
        'Supervising Administrative Officer' => 22,
        'Division Chief' => 24,
    ];

    public function run(): void
    {
        foreach (self::DIVISIONS as $name => $details) {
            $division = Division::firstOrCreate(['code' => $details['code']], ['name' => $name]);

            foreach ($details['sections'] as $sectionName => $sectionCode) {
                Section::firstOrCreate(
                    ['code' => $sectionCode],
                    ['division_id' => $division->id, 'name' => $sectionName],
                );
            }
        }

        foreach (self::POSITIONS as $title => $salaryGrade) {
            Position::firstOrCreate(['title' => $title], ['salary_grade' => $salaryGrade]);
        }
    }
}
```

- [ ] **Step 6: Ikabit ang seeder**

Sa `database/seeders/DatabaseSeeder.php`, sa loob ng `run()`, tanggalin ang anumang paggawa ng halimbawang user at ilagay:

```php
$this->call(OrgStructureSeeder::class);
```

- [ ] **Step 7: Subukan ang buong daloy**

```bash
php artisan migrate:fresh --seed
php artisan hr:create-admin --name="HR Officer" --email=hr@example.test --password=secret-password
php artisan route:list --except-vendor
```

Inaasahan: tumatakbo ang migrations, may divisions, sections at positions na, at lumalabas ang lahat ng route na `employees.*`, `trainings.*`, `reports.*`, `setup.*`.

- [ ] **Step 8: Patakbuhin ang buong suite**

```bash
php artisan test --compact
```

Inaasahan: PASS lahat.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: add org structure seeder and hr admin command"
```

---

## Matapos ang lahat ng task

1. Patakbuhin ang buong suite: `php artisan test --compact`
2. Patakbuhin ang static analysis: `vendor/bin/phpstan analyse` (may `phpstan.neon` na ang project)
3. `npm run build`, tapos tingnan ang app sa browser
4. Ibalik sa user ang anim na bukas na tanong sa spec — lalo na ang 11.4 (`hours` per training o per kalahok), dahil bago mag-encode ng totoong data ang pinakamurang panahon para baguhin iyon

## Hindi kasama — Phase 2 pataas

LDNA at competency GAP, training budget, PDS export, employee self-service login, expiry ng training, rules-based na pag-assign, upload ng certificate, at Excel export. Nakatayo na ang pundasyon para sa lahat ng ito: ang `employees` ay PDS-ready, ang `trainings` ay may eksaktong PDS L&D fields, at ang mga report ay hiwalay nang klase na kayang gamitin ulit.
