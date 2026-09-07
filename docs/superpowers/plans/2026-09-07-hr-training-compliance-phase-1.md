# LDI Training System — Implementation Plan (Phase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ang pangunahing loop ng LDI system — org at empleyado mula sa `hris_db`, training records na may gastos at PDS L&D fields, at ang dalawang hakbang na approval (section head → division head).

**Architecture:** Manipis na Livewire single-file component para sa CRUD at listahan. Ang workflow — pagsu-submit, pag-route sa tamang approver, pag-apruba, pagtanggi — ay nasa Action class, dahil doon nakatira ang tunay na tuntunin at kailangan itong ma-test nang walang UI. Ang pag-route ng approver ay isang hiwalay na klase, dahil may tatlong sangay ito at siya ang pinakamadaling masira.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4 (single-file components), Flux 2 (free tier), Fortify, Pest 5, MySQL 8.4 (`ldi_db`).

**Spec:** `docs/superpowers/specs/2026-09-07-hr-training-compliance-design.md`

## Global Constraints

- **Basahin lamang ang `hr_training_system` at `hris_db`.** Walang INSERT, UPDATE, DELETE, o DDL sa alinman sa dalawa, kailanman. Ang bagong sistema ay nasa `ldi_db` lamang.
- **Huwag magpatakbo ng `git commit`, `git push`, o `git tag`.** Ang user ang nagko-commit. Sa bawat "Commit" na hakbang, isulat ang commit message at iabot — huwag itong isagawa.
- **Walang bagong composer o npm dependency** nang walang pahintulot.
- **Livewire pages:** nasa `resources/views/pages/` bilang `⚡name.blade.php`, tinutukoy bilang `pages::folder.name`, naka-route via `Route::livewire()`. **Huwag gamitin ang `php artisan make:livewire --sfc`** — sumusulat ito sa `resources/views/components/pages/...` na maling namespace. Gawin nang manual; sundan ang `resources/views/pages/settings/⚡profile.blade.php`. Huwag ibalot ang page sa `<x-layouts::app>` — awtomatiko itong nilalapat ng `Route::livewire()`.
- **Flux 2 free tier lang.** Walang `flux:date-picker` — gamitin ang `<flux:input type="date">`. Tingnan ang `.ai/rules/views.md`.
- **Tests ay tumatakbo sa in-memory SQLite** (`phpunit.xml`), habang MySQL ang dev. Portable dapat ang bawat migration — iwasan ang syntax na MySQL lang. Hindi nagagalaw ng test ang `ldi_db`.
- **PHP style:** laging curly braces; explicit return type; type hint sa lahat ng parameter; constructor property promotion; TitleCase ang enum keys; PHPDoc array shapes.
- **Wika ng UI:** English na nakabalot sa `__()`.
- Patakbuhin ang `vendor/bin/pint --dirty --format agent` bago iabot ang bawat commit message.

---

## File Structure

**Bago:**

```
app/Enums/UserRole.php               Admin, Hr, DivisionHead, SectionHead, Employee
app/Enums/EmploymentStatus.php       Permanent, JobOrder, ContractOfService
app/Enums/LdType.php                 4 PDS types + Other
app/Enums/TrainingStatus.php         Pending, Approved, Rejected
app/Enums/ApprovalLevel.php          SectionHead, DivisionHead
app/Enums/ApprovalDecision.php       Approved, Rejected

app/Models/Division.php              may division_head_employee_id
app/Models/Section.php               may section_head_employee_id
app/Models/Position.php
app/Models/Employee.php              scopes: active, visibleTo
app/Models/TrainingRecord.php        isang row bawat empleyado bawat training
app/Models/TrainingApproval.php      audit trail

app/Workflow/ApprovalRouter.php      sino ang susunod na aprubahan — tatlong sangay
app/Actions/Training/SubmitTrainingRecord.php
app/Actions/Training/DecideOnTrainingRecord.php

app/Policies/TrainingRecordPolicy.php
app/Policies/EmployeePolicy.php

app/Console/Commands/ImportEmployeesFromHris.php

resources/views/pages/⚡dashboard.blade.php
resources/views/pages/trainings/⚡mine.blade.php
resources/views/pages/trainings/⚡form.blade.php
resources/views/pages/trainings/⚡show.blade.php
resources/views/pages/⚡approvals.blade.php
resources/views/pages/employees/⚡index.blade.php
resources/views/pages/employees/⚡show.blade.php
resources/views/pages/setup/⚡divisions.blade.php
resources/views/pages/setup/⚡sections.blade.php
resources/views/pages/setup/⚡positions.blade.php
```

**Babaguhin:** `.env`, `config/fortify.php`, `config/database.php` (koneksyon sa `hris`), `app/Models/User.php`, `database/factories/UserFactory.php`, `routes/web.php`, `resources/views/layouts/app/sidebar.blade.php`.

**Bakit hiwalay ang `ApprovalRouter` sa mga action:** tatlong sangay ito — may section head, walang section head kaya diretso sa division, o sarili mismo ang head kaya nilalaktawan. Dalawang lugar ang gumagamit nito (pagsu-submit at pag-advance pagkatapos ng unang apruba). Kung nasa loob ito ng action, madodoble ang lohika at maghihiwalay ang dalawa.

---

## Task 1: Housekeeping at koneksyon sa `hris_db`

**Files:**
- Modify: `.env`, `config/fortify.php`, `config/database.php`
- Test: `tests/Feature/HrisConnectionTest.php`, `tests/Feature/Auth/RegistrationDisabledTest.php`

**Interfaces:**
- Produces: read-only na koneksyon na `hris`; saradong public registration

- [ ] **Step 1: Itakda ang pangalan ng app**

Sa `.env`, palitan ang `APP_NAME=Laravel`:

```
APP_NAME="LDI System"
```

- [ ] **Step 2: Isulat ang failing test**

Gumawa ng `tests/Feature/Auth/RegistrationDisabledTest.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('public registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('the registration route does not exist', function () {
    expect(Route::has('register'))->toBeFalse();
});
```

Gumawa ng `tests/Feature/HrisConnectionTest.php`:

```php
<?php

test('the hris connection is configured and read only by convention', function () {
    $config = config('database.connections.hris');

    expect($config)->not->toBeNull()
        ->and($config['driver'])->toBe('mysql')
        ->and($config['database'])->toBe(env('HRIS_DB_DATABASE', 'hris_db'));
});
```

- [ ] **Step 3: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/HrisConnectionTest.php tests/Feature/Auth/RegistrationDisabledTest.php
```

Inaasahan: FAIL — walang `hris` connection at buhay pa ang registration.

- [ ] **Step 4: Isara ang registration**

Sa `config/fortify.php`, tanggalin ang linyang `Features::registration(),` sa `features` array.

- [ ] **Step 5: Idagdag ang read-only na koneksyon**

Sa `.env`, idagdag:

```
HRIS_DB_HOST=127.0.0.1
HRIS_DB_PORT=3306
HRIS_DB_DATABASE=hris_db
HRIS_DB_USERNAME=root
HRIS_DB_PASSWORD=
```

Idagdag din ang parehong linya sa `.env.example`, nang walang halaga sa password.

Sa `config/database.php`, sa loob ng `connections` array, kopyahin ang `mysql` na entry at palitan:

```php
'hris' => [
    'driver' => 'mysql',
    'host' => env('HRIS_DB_HOST', '127.0.0.1'),
    'port' => env('HRIS_DB_PORT', '3306'),
    'database' => env('HRIS_DB_DATABASE', 'hris_db'),
    'username' => env('HRIS_DB_USERNAME', 'root'),
    'password' => env('HRIS_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

Isang paalala na dapat nasa itaas ng entry bilang komento: **basahin lamang ang koneksyong ito.** Ang mga MySQL grant ay hindi natin kontrolado, kaya kasunduan lang ito — pero walang code sa proyektong ito ang dapat sumulat dito.

- [ ] **Step 6: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature
```

Inaasahan: PASS lahat. Awtomatikong lalaktawan ang `RegistrationTest.php` — may `skipUnlessFortifyHas()` ito.

- [ ] **Step 7: Commit message**

Patakbuhin ang `vendor/bin/pint --dirty --format agent`, tapos **iabot sa user** ang message na ito — huwag itong isagawa:

```
chore: close public registration and add read-only hris connection
```

---

## Task 2: Enums at org tables

**Files:**
- Create: 6 enum sa `app/Enums`
- Create: migration at model para sa `divisions`, `sections`, `positions`; 3 factory
- Test: `tests/Feature/OrgStructureTest.php`

**Interfaces:**
- Produces: `Division` (name, code, division_head_employee_id, is_active; `sections()`, `head()`), `Section` (division_id, name, code, section_head_employee_id, is_active; `division()`, `employees()`, `head()`), `Position` (title, item_number, salary_grade, is_active); anim na enum na may `label(): string`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/OrgStructureTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Section;
use Illuminate\Database\QueryException;

test('a section belongs to a division', function () {
    $division = Division::factory()->create(['name' => 'Finance Division', 'code' => 'FAD']);
    $section = Section::factory()->for($division)->create(['name' => 'Human Resource', 'code' => 'HRS']);

    expect($section->division->code)->toBe('FAD')
        ->and($division->sections)->toHaveCount(1);
});

test('section codes are unique', function () {
    Section::factory()->create(['code' => 'HRS']);

    expect(fn () => Section::factory()->create(['code' => 'HRS']))
        ->toThrow(QueryException::class);
});

```

Walang test dito para sa `head()` relation: tumutukoy ito sa `Employee`, na gagawin pa lang sa Task 3. Ang pagtawag dito ngayon ay babagsak sa "class not found". Lubusang sinusukat ito ng `ApprovalRouterTest` sa Task 7.

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/OrgStructureTest.php
```

Inaasahan: FAIL — `Class "App\Models\Division" not found`.

- [ ] **Step 3: Gawin ang mga enum**

```bash
php artisan make:enum UserRole --string --no-interaction
php artisan make:enum EmploymentStatus --string --no-interaction
php artisan make:enum LdType --string --no-interaction
php artisan make:enum TrainingStatus --string --no-interaction
php artisan make:enum ApprovalLevel --string --no-interaction
php artisan make:enum ApprovalDecision --string --no-interaction
```

`app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Hr = 'hr';
    case DivisionHead = 'division_head';
    case SectionHead = 'section_head';
    case Employee = 'employee';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }

    /**
     * Roles that may see every employee and every training record.
     */
    public function seesEverything(): bool
    {
        return in_array($this, [self::Admin, self::Hr], true);
    }
}
```

`app/Enums/EmploymentStatus.php` — ang tatlong aktwal na ginagamit sa `hris_db`:

```php
<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Permanent = 'permanent';
    case JobOrder = 'job_order';
    case ContractOfService = 'contract_of_service';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
```

`app/Enums/LdType.php`:

```php
<?php

namespace App\Enums;

enum LdType: string
{
    case Managerial = 'managerial';
    case Supervisory = 'supervisory';
    case Technical = 'technical';
    case Foundation = 'foundation';
    case Other = 'other';

    public function label(): string
    {
        return $this->name;
    }

    /**
     * The first four are the CS Form 212 types. Other carries its own
     * free text in TrainingRecord::$ld_type_other.
     */
    public function requiresOwnText(): bool
    {
        return $this === self::Other;
    }
}
```

`app/Enums/TrainingStatus.php`:

```php
<?php

namespace App\Enums;

enum TrainingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
```

`app/Enums/ApprovalLevel.php`:

```php
<?php

namespace App\Enums;

enum ApprovalLevel: string
{
    case SectionHead = 'section_head';
    case DivisionHead = 'division_head';

    public function label(): string
    {
        return str($this->name)->headline()->toString();
    }
}
```

`app/Enums/ApprovalDecision.php`:

```php
<?php

namespace App\Enums;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

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

```php
Schema::create('divisions', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->unique();
    $table->unsignedBigInteger('division_head_employee_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

```php
Schema::create('sections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('division_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('code')->unique();
    $table->unsignedBigInteger('section_head_employee_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

```php
Schema::create('positions', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('item_number')->nullable();
    $table->unsignedTinyInteger('salary_grade')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

Ang `division_head_employee_id` at `section_head_employee_id` ay **plain column, walang foreign key constraint.** Sadya ito: ang `employees` ay tumutukoy sa `sections`, at kung magdaragdag tayo ng constraint pabalik, magkakaroon ng circular dependency sa pagitan ng dalawang migration. Ang model at ang import command ang sisiguro sa integridad.

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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    /** @use HasFactory<\Database\Factories\DivisionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'division_head_employee_id', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * The employee designated to approve at division level. May be absent.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'division_head_employee_id');
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

    protected $fillable = ['division_id', 'name', 'code', 'section_head_employee_id', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

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

    /**
     * The employee designated to approve at section level. Only 3 of 28
     * sections have one in hris_db, so absence is the normal case.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'section_head_employee_id');
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

    protected $fillable = ['title', 'item_number', 'salary_grade', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
            'division_head_employee_id' => null,
            'is_active' => true,
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
            'section_head_employee_id' => null,
            'is_active' => true,
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
            'item_number' => fake()->bothify('ITEM-####'),
            'salary_grade' => fake()->numberBetween(1, 33),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 6: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/OrgStructureTest.php
```

Inaasahan: PASS, dalawang test.

- [ ] **Step 7: Commit message**

Patakbuhin ang pint, tapos iabot:

```
feat: add enums and organisational structure
```

---

## Task 3: Employees

**Files:**
- Create: migration `create_employees_table`, `app/Models/Employee.php`, `database/factories/EmployeeFactory.php`
- Test: `tests/Feature/EmployeeTest.php`

**Interfaces:**
- Consumes: `Division`, `Section`, `Position`, `EmploymentStatus`
- Produces: `Employee` na may `section()`, `division()`, `position()`, `user()`, `trainingRecords()`, accessor na `full_name`, `scopeActive()`; `EmployeeFactory` na may state na `inactive()`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/EmployeeTest.php`:

```php
<?php

use App\Models\Employee;
use App\Models\Section;

test('an employee belongs to a section and a division', function () {
    $section = Section::factory()->create();
    $employee = Employee::factory()->for($section)->create();

    expect($employee->section->id)->toBe($section->id)
        ->and($employee->division->id)->toBe($section->division_id);
});

test('saving an employee keeps division in step with section', function () {
    $section = Section::factory()->create();
    $employee = Employee::factory()->create(['section_id' => $section->id, 'division_id' => null]);

    expect($employee->fresh()->division_id)->toBe($section->division_id);
});

test('full name joins the parts and omits blanks', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Cruz',
        'suffix' => null,
    ]);

    expect($employee->full_name)->toBe('Maria Santos Cruz');
});

test('full name includes the suffix when present', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Jose',
        'middle_name' => null,
        'last_name' => 'Rizal',
        'suffix' => 'Jr.',
    ]);

    expect($employee->full_name)->toBe('Jose Rizal Jr.');
});

test('the active scope excludes inactive employees', function () {
    Employee::factory()->count(2)->create();
    Employee::factory()->inactive()->create();

    expect(Employee::count())->toBe(3)
        ->and(Employee::active()->count())->toBe(2);
});

test('employee numbers are unique', function () {
    Employee::factory()->create(['employee_number' => 'EMP-001']);

    expect(fn () => Employee::factory()->create(['employee_number' => 'EMP-001']))
        ->toThrow(Illuminate\Database\QueryException::class);
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
    $table->string('employee_number')->unique();
    $table->string('first_name');
    $table->string('middle_name')->nullable();
    $table->string('last_name');
    $table->string('suffix', 20)->nullable();
    $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
    $table->date('date_hired')->nullable();
    $table->string('employment_status');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index('is_active');
});
```

Nullable ang `section_id`, `division_id`, `position_id` at `date_hired` kahit kumpleto ang lahat ng 134 sa `hris_db` — hindi tayo nangangako para sa datos na hindi natin pag-aari, at kailangang makapasok ang import kahit may butas.

- [ ] **Step 4: Gawin ang model at factory**

```bash
php artisan make:model Employee --factory --no-interaction
```

`app/Models/Employee.php`:

```php
<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'position_id',
        'section_id',
        'division_id',
        'date_hired',
        'employment_status',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'date_hired' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            if ($employee->section_id !== null) {
                $employee->division_id = Section::query()
                    ->whereKey($employee->section_id)
                    ->value('division_id');
            }
        });
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
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
     * @return HasMany<TrainingRecord, $this>
     */
    public function trainingRecords(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
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
            $this->suffix,
        ])->filter()->join(' '));
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Employees the given user is allowed to see.
     *
     * Admin and HR see everyone. A division or section head sees their own
     * division or section. Everyone else sees only themselves.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role->seesEverything()) {
            return;
        }

        $employee = $user->employee;

        if ($employee === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        match ($user->role) {
            UserRole::DivisionHead => $query->where('division_id', $employee->division_id),
            UserRole::SectionHead => $query->where('section_id', $employee->section_id),
            default => $query->whereKey($employee->getKey()),
        };
    }
}
```

Ang `booted()` na hook ang nagpapanatiling tugma ang `division_id` sa section. Dalawang column ang hawak natin dahil ganoon ang `hris_db` at kailangan ng division nang tuwiran sa routing — ito ang presyo, at isang lugar lang ito.

`database/factories/EmployeeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
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
            'employee_number' => fake()->unique()->numerify('EMP-#####'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'position_id' => Position::factory(),
            'section_id' => Section::factory(),
            'division_id' => null,
            'date_hired' => fake()->dateTimeBetween('-20 years', '-1 year'),
            'employment_status' => EmploymentStatus::Permanent,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
```

Sinadyang `null` ang `division_id` sa factory — ang `saving` hook ang pupuno nito mula sa section, na siyang sinusubok ng ikalawang test.

- [ ] **Step 5: Patakbuhin ang test**

Ang `scopeVisibleTo` ay tumutukoy sa `User::$role` at `User::employee()`, na gagawin sa Task 4. Hindi ito hinahawakan ng test ng task na ito.

```bash
php artisan test --compact tests/Feature/EmployeeTest.php
```

Inaasahan: PASS, anim na test.

- [ ] **Step 6: Commit message**

```
feat: add employee records
```

---
## Task 4: Roles, saklaw at policies

**Files:**
- Create: migration `add_role_to_users_table`, `app/Policies/EmployeePolicy.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/AccessScopeTest.php`

**Interfaces:**
- Consumes: `Employee`, `UserRole`
- Produces: `User::$role`, `User::employee()`, `User::isAdminOrHr(): bool`; `UserFactory` states `admin()`, `hr()`, `divisionHead()`, `sectionHead()`, `employee()`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/AccessScopeTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

test('hr sees every employee', function () {
    Employee::factory()->count(3)->create();

    expect(Employee::visibleTo(User::factory()->hr()->create())->count())->toBe(3);
});

test('a division head sees only their own division', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();
    Employee::factory()->count(2)->for($ownSection)->create();
    Employee::factory()->for(Section::factory()->create())->create();

    $head = User::factory()->divisionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $head->id]);

    expect(Employee::visibleTo($head)->count())->toBe(3);
});

test('a section head sees only their own section', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();
    $siblingSection = Section::factory()->for($division)->create();

    Employee::factory()->for($ownSection)->create();
    Employee::factory()->for($siblingSection)->create();

    $head = User::factory()->sectionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $head->id]);

    expect(Employee::visibleTo($head)->count())->toBe(2);
});

test('a plain employee sees only themselves', function () {
    Employee::factory()->count(3)->create();

    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    expect(Employee::visibleTo($user)->count())->toBe(1);
});

test('a user with no employee record sees nothing', function () {
    Employee::factory()->count(2)->create();

    expect(Employee::visibleTo(User::factory()->sectionHead()->create())->count())->toBe(0);
});

test('only admin and hr may edit employees', function () {
    $employee = Employee::factory()->create();

    expect(User::factory()->hr()->create()->can('update', $employee))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('update', $employee))->toBeTrue()
        ->and(User::factory()->divisionHead()->create()->can('update', $employee))->toBeFalse();
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/AccessScopeTest.php
```

Inaasahan: FAIL — walang `hr()` state ang `UserFactory`.

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

Walang default — sadya. Bawat account ay may tahasang role, at ang HR o Admin lang ang gumagawa ng account.

- [ ] **Step 4: Baguhin ang User model**

Sa `app/Models/User.php`: idagdag ang `'role'` sa `$fillable`, ang `'role' => UserRole::class` sa `casts()`, at ang mga import na `App\Enums\UserRole` at `Illuminate\Database\Eloquent\Relations\HasOne`. Idagdag ang:

```php
/**
 * @return HasOne<Employee, $this>
 */
public function employee(): HasOne
{
    return $this->hasOne(Employee::class);
}

public function isAdminOrHr(): bool
{
    return $this->role->seesEverything();
}
```

- [ ] **Step 5: Baguhin ang UserFactory**

Idagdag ang `'role' => UserRole::Employee,` sa `definition()`, ang import ng `App\Enums\UserRole`, at ang limang state:

```php
public function admin(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::Admin]);
}

public function hr(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::Hr]);
}

public function divisionHead(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::DivisionHead]);
}

public function sectionHead(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::SectionHead]);
}

public function employee(): static
{
    return $this->state(fn (array $attributes): array => ['role' => UserRole::Employee]);
}
```

Ang `Employee` ang default dahil iyon ang pinakakaraniwang role sa aktwal — 134 na tao, iilan lang ang head.

- [ ] **Step 6: Gawin ang EmployeePolicy**

```bash
php artisan make:policy EmployeePolicy --model=Employee --no-interaction
```

Palitan ang buong laman:

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
        return $user->isAdminOrHr();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isAdminOrHr();
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->isAdminOrHr();
    }
}
```

- [ ] **Step 7: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature
```

Inaasahan: PASS lahat. Kung may bumagsak dahil sa kulang na `role`, ang factory default ang kulang.

- [ ] **Step 8: Commit message**

```
feat: add user roles, visibility scoping and employee policy
```

---

## Task 5: Import ng empleyado mula sa `hris_db`

**Files:**
- Create: `app/Console/Commands/ImportEmployeesFromHris.php`
- Test: `tests/Feature/ImportEmployeesFromHrisTest.php`

**Interfaces:**
- Consumes: koneksyong `hris`, `Division`, `Section`, `Position`, `Employee`
- Produces: ang command na `php artisan ldi:import-employees`

**Mahigpit:** SELECT lamang sa koneksyong `hris`. Walang isusulat doon.

Ang pagkakasunod ay mahalaga: divisions → sections → positions → employees → saka ang head designations. Huli ang mga head dahil tumutukoy sila sa employee na kailangan munang umiral.

- [ ] **Step 1: Isulat ang failing test**

Ang test ay hindi umaasa sa totoong `hris_db`. Gumagawa ito ng pekeng source table sa test connection para masukat ang lohika nang malinaw at hindi marupok.

Gumawa ng `tests/Feature/ImportEmployeesFromHrisTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Point the importer at the test connection itself. Copying the config
    // would not do: each sqlite :memory: connection is its own database, so
    // the fixture tables would be invisible to it.
    config()->set('ldi.hris_connection', config('database.default'));
    config()->set('ldi.hris_tables', [
        'divisions' => 'hris_divisions',
        'sections' => 'hris_sections',
        'positions' => 'hris_positions',
        'employees' => 'hris_employees',
    ]);

    Schema::create('hris_divisions', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('code');
        $table->unsignedBigInteger('division_head_employee_id')->nullable();
    });

    Schema::create('hris_sections', function ($table) {
        $table->id();
        $table->unsignedBigInteger('division_id');
        $table->string('name');
        $table->string('code');
        $table->unsignedBigInteger('section_head_employee_id')->nullable();
    });

    Schema::create('hris_positions', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('item_number')->nullable();
        $table->unsignedTinyInteger('salary_grade')->nullable();
    });

    Schema::create('hris_employees', function ($table) {
        $table->id();
        $table->string('employee_number');
        $table->string('first_name');
        $table->string('middle_name')->nullable();
        $table->string('last_name');
        $table->string('suffix')->nullable();
        $table->unsignedBigInteger('position_id')->nullable();
        $table->unsignedBigInteger('section_id')->nullable();
        $table->unsignedBigInteger('division_id')->nullable();
        $table->date('date_hired')->nullable();
        $table->string('employment_status');
        $table->boolean('is_active')->default(true);
        $table->timestamp('deleted_at')->nullable();
    });

    DB::table('hris_divisions')->insert([
        ['id' => 1, 'name' => 'Finance Division', 'code' => 'FAD', 'division_head_employee_id' => 2],
    ]);
    DB::table('hris_sections')->insert([
        ['id' => 1, 'division_id' => 1, 'name' => 'Human Resource', 'code' => 'HRS', 'section_head_employee_id' => null],
    ]);
    DB::table('hris_positions')->insert([
        ['id' => 1, 'title' => 'Administrative Officer V', 'item_number' => 'ITEM-1', 'salary_grade' => 18],
    ]);
    DB::table('hris_employees')->insert([
        ['id' => 1, 'employee_number' => 'EMP-001', 'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Cruz', 'suffix' => null, 'position_id' => 1, 'section_id' => 1, 'division_id' => 1, 'date_hired' => '2015-01-05', 'employment_status' => 'permanent', 'is_active' => true, 'deleted_at' => null],
        ['id' => 2, 'employee_number' => 'EMP-002', 'first_name' => 'Jose', 'middle_name' => null, 'last_name' => 'Rizal', 'suffix' => null, 'position_id' => 1, 'section_id' => 1, 'division_id' => 1, 'date_hired' => '2010-03-01', 'employment_status' => 'permanent', 'is_active' => true, 'deleted_at' => null],
    ]);
});

test('it imports the organisation and the employees', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    expect(Division::count())->toBe(1)
        ->and(Section::count())->toBe(1)
        ->and(Position::count())->toBe(1)
        ->and(Employee::count())->toBe(2)
        ->and(Employee::where('employee_number', 'EMP-001')->first()->full_name)->toBe('Maria Cruz');
});

test('it wires the employee to its section, division and position', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    $employee = Employee::where('employee_number', 'EMP-001')->first();

    expect($employee->section->code)->toBe('HRS')
        ->and($employee->division->code)->toBe('FAD')
        ->and($employee->position->title)->toBe('Administrative Officer V');
});

test('it sets the head designations after the employees exist', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    $head = Employee::where('employee_number', 'EMP-002')->first();

    expect(Division::first()->division_head_employee_id)->toBe($head->id);
});

test('running it twice changes nothing', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();
    $this->artisan('ldi:import-employees')->assertSuccessful();

    expect(Employee::count())->toBe(2)
        ->and(Division::count())->toBe(1);
});

test('it updates an employee whose details changed at source', function () {
    $this->artisan('ldi:import-employees')->assertSuccessful();

    DB::table('hris_employees')->where('employee_number', 'EMP-001')->update(['last_name' => 'Santos']);
    $this->artisan('ldi:import-employees')->assertSuccessful();

    expect(Employee::where('employee_number', 'EMP-001')->first()->last_name)->toBe('Santos')
        ->and(Employee::count())->toBe(2);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/ImportEmployeesFromHrisTest.php
```

Inaasahan: FAIL — walang command na `ldi:import-employees`.

- [ ] **Step 3: Isulat ang command**

```bash
php artisan make:command ImportEmployeesFromHris --no-interaction
```

```php
<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportEmployeesFromHris extends Command
{
    protected $signature = 'ldi:import-employees';

    protected $description = 'Copy divisions, sections, positions and employees from hris_db';

    /**
     * Maps a source id to the local id, per entity.
     *
     * @var array<string, array<int, int>>
     */
    private array $idMap = [
        'divisions' => [],
        'sections' => [],
        'positions' => [],
        'employees' => [],
    ];

    public function handle(): int
    {
        DB::transaction(function (): void {
            $this->importDivisions();
            $this->importPositions();
            $this->importSections();
            $this->importEmployees();
            $this->importHeadDesignations();
        });

        $this->info(sprintf(
            'Imported %d divisions, %d sections, %d positions, %d employees.',
            count($this->idMap['divisions']),
            count($this->idMap['sections']),
            count($this->idMap['positions']),
            count($this->idMap['employees']),
        ));

        return self::SUCCESS;
    }

    /**
     * Source table names, overridable so tests can point at fixtures.
     *
     * @return array<string, string>
     */
    private function sourceTables(): array
    {
        return config('ldi.hris_tables', [
            'divisions' => 'divisions',
            'sections' => 'sections',
            'positions' => 'positions',
            'employees' => 'employees',
        ]);
    }

    private function source(string $entity): \Illuminate\Database\Query\Builder
    {
        return DB::connection(config('ldi.hris_connection', 'hris'))
            ->table($this->sourceTables()[$entity]);
    }

    private function importDivisions(): void
    {
        foreach ($this->source('divisions')->get() as $row) {
            $division = Division::updateOrCreate(
                ['code' => $row->code],
                ['name' => $row->name, 'is_active' => true],
            );

            $this->idMap['divisions'][$row->id] = $division->id;
        }
    }

    private function importPositions(): void
    {
        foreach ($this->source('positions')->get() as $row) {
            $position = Position::updateOrCreate(
                ['title' => $row->title],
                ['item_number' => $row->item_number, 'salary_grade' => $row->salary_grade, 'is_active' => true],
            );

            $this->idMap['positions'][$row->id] = $position->id;
        }
    }

    private function importSections(): void
    {
        foreach ($this->source('sections')->get() as $row) {
            $section = Section::updateOrCreate(
                ['code' => $row->code],
                [
                    'division_id' => $this->idMap['divisions'][$row->division_id] ?? null,
                    'name' => $row->name,
                    'is_active' => true,
                ],
            );

            $this->idMap['sections'][$row->id] = $section->id;
        }
    }

    private function importEmployees(): void
    {
        foreach ($this->source('employees')->whereNull('deleted_at')->get() as $row) {
            $employee = Employee::updateOrCreate(
                ['employee_number' => $row->employee_number],
                [
                    'first_name' => $row->first_name,
                    'middle_name' => $row->middle_name,
                    'last_name' => $row->last_name,
                    'suffix' => $row->suffix,
                    'position_id' => $this->idMap['positions'][$row->position_id] ?? null,
                    'section_id' => $this->idMap['sections'][$row->section_id] ?? null,
                    'date_hired' => $row->date_hired,
                    'employment_status' => $row->employment_status,
                    'is_active' => (bool) $row->is_active,
                ],
            );

            $this->idMap['employees'][$row->id] = $employee->id;
        }
    }

    /**
     * Head designations come last: they point at employees that must exist first.
     */
    private function importHeadDesignations(): void
    {
        foreach ($this->source('divisions')->get() as $row) {
            Division::whereKey($this->idMap['divisions'][$row->id])->update([
                'division_head_employee_id' => $this->idMap['employees'][$row->division_head_employee_id] ?? null,
            ]);
        }

        foreach ($this->source('sections')->get() as $row) {
            Section::whereKey($this->idMap['sections'][$row->id])->update([
                'section_head_employee_id' => $this->idMap['employees'][$row->section_head_employee_id] ?? null,
            ]);
        }
    }
}
```

Ang `division_id` ng employee ay hindi itinatakda dito — ang `saving` hook ng model ang kumukuha nito mula sa section. Isang lugar lang ang may hawak ng tuntuning iyon.

Dalawang bagay ang configurable, at ang test ang nag-o-override ng dalawa: ang koneksyon (`ldi.hris_connection`, default `hris`) at ang pangalan ng table (`ldi.hris_tables`, default `divisions`, `sections`, `positions`, `employees` — ang aktwal na nasa `hris_db`). Kaya nito, hindi na kailangan ng totoong database sa test.

Ang koneksyon ang mahalaga: kung kokopyahin lang ng test ang config ng `hris`, magbubukas ito ng bagong SQLite `:memory:` na database na walang laman. Ang pagturo sa koneksyon mismo ang gumagana.

- [ ] **Step 4: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/ImportEmployeesFromHrisTest.php
```

Inaasahan: PASS, limang test.

- [ ] **Step 5: Patakbuhin sa totoong datos**

```bash
php artisan ldi:import-employees
```

Inaasahan: `Imported 5 divisions, 28 sections, 59 positions, 134 employees.`

Patunayan na walang nagbago sa pinagkunan:

```bash
php artisan tinker --execute 'echo DB::connection("hris")->table("employees")->count()." employees pa rin sa hris_db\n";'
```

- [ ] **Step 6: Commit message**

```
feat: import organisation and employees from hris_db
```

---
## Task 6: Training records at approval trail

**Files:**
- Create: migration `create_training_records_table`, `create_training_approvals_table`
- Create: `app/Models/TrainingRecord.php`, `app/Models/TrainingApproval.php`, 2 factory
- Test: `tests/Feature/TrainingRecordTest.php`

**Interfaces:**
- Consumes: `Employee`, `User`, `LdType`, `TrainingStatus`, `ApprovalLevel`, `ApprovalDecision`
- Produces: `TrainingRecord` (`employee()`, `submittedBy()`, `approvals()`; `scopeApproved()`, `scopePending()`, `scopeAwaiting(ApprovalLevel)`, `scopeUnroutable()`; accessor na `ld_type_label`), `TrainingApproval`; `TrainingRecordFactory` states `approved()`, `rejected()`, `awaitingDivisionHead()`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/TrainingRecordTest.php`:

```php
<?php

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;

test('a record keeps the PDS fields and the costs', function () {
    $record = TrainingRecord::factory()->create([
        'title' => 'Records Management Seminar',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
        'hours' => 24,
        'ld_type' => LdType::Technical,
        'conducted_by' => 'Civil Service Commission',
        'registration_fee' => 1500.00,
        'tev' => 2350.50,
    ]);

    expect($record->ld_type)->toBe(LdType::Technical)
        ->and($record->hours)->toBe(24)
        ->and((float) $record->registration_fee)->toBe(1500.00)
        ->and((float) $record->tev)->toBe(2350.50);
});

test('the label of a standard type is the PDS name', function () {
    $record = TrainingRecord::factory()->create(['ld_type' => LdType::Technical, 'ld_type_other' => null]);

    expect($record->ld_type_label)->toBe('Technical');
});

test('the label of an other type is its own text', function () {
    $record = TrainingRecord::factory()->create([
        'ld_type' => LdType::Other,
        'ld_type_other' => 'Convention',
    ]);

    expect($record->ld_type_label)->toBe('Convention');
});

test('scopes separate pending, approved and awaiting a level', function () {
    TrainingRecord::factory()->create();
    TrainingRecord::factory()->awaitingDivisionHead()->create();
    TrainingRecord::factory()->approved()->create();
    TrainingRecord::factory()->rejected()->create();

    expect(TrainingRecord::pending()->count())->toBe(2)
        ->and(TrainingRecord::approved()->count())->toBe(1)
        ->and(TrainingRecord::awaiting(ApprovalLevel::DivisionHead)->count())->toBe(1)
        ->and(TrainingRecord::awaiting(ApprovalLevel::SectionHead)->count())->toBe(1);
});

test('a pending record with no level is unroutable', function () {
    TrainingRecord::factory()->create();
    TrainingRecord::factory()->create(['current_level' => null]);
    TrainingRecord::factory()->approved()->create();

    expect(TrainingRecord::unroutable()->count())->toBe(1);
});

test('an approval belongs to its record', function () {
    $record = TrainingRecord::factory()->create();
    $approval = $record->approvals()->create([
        'level' => ApprovalLevel::SectionHead,
        'approver_user_id' => $record->submitted_by,
        'decision' => App\Enums\ApprovalDecision::Approved,
        'remarks' => null,
        'decided_at' => now(),
    ]);

    expect($record->approvals)->toHaveCount(1)
        ->and($approval->level)->toBe(ApprovalLevel::SectionHead);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/TrainingRecordTest.php
```

Inaasahan: FAIL — `Class "App\Models\TrainingRecord" not found`.

- [ ] **Step 3: Gawin ang mga migration**

```bash
php artisan make:migration create_training_records_table --no-interaction
php artisan make:migration create_training_approvals_table --no-interaction
```

```php
Schema::create('training_records', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->date('date_start');
    $table->date('date_end')->index();
    $table->unsignedSmallInteger('hours');
    $table->string('ld_type');
    $table->string('ld_type_other')->nullable();
    $table->string('conducted_by');
    $table->string('location')->nullable();
    $table->decimal('expenses', 10, 2)->nullable();
    $table->decimal('registration_fee', 10, 2)->nullable();
    $table->decimal('tev', 10, 2)->nullable();
    $table->float('cpd_units')->nullable();
    $table->string('status')->index();
    $table->string('current_level')->nullable()->index();
    $table->foreignId('submitted_by')->constrained('users');
    $table->text('rejection_reason')->nullable();
    $table->timestamps();
});
```

```php
Schema::create('training_approvals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('training_record_id')->constrained()->cascadeOnDelete();
    $table->string('level');
    $table->foreignId('approver_user_id')->constrained('users');
    $table->string('decision');
    $table->text('remarks')->nullable();
    $table->timestamp('decided_at');
    $table->timestamps();
});
```

Isang row bawat empleyado bawat training. Indibidwal ang `registration_fee` at `tev` — kaya nga per-employee ang talaan.

- [ ] **Step 4: Gawin ang mga model**

```bash
php artisan make:model TrainingRecord --factory --no-interaction
php artisan make:model TrainingApproval --factory --no-interaction
```

`app/Models/TrainingRecord.php`:

```php
<?php

namespace App\Models;

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingRecord extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'title',
        'date_start',
        'date_end',
        'hours',
        'ld_type',
        'ld_type_other',
        'conducted_by',
        'location',
        'expenses',
        'registration_fee',
        'tev',
        'cpd_units',
        'status',
        'current_level',
        'submitted_by',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'hours' => 'integer',
            'ld_type' => LdType::class,
            'status' => TrainingStatus::class,
            'current_level' => ApprovalLevel::class,
            'expenses' => 'decimal:2',
            'registration_fee' => 'decimal:2',
            'tev' => 'decimal:2',
            'cpd_units' => 'float',
        ];
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
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<TrainingApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(TrainingApproval::class);
    }

    /**
     * What to print for the type of LD. An Other type carries its own text.
     *
     * @return Attribute<string, never>
     */
    protected function ldTypeLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->ld_type->requiresOwnText()
            ? (string) $this->ld_type_other
            : $this->ld_type->label());
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', TrainingStatus::Pending);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', TrainingStatus::Approved);
    }

    public function scopeAwaiting(Builder $query, ApprovalLevel $level): void
    {
        $query->where('status', TrainingStatus::Pending)->where('current_level', $level);
    }

    /**
     * Pending records with nobody to approve them, because neither the
     * section nor the division has a head designated.
     */
    public function scopeUnroutable(Builder $query): void
    {
        $query->where('status', TrainingStatus::Pending)->whereNull('current_level');
    }
}
```

`app/Models/TrainingApproval.php`:

```php
<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingApproval extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'training_record_id',
        'level',
        'approver_user_id',
        'decision',
        'remarks',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ApprovalLevel::class,
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TrainingRecord, $this>
     */
    public function trainingRecord(): BelongsTo
    {
        return $this->belongsTo(TrainingRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
```

- [ ] **Step 5: Gawin ang mga factory**

`database/factories/TrainingRecordFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<\App\Models\TrainingRecord>
 */
class TrainingRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-2 years', 'now'));

        return [
            'employee_id' => Employee::factory(),
            'title' => fake()->sentence(4),
            'date_start' => $start,
            'date_end' => $start->copy()->addDays(fake()->numberBetween(0, 4)),
            'hours' => fake()->numberBetween(8, 40),
            'ld_type' => fake()->randomElement([LdType::Technical, LdType::Supervisory, LdType::Managerial, LdType::Foundation]),
            'ld_type_other' => null,
            'conducted_by' => fake()->company(),
            'location' => fake()->city(),
            'expenses' => fake()->randomFloat(2, 0, 5000),
            'registration_fee' => fake()->randomFloat(2, 0, 3000),
            'tev' => fake()->randomFloat(2, 0, 4000),
            'cpd_units' => fake()->randomFloat(1, 0, 20),
            'status' => TrainingStatus::Pending,
            'current_level' => ApprovalLevel::SectionHead,
            'submitted_by' => User::factory(),
            'rejection_reason' => null,
        ];
    }

    public function awaitingDivisionHead(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TrainingStatus::Pending,
            'current_level' => ApprovalLevel::DivisionHead,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TrainingStatus::Approved,
            'current_level' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TrainingStatus::Rejected,
            'current_level' => null,
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
```

`database/factories/TrainingApprovalFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TrainingApproval>
 */
class TrainingApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_record_id' => TrainingRecord::factory(),
            'level' => ApprovalLevel::SectionHead,
            'approver_user_id' => User::factory(),
            'decision' => ApprovalDecision::Approved,
            'remarks' => null,
            'decided_at' => now(),
        ];
    }
}
```

- [ ] **Step 6: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/TrainingRecordTest.php
```

Inaasahan: PASS, anim na test.

- [ ] **Step 7: Commit message**

```
feat: add training records and the approval trail
```

---

## Task 7: Approval routing at pagsu-submit

**Files:**
- Create: `app/Workflow/ApprovalRouter.php`, `app/Actions/Training/SubmitTrainingRecord.php`
- Test: `tests/Feature/ApprovalRouterTest.php`, `tests/Feature/SubmitTrainingRecordTest.php`

**Interfaces:**
- Consumes: `Employee`, `Section`, `Division`, `ApprovalLevel`, `TrainingRecord`, `TrainingStatus`
- Produces:
  - `ApprovalRouter::firstLevelFor(Employee $employee): ?ApprovalLevel`
  - `ApprovalRouter::levelAfter(ApprovalLevel $level, Employee $employee): ?ApprovalLevel`
  - `ApprovalRouter::approverFor(ApprovalLevel $level, Employee $employee): ?Employee`
  - `SubmitTrainingRecord::handle(Employee $employee, array $attributes, User $submittedBy): TrainingRecord`

Tatlong sangay ang routing, at ito ang pinakamadaling masira sa buong sistema:

1. May head ang section → doon magsisimula.
2. Walang head ang section (25 sa 28) → laktawan, diretso sa division head.
3. Walang head kahit saan, **o ang empleyado mismo ang head** → walang makakapag-apruba. Nananatiling `pending` na walang `current_level`, at lalabas sa listahan ng HR.

- [ ] **Step 1: Isulat ang failing test para sa router**

Gumawa ng `tests/Feature/ApprovalRouterTest.php`:

```php
<?php

use App\Enums\ApprovalLevel;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Workflow\ApprovalRouter;

test('it starts at the section head when the section has one', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBe(ApprovalLevel::SectionHead);
});

test('it skips to the division head when the section has no head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create(['section_head_employee_id' => null]);
    $divisionHead = Employee::factory()->for($section)->create();
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBe(ApprovalLevel::DivisionHead);
});

test('it returns nothing when neither level has a head', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBeNull();
});

test('an employee never approves their own record', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    expect(app(ApprovalRouter::class)->firstLevelFor($head))->toBeNull();
});

test('a section head submitting their own record skips to the division head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $sectionHead = Employee::factory()->for($section)->create();
    $divisionHead = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $sectionHead->id]);
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    expect(app(ApprovalRouter::class)->firstLevelFor($sectionHead))->toBe(ApprovalLevel::DivisionHead);
});

test('after the section head comes the division head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $divisionHead = Employee::factory()->for($section)->create();
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->levelAfter(ApprovalLevel::SectionHead, $employee))
        ->toBe(ApprovalLevel::DivisionHead);
});

test('after the section head comes nothing when the division has no head', function () {
    $section = Section::factory()->create();
    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->levelAfter(ApprovalLevel::SectionHead, $employee))->toBeNull();
});

test('the division head is the last level', function () {
    $employee = Employee::factory()->create();

    expect(app(ApprovalRouter::class)->levelAfter(ApprovalLevel::DivisionHead, $employee))->toBeNull();
});

test('an inactive head cannot approve', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->inactive()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();

    expect(app(ApprovalRouter::class)->firstLevelFor($employee))->toBeNull();
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/ApprovalRouterTest.php
```

Inaasahan: FAIL — `Target class [App\Workflow\ApprovalRouter] does not exist`.

- [ ] **Step 3: Isulat ang router**

```bash
php artisan make:class Workflow/ApprovalRouter --no-interaction
```

```php
<?php

namespace App\Workflow;

use App\Enums\ApprovalLevel;
use App\Models\Employee;

class ApprovalRouter
{
    /**
     * The level that should act first on a record for this employee.
     *
     * Null means nobody can approve it: neither the section nor the
     * division has an available head. The record stays pending and
     * surfaces on the HR "no approver" list.
     */
    public function firstLevelFor(Employee $employee): ?ApprovalLevel
    {
        if ($this->approverFor(ApprovalLevel::SectionHead, $employee) instanceof Employee) {
            return ApprovalLevel::SectionHead;
        }

        if ($this->approverFor(ApprovalLevel::DivisionHead, $employee) instanceof Employee) {
            return ApprovalLevel::DivisionHead;
        }

        return null;
    }

    /**
     * The level that follows the given one.
     *
     * Null means the record is fully approved. This differs from
     * firstLevelFor() returning null, which means unroutable — the two
     * are told apart by the caller, not by this value.
     */
    public function levelAfter(ApprovalLevel $level, Employee $employee): ?ApprovalLevel
    {
        if ($level === ApprovalLevel::DivisionHead) {
            return null;
        }

        return $this->approverFor(ApprovalLevel::DivisionHead, $employee) instanceof Employee
            ? ApprovalLevel::DivisionHead
            : null;
    }

    /**
     * The employee designated to decide at the given level, if there is one.
     *
     * Nobody may approve their own record, and an inactive head does not count.
     */
    public function approverFor(ApprovalLevel $level, Employee $employee): ?Employee
    {
        $headId = match ($level) {
            ApprovalLevel::SectionHead => $employee->section?->section_head_employee_id,
            ApprovalLevel::DivisionHead => $employee->division?->division_head_employee_id,
        };

        if ($headId === null || $headId === $employee->getKey()) {
            return null;
        }

        return Employee::query()->active()->find($headId);
    }
}
```

- [ ] **Step 4: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/ApprovalRouterTest.php
```

Inaasahan: PASS, siyam na test.

- [ ] **Step 5: Isulat ang failing test para sa pagsu-submit**

Gumawa ng `tests/Feature/SubmitTrainingRecordTest.php`:

```php
<?php

use App\Actions\Training\SubmitTrainingRecord;
use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\User;

function submissionAttributes(): array
{
    return [
        'title' => 'Records Management Seminar',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
        'hours' => 24,
        'ld_type' => LdType::Technical,
        'conducted_by' => 'Civil Service Commission',
        'location' => 'Manila',
        'registration_fee' => 1500,
        'tev' => 2000,
    ];
}

test('a submitted record waits for the section head', function () {
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();
    $head = Employee::factory()->for($section)->create();
    $section->update(['section_head_employee_id' => $head->id]);

    $employee = Employee::factory()->for($section)->create();
    $user = User::factory()->employee()->create();

    $record = app(SubmitTrainingRecord::class)->handle($employee, submissionAttributes(), $user);

    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBe(ApprovalLevel::SectionHead)
        ->and($record->submitted_by)->toBe($user->id)
        ->and($record->employee_id)->toBe($employee->id);
});

test('a record with no available head is unroutable but still saved', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();
    $user = User::factory()->employee()->create();

    $record = app(SubmitTrainingRecord::class)->handle($employee, submissionAttributes(), $user);

    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBeNull()
        ->and(App\Models\TrainingRecord::unroutable()->count())->toBe(1);
});

test('hr may submit on behalf of an employee', function () {
    $employee = Employee::factory()->create();
    $hr = User::factory()->hr()->create();

    $record = app(SubmitTrainingRecord::class)->handle($employee, submissionAttributes(), $hr);

    expect($record->employee_id)->toBe($employee->id)
        ->and($record->submitted_by)->toBe($hr->id);
});
```

- [ ] **Step 6: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/SubmitTrainingRecordTest.php
```

Inaasahan: FAIL — wala pa ang action.

- [ ] **Step 7: Isulat ang action**

```bash
php artisan make:class Actions/Training/SubmitTrainingRecord --no-interaction
```

```php
<?php

namespace App\Actions\Training;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Workflow\ApprovalRouter;

class SubmitTrainingRecord
{
    public function __construct(private readonly ApprovalRouter $router) {}

    /**
     * Record a training an employee attended and start its approval.
     *
     * When nobody can approve it the record is still saved, pending and
     * without a level, so HR can see it and designate a head.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Employee $employee, array $attributes, User $submittedBy): TrainingRecord
    {
        return TrainingRecord::create([
            ...$attributes,
            'employee_id' => $employee->getKey(),
            'submitted_by' => $submittedBy->getKey(),
            'status' => TrainingStatus::Pending,
            'current_level' => $this->router->firstLevelFor($employee),
        ]);
    }
}
```

- [ ] **Step 8: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/SubmitTrainingRecordTest.php
```

Inaasahan: PASS, tatlong test.

- [ ] **Step 9: Commit message**

```
feat: route training submissions to the right approver
```

---

## Task 8: Pag-apruba at pagtanggi

**Files:**
- Create: `app/Actions/Training/DecideOnTrainingRecord.php`, `app/Policies/TrainingRecordPolicy.php`
- Test: `tests/Feature/DecideOnTrainingRecordTest.php`

**Interfaces:**
- Consumes: `ApprovalRouter`, `TrainingRecord`, `TrainingApproval`, `ApprovalDecision`
- Produces:
  - `DecideOnTrainingRecord::handle(TrainingRecord $record, User $approver, ApprovalDecision $decision, ?string $remarks = null): TrainingRecord`
  - `TrainingRecordPolicy` na may `view`, `create`, `decide`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/DecideOnTrainingRecordTest.php`:

```php
<?php

use App\Actions\Training\DecideOnTrainingRecord;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;

/**
 * A section with both heads designated, and one ordinary employee in it.
 *
 * @return array{employee: Employee, sectionHead: Employee, divisionHead: Employee}
 */
function staffedSection(): array
{
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $sectionHead = Employee::factory()->for($section)->create(['user_id' => User::factory()->sectionHead()]);
    $divisionHead = Employee::factory()->for($section)->create(['user_id' => User::factory()->divisionHead()]);

    $section->update(['section_head_employee_id' => $sectionHead->id]);
    $division->update(['division_head_employee_id' => $divisionHead->id]);

    return [
        'employee' => Employee::factory()->for($section)->create(),
        'sectionHead' => $sectionHead,
        'divisionHead' => $divisionHead,
    ];
}

test('section head approval advances the record to the division head', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $record = app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Approved, 'Endorsed.');

    expect($record->status)->toBe(TrainingStatus::Pending)
        ->and($record->current_level)->toBe(ApprovalLevel::DivisionHead)
        ->and($record->approvals)->toHaveCount(1);
});

test('division head approval completes the record', function () {
    ['employee' => $employee, 'divisionHead' => $divisionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::DivisionHead]);

    $record = app(DecideOnTrainingRecord::class)
        ->handle($record, $divisionHead->user, ApprovalDecision::Approved);

    expect($record->status)->toBe(TrainingStatus::Approved)
        ->and($record->current_level)->toBeNull();
});

test('both approvals are kept in the trail', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead, 'divisionHead' => $divisionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);
    $action = app(DecideOnTrainingRecord::class);

    $record = $action->handle($record, $sectionHead->user, ApprovalDecision::Approved);
    $record = $action->handle($record, $divisionHead->user, ApprovalDecision::Approved);

    expect($record->approvals)->toHaveCount(2)
        ->and($record->approvals->pluck('level')->all())
        ->toBe([ApprovalLevel::SectionHead, ApprovalLevel::DivisionHead]);
});

test('a rejection ends the record and keeps the reason', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $record = app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Rejected, 'Outside the training plan.');

    expect($record->status)->toBe(TrainingStatus::Rejected)
        ->and($record->current_level)->toBeNull()
        ->and($record->rejection_reason)->toBe('Outside the training plan.');
});

test('an already decided record cannot be decided again', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->approved()->create();

    expect(fn () => app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Approved))
        ->toThrow(InvalidArgumentException::class);
});

test('an unroutable record cannot be decided', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => null]);

    expect(fn () => app(DecideOnTrainingRecord::class)
        ->handle($record, $sectionHead->user, ApprovalDecision::Approved))
        ->toThrow(InvalidArgumentException::class);
});

test('only the designated head at the current level may decide', function () {
    ['employee' => $employee, 'sectionHead' => $sectionHead, 'divisionHead' => $divisionHead] = staffedSection();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    expect($sectionHead->user->can('decide', $record))->toBeTrue()
        ->and($divisionHead->user->can('decide', $record))->toBeFalse()
        ->and(User::factory()->hr()->create()->can('decide', $record))->toBeFalse();
});
```

Ang huling test ang nagtatakda ng desisyong walang override ang HR: hindi sila makakapag-apruba kahit nakikita nila ang lahat.

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/DecideOnTrainingRecordTest.php
```

Inaasahan: FAIL — wala pa ang action.

- [ ] **Step 3: Isulat ang action**

```bash
php artisan make:class Actions/Training/DecideOnTrainingRecord --no-interaction
```

```php
<?php

namespace App\Actions\Training;

use App\Enums\ApprovalDecision;
use App\Enums\TrainingStatus;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Workflow\ApprovalRouter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DecideOnTrainingRecord
{
    public function __construct(private readonly ApprovalRouter $router) {}

    /**
     * Record one approval decision and move the record along.
     *
     * Approval at the section level advances to the division head, unless
     * the division has no head, in which case the record is complete.
     * Rejection ends it at whatever level rejected.
     *
     * @throws InvalidArgumentException when the record is not awaiting a decision
     */
    public function handle(
        TrainingRecord $record,
        User $approver,
        ApprovalDecision $decision,
        ?string $remarks = null,
    ): TrainingRecord {
        if ($record->status !== TrainingStatus::Pending) {
            throw new InvalidArgumentException('Only a pending record can be decided on.');
        }

        $level = $record->current_level;

        if ($level === null) {
            throw new InvalidArgumentException('This record has no approver assigned.');
        }

        DB::transaction(function () use ($record, $approver, $decision, $remarks, $level): void {
            $record->approvals()->create([
                'level' => $level,
                'approver_user_id' => $approver->getKey(),
                'decision' => $decision,
                'remarks' => $remarks,
                'decided_at' => now(),
            ]);

            if ($decision === ApprovalDecision::Rejected) {
                $record->update([
                    'status' => TrainingStatus::Rejected,
                    'current_level' => null,
                    'rejection_reason' => $remarks,
                ]);

                return;
            }

            $next = $this->router->levelAfter($level, $record->employee);

            $record->update([
                'status' => $next === null ? TrainingStatus::Approved : TrainingStatus::Pending,
                'current_level' => $next,
            ]);
        });

        return $record->refresh()->load('approvals');
    }
}
```

- [ ] **Step 4: Isulat ang policy**

```bash
php artisan make:policy TrainingRecordPolicy --model=TrainingRecord --no-interaction
```

Palitan ang buong laman:

```php
<?php

namespace App\Policies;

use App\Enums\TrainingStatus;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Workflow\ApprovalRouter;

class TrainingRecordPolicy
{
    public function __construct(private readonly ApprovalRouter $router) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TrainingRecord $record): bool
    {
        return Employee::query()->visibleTo($user)->whereKey($record->employee_id)->exists();
    }

    /**
     * Anyone may record a training. Whose record it is decides the rest.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Submitting for somebody else is an HR and admin matter.
     */
    public function createFor(User $user, Employee $employee): bool
    {
        return $user->isAdminOrHr() || $user->employee?->is($employee) === true;
    }

    /**
     * Only the designated head at the record's current level may decide.
     *
     * HR and admin see everything but never decide — every record goes
     * through both steps.
     */
    public function decide(User $user, TrainingRecord $record): bool
    {
        if ($record->status !== TrainingStatus::Pending || $record->current_level === null) {
            return false;
        }

        $employee = $user->employee;

        if ($employee === null) {
            return false;
        }

        $approver = $this->router->approverFor($record->current_level, $record->employee);

        return $approver instanceof Employee && $approver->is($employee);
    }
}
```

- [ ] **Step 5: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/DecideOnTrainingRecordTest.php
```

Inaasahan: PASS, pitong test.

- [ ] **Step 6: Patakbuhin ang buong suite**

```bash
php artisan test --compact
```

Inaasahan: PASS lahat. Buo na ang backend; UI na ang natitira.

- [ ] **Step 7: Commit message**

```
feat: approve and reject training records
```

---
## Task 9: Training screens — sarili, pagsu-submit, at detalye

**Files:**
- Create: `resources/views/pages/trainings/⚡mine.blade.php`, `⚡form.blade.php`, `⚡show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TrainingScreensTest.php`

**Interfaces:**
- Consumes: `SubmitTrainingRecord`, `TrainingRecord`, `TrainingRecordPolicy`, `LdType`
- Produces: mga route na `trainings.mine`, `trainings.create`, `trainings.show`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/TrainingScreensTest.php`:

```php
<?php

use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Models\Employee;
use App\Models\TrainingApproval;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

function actingAsEmployee(): Employee
{
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    test()->actingAs($user);

    return $employee;
}

test('an employee can record a training for themselves', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an other type must carry its own text', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Annual Convention')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 8)
        ->set('ld_type', LdType::Other->value)
        ->set('ld_type_other', '')
        ->set('conducted_by', 'PHA')
        ->call('save')
        ->assertHasErrors('ld_type_other');
});

test('the end date cannot come before the start date', function () {
    $employee = actingAsEmployee();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-04')
        ->set('date_end', '2026-03-02')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasErrors('date_end');
});

test('hr can record a training for somebody else', function () {
    $this->actingAs(User::factory()->hr()->create());
    $employee = Employee::factory()->create();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $employee->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertHasNoErrors();

    expect(TrainingRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

test('an employee cannot record a training for somebody else', function () {
    actingAsEmployee();
    $other = Employee::factory()->create();

    Livewire::test('pages::trainings.form')
        ->set('employeeId', $other->id)
        ->set('title', 'Records Management Seminar')
        ->set('date_start', '2026-03-02')
        ->set('date_end', '2026-03-04')
        ->set('hours', 24)
        ->set('ld_type', LdType::Technical->value)
        ->set('conducted_by', 'Civil Service Commission')
        ->call('save')
        ->assertForbidden();
});

test('my trainings lists only my own records', function () {
    $employee = actingAsEmployee();
    TrainingRecord::factory()->for($employee)->create(['title' => 'Mine Seminar']);
    TrainingRecord::factory()->create(['title' => 'Somebody Else Seminar']);

    Livewire::test('pages::trainings.mine')
        ->assertSee('Mine Seminar')
        ->assertDontSee('Somebody Else Seminar');
});

test('the detail page shows the approval trail', function () {
    $employee = actingAsEmployee();
    $record = TrainingRecord::factory()->for($employee)->create();
    TrainingApproval::factory()->for($record)->create([
        'level' => ApprovalLevel::SectionHead,
        'remarks' => 'Endorsed by the section.',
    ]);

    $this->get(route('trainings.show', $record))
        ->assertOk()
        ->assertSee('Endorsed by the section.');
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/TrainingScreensTest.php
```

Inaasahan: FAIL — walang component na `pages::trainings.form`.

- [ ] **Step 3: Gawin ang submission form**

Gumawa ng `resources/views/pages/trainings/⚡form.blade.php`:

```blade
<?php

use App\Actions\Training\SubmitTrainingRecord;
use App\Enums\LdType;
use App\Models\Employee;
use App\Models\TrainingRecord;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Record a training')] class extends Component {
    public ?int $employeeId = null;

    public string $title = '';

    public string $date_start = '';

    public string $date_end = '';

    public ?int $hours = null;

    public string $ld_type = '';

    public string $ld_type_other = '';

    public string $conducted_by = '';

    public string $location = '';

    public ?float $expenses = null;

    public ?float $registration_fee = null;

    public ?float $tev = null;

    public ?float $cpd_units = null;

    public function mount(): void
    {
        $this->employeeId = auth()->user()->employee?->getKey();
    }

    /**
     * Only HR and admin choose somebody else; everyone else records their own.
     */
    #[Computed]
    public function canChooseEmployee(): bool
    {
        return auth()->user()->isAdminOrHr();
    }

    /**
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employees(): Collection
    {
        return Employee::query()->active()->orderBy('last_name')->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'employeeId' => ['required', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:255'],
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'hours' => ['required', 'integer', 'min:1', 'max:9999'],
            'ld_type' => ['required', Rule::enum(LdType::class)],
            'ld_type_other' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->ld_type === LdType::Other->value)],
            'conducted_by' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'expenses' => ['nullable', 'numeric', 'min:0'],
            'registration_fee' => ['nullable', 'numeric', 'min:0'],
            'tev' => ['nullable', 'numeric', 'min:0'],
            'cpd_units' => ['nullable', 'numeric', 'min:0'],
        ]);

        $employee = Employee::findOrFail($validated['employeeId']);

        $this->authorize('createFor', [TrainingRecord::class, $employee]);

        $record = app(SubmitTrainingRecord::class)->handle(
            $employee,
            [
                'title' => $validated['title'],
                'date_start' => $validated['date_start'],
                'date_end' => $validated['date_end'],
                'hours' => $validated['hours'],
                'ld_type' => $validated['ld_type'],
                'ld_type_other' => $this->ld_type === LdType::Other->value ? $validated['ld_type_other'] : null,
                'conducted_by' => $validated['conducted_by'],
                'location' => $validated['location'] ?: null,
                'expenses' => $validated['expenses'],
                'registration_fee' => $validated['registration_fee'],
                'tev' => $validated['tev'],
                'cpd_units' => $validated['cpd_units'],
            ],
            auth()->user(),
        );

        Flux::toast(variant: 'success', text: __('Training submitted for approval.'));

        $this->redirectRoute('trainings.show', $record, navigate: true);
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Record a training') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="space-y-6">
            @if ($this->canChooseEmployee)
                <flux:select wire:model="employeeId" :label="__('Employee')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach ($this->employees as $employee)
                        <flux:select.option :value="$employee->id">
                            {{ $employee->full_name }} — {{ $employee->employee_number }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="title" :label="__('Title of learning and development intervention')" required />

            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="date_start" :label="__('From')" type="date" required />
                <flux:input wire:model="date_end" :label="__('To')" type="date" required />
                <flux:input wire:model="hours" :label="__('Number of hours')" type="number" min="1" required />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model.live="ld_type" :label="__('Type of LD')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach (App\Enums\LdType::cases() as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($ld_type === App\Enums\LdType::Other->value)
                    <flux:input wire:model="ld_type_other" :label="__('Specify the type')"
                        :placeholder="__('Soft Skill, Workshop, Convention')" required />
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="conducted_by" :label="__('Conducted or sponsored by')" required />
                <flux:input wire:model="location" :label="__('Location')" />
            </div>

            <flux:separator :text="__('Costs')" />

            <div class="grid gap-4 md:grid-cols-4">
                <flux:input wire:model="registration_fee" :label="__('Registration fee')" type="number" step="0.01" min="0" />
                <flux:input wire:model="tev" :label="__('Travel expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="expenses" :label="__('Other expenses')" type="number" step="0.01" min="0" />
                <flux:input wire:model="cpd_units" :label="__('CPD units')" type="number" step="0.1" min="0" />
            </div>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Submit for approval') }}</flux:button>
                <flux:button :href="route('trainings.mine')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
```

- [ ] **Step 4: Gawin ang "my trainings" page**

Gumawa ng `resources/views/pages/trainings/⚡mine.blade.php`:

```blade
<?php

use App\Models\TrainingRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My trainings')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, TrainingRecord>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return TrainingRecord::query()
            ->where('employee_id', auth()->user()->employee?->getKey())
            ->orderByDesc('date_end')
            ->paginate(20);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('My trainings') }}</flux:heading>

        <flux:button :href="route('trainings.create')" variant="primary" wire:navigate>
            {{ __('Record a training') }}
        </flux:button>
    </div>

    @if (auth()->user()->employee === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Your account is not linked to an employee record yet. Ask HR to link it before recording a training.') }}
        </flux:callout>
    @endif

    <flux:table :paginate="$this->records">
        <flux:table.columns>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column>{{ __('Type of LD') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->records as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $record)" wire:navigate>
                            {{ $record->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>{{ $record->ld_type_label }}</flux:table.cell>
                    <flux:table.cell>
                        <x-training-status :record="$record" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No trainings recorded yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 5: Gawin ang status badge component**

Ginagamit ito ng tatlong page, kaya isang Blade component. Gumawa ng `resources/views/components/training-status.blade.php`:

```blade
@props(['record'])

@php($status = $record->status)

@if ($status === App\Enums\TrainingStatus::Approved)
    <flux:badge color="green">{{ __('Approved') }}</flux:badge>
@elseif ($status === App\Enums\TrainingStatus::Rejected)
    <flux:badge color="red">{{ __('Rejected') }}</flux:badge>
@elseif ($record->current_level === null)
    <flux:badge color="amber">{{ __('No approver') }}</flux:badge>
@else
    <flux:badge color="zinc">
        {{ __('Waiting for :level', ['level' => $record->current_level->label()]) }}
    </flux:badge>
@endif
```

Ang "No approver" ang nakikitang anyo ng nakabinbing record na walang head — 25 sa 28 na section ang nanganganib dito, kaya hindi ito maaaring maging tahimik.

- [ ] **Step 6: Gawin ang detail page**

Gumawa ng `resources/views/pages/trainings/⚡show.blade.php`:

```blade
<?php

use App\Models\TrainingRecord;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Training record')] class extends Component {
    public TrainingRecord $record;

    public function mount(TrainingRecord $record): void
    {
        $this->authorize('view', $record);

        $this->record = $record->load(['employee.section.division', 'submittedBy', 'approvals.approver']);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">{{ $record->title }}</flux:heading>
            <flux:text>
                {{ $record->employee->full_name }} · {{ $record->employee->section?->name }}
            </flux:text>
        </div>

        <x-training-status :record="$record" />
    </div>

    <flux:card class="grid gap-4 md:grid-cols-3">
        <div>
            <flux:text size="sm">{{ __('Inclusive dates') }}</flux:text>
            <flux:heading size="lg">
                {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
            </flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Number of hours') }}</flux:text>
            <flux:heading size="lg">{{ $record->hours }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Type of LD') }}</flux:text>
            <flux:heading size="lg">{{ $record->ld_type_label }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Conducted by') }}</flux:text>
            <flux:heading size="lg">{{ $record->conducted_by }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Location') }}</flux:text>
            <flux:heading size="lg">{{ $record->location ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('CPD units') }}</flux:text>
            <flux:heading size="lg">{{ $record->cpd_units ?? '—' }}</flux:heading>
        </div>
    </flux:card>

    <flux:card class="grid gap-4 md:grid-cols-4">
        <div>
            <flux:text size="sm">{{ __('Registration fee') }}</flux:text>
            <flux:heading size="lg">{{ $record->registration_fee ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Travel expenses') }}</flux:text>
            <flux:heading size="lg">{{ $record->tev ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Other expenses') }}</flux:text>
            <flux:heading size="lg">{{ $record->expenses ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Submitted by') }}</flux:text>
            <flux:heading size="lg">{{ $record->submittedBy->name }}</flux:heading>
        </div>
    </flux:card>

    @if ($record->rejection_reason)
        <flux:callout variant="danger" icon="x-circle" :heading="__('Rejected')">
            {{ $record->rejection_reason }}
        </flux:callout>
    @endif

    <flux:heading size="lg">{{ __('Approval trail') }}</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Level') }}</flux:table.column>
            <flux:table.column>{{ __('Decision') }}</flux:table.column>
            <flux:table.column>{{ __('By') }}</flux:table.column>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Remarks') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($record->approvals as $approval)
                <flux:table.row :key="$approval->id">
                    <flux:table.cell>{{ $approval->level->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->decision->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->approver->name }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->decided_at->format('d M Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $approval->remarks ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nobody has acted on this yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 7: Irehistro ang mga route**

Sa `routes/web.php`, sa loob ng auth group. Nauuna ang `create` kaysa `{record}`:

```php
Route::livewire('trainings/mine', 'pages::trainings.mine')->name('trainings.mine');
Route::livewire('trainings/create', 'pages::trainings.form')->name('trainings.create');
Route::livewire('trainings/{record}', 'pages::trainings.show')->name('trainings.show');
```

- [ ] **Step 8: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/TrainingScreensTest.php
```

Inaasahan: PASS, pitong test.

- [ ] **Step 9: Commit message**

```
feat: add training submission and detail screens
```

---

## Task 10: Approvals queue

**Files:**
- Create: `resources/views/pages/⚡approvals.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ApprovalScreenTest.php`

**Interfaces:**
- Consumes: `DecideOnTrainingRecord`, `ApprovalRouter`, `TrainingRecordPolicy`
- Produces: ang route na `approvals`

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/ApprovalScreenTest.php`:

```php
<?php

use App\Enums\ApprovalLevel;
use App\Enums\TrainingStatus;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array{employee: Employee, sectionHeadUser: User}
 */
function sectionWithHead(): array
{
    $division = Division::factory()->create();
    $section = Section::factory()->for($division)->create();

    $headUser = User::factory()->sectionHead()->create();
    $head = Employee::factory()->for($section)->create(['user_id' => $headUser->id]);
    $section->update(['section_head_employee_id' => $head->id]);

    return [
        'employee' => Employee::factory()->for($section)->create(),
        'sectionHeadUser' => $headUser,
    ];
}

test('the queue shows only records waiting on me', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();

    TrainingRecord::factory()->for($employee)->create([
        'title' => 'Mine To Decide',
        'current_level' => ApprovalLevel::SectionHead,
    ]);
    TrainingRecord::factory()->create(['title' => 'Someone Elses Queue']);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->assertSee('Mine To Decide')
        ->assertDontSee('Someone Elses Queue');
});

test('the section head can approve from the queue', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->set('remarks', 'Endorsed.')
        ->call('approve', $record->id)
        ->assertHasNoErrors();

    expect($record->fresh()->current_level)->toBe(ApprovalLevel::DivisionHead);
});

test('the section head can reject with a reason', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->set('remarks', 'Outside the training plan.')
        ->call('reject', $record->id)
        ->assertHasNoErrors();

    expect($record->fresh()->status)->toBe(TrainingStatus::Rejected)
        ->and($record->fresh()->rejection_reason)->toBe('Outside the training plan.');
});

test('rejecting without a reason is refused', function () {
    ['employee' => $employee, 'sectionHeadUser' => $headUser] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs($headUser);

    Livewire::test('pages::approvals')
        ->set('remarks', '')
        ->call('reject', $record->id)
        ->assertHasErrors('remarks');

    expect($record->fresh()->status)->toBe(TrainingStatus::Pending);
});

test('somebody who is not the current approver cannot decide', function () {
    ['employee' => $employee] = sectionWithHead();
    $record = TrainingRecord::factory()->for($employee)->create(['current_level' => ApprovalLevel::SectionHead]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::approvals')
        ->set('remarks', 'Approving anyway.')
        ->call('approve', $record->id)
        ->assertForbidden();
});

test('hr sees the records that have no approver at all', function () {
    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $employee = Employee::factory()->for($section)->create();
    TrainingRecord::factory()->for($employee)->create([
        'title' => 'Stuck Seminar',
        'current_level' => null,
    ]);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::approvals')->assertSee('Stuck Seminar');
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/ApprovalScreenTest.php
```

Inaasahan: FAIL — walang component na `pages::approvals`.

- [ ] **Step 3: Gawin ang page**

Gumawa ng `resources/views/pages/⚡approvals.blade.php`:

```blade
<?php

use App\Actions\Training\DecideOnTrainingRecord;
use App\Enums\ApprovalDecision;
use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Workflow\ApprovalRouter;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Approvals')] class extends Component {
    public string $remarks = '';

    /**
     * Records this user is the designated approver for, right now.
     *
     * The level is filtered in SQL; the designation itself is checked in
     * PHP through the router, so there is exactly one definition of who
     * approves what.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function queue(): Collection
    {
        $user = auth()->user();
        $employee = $user->employee;

        if ($employee === null) {
            return collect();
        }

        $router = app(ApprovalRouter::class);

        return TrainingRecord::query()
            ->pending()
            ->whereNotNull('current_level')
            ->with(['employee.section.division'])
            ->orderBy('created_at')
            ->get()
            ->filter(function (TrainingRecord $record) use ($router, $employee): bool {
                $approver = $router->approverFor($record->current_level, $record->employee);

                return $approver instanceof Employee && $approver->is($employee);
            })
            ->values();
    }

    /**
     * Pending records nobody can approve. HR and admin only.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function unroutable(): Collection
    {
        if (! auth()->user()->isAdminOrHr()) {
            return collect();
        }

        return TrainingRecord::query()
            ->unroutable()
            ->with(['employee.section.division'])
            ->orderBy('created_at')
            ->get();
    }

    public function approve(int $recordId): void
    {
        $this->decide($recordId, ApprovalDecision::Approved);
    }

    public function reject(int $recordId): void
    {
        $this->validate(
            ['remarks' => ['required', 'string', 'max:1000']],
            ['remarks.required' => __('A reason is required when rejecting.')],
        );

        $this->decide($recordId, ApprovalDecision::Rejected);
    }

    private function decide(int $recordId, ApprovalDecision $decision): void
    {
        $record = TrainingRecord::findOrFail($recordId);

        $this->authorize('decide', $record);

        app(DecideOnTrainingRecord::class)->handle(
            $record,
            auth()->user(),
            $decision,
            $this->remarks !== '' ? $this->remarks : null,
        );

        $this->reset('remarks');
        unset($this->queue, $this->unroutable);

        Flux::toast(variant: 'success', text: __('Decision recorded.'));
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Approvals') }}</flux:heading>

    <flux:card>
        <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2"
            :description="__('Optional when approving, required when rejecting.')" />
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Employee') }}</flux:table.column>
            <flux:table.column>{{ __('Training') }}</flux:table.column>
            <flux:table.column>{{ __('Inclusive dates') }}</flux:table.column>
            <flux:table.column>{{ __('Hours') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->queue as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>{{ $record->employee->full_name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $record)" wire:navigate>
                            {{ $record->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="primary" wire:click="approve({{ $record->id }})">
                                {{ __('Approve') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="reject({{ $record->id }})">
                                {{ __('Reject') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('Nothing is waiting for you.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->unroutable->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No approver assigned')">
            {{ __('These records cannot move because neither the section nor the division has a head designated. Set a head under Setup.') }}
        </flux:callout>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Employee') }}</flux:table.column>
                <flux:table.column>{{ __('Training') }}</flux:table.column>
                <flux:table.column>{{ __('Section') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->unroutable as $record)
                    <flux:table.row :key="$record->id">
                        <flux:table.cell>{{ $record->employee->full_name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('trainings.show', $record)" wire:navigate>
                                {{ $record->title }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $record->employee->section?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
```

Ang pila ay sinasala sa PHP sa pamamagitan ng router, hindi sa SQL. Sinadya ito: iisa lang ang kahulugan ng "sino ang nag-a-apruba", at ang bilang ng nakabinbing record ay maliit. Kung lumaki ito, doon pa lang mag-iisip ng index.

- [ ] **Step 4: Irehistro ang route**

```php
Route::livewire('approvals', 'pages::approvals')->name('approvals');
```

- [ ] **Step 5: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/ApprovalScreenTest.php
```

Inaasahan: PASS, anim na test.

- [ ] **Step 6: Commit message**

```
feat: add the approvals queue
```

---
## Task 11: Employee screens

**Files:**
- Create: `resources/views/pages/employees/⚡index.blade.php`, `⚡show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/EmployeeScreensTest.php`

**Interfaces:**
- Consumes: `Employee`, `EmployeePolicy`, `TrainingRecord`
- Produces: mga route na `employees.index`, `employees.show`

Walang employee form sa Phase 1 — ang `ldi:import-employees` ang pinagmumulan ng datos. Ang pag-edit ay tatalakayin kapag may aktwal nang pangangailangan.

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/EmployeeScreensTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TrainingRecord;
use App\Models\User;
use Livewire\Livewire;

test('hr sees every active employee', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['last_name' => 'Bonifacio']);
    Employee::factory()->inactive()->create(['last_name' => 'Aguinaldo']);

    Livewire::test('pages::employees.index')
        ->assertSee('Bonifacio')
        ->assertDontSee('Aguinaldo');
});

test('a section head sees only their own section', function () {
    $division = Division::factory()->create();
    $ownSection = Section::factory()->for($division)->create();

    $headUser = User::factory()->sectionHead()->create();
    Employee::factory()->for($ownSection)->create(['user_id' => $headUser->id, 'last_name' => 'Mabini']);
    Employee::factory()->for(Section::factory()->create())->create(['last_name' => 'Jacinto']);

    $this->actingAs($headUser);

    Livewire::test('pages::employees.index')
        ->assertSee('Mabini')
        ->assertDontSee('Jacinto');
});

test('search narrows by name and employee number', function () {
    $this->actingAs(User::factory()->hr()->create());

    Employee::factory()->create(['last_name' => 'Bonifacio', 'employee_number' => 'EMP-111']);
    Employee::factory()->create(['last_name' => 'Jacinto', 'employee_number' => 'EMP-222']);

    Livewire::test('pages::employees.index')
        ->set('search', 'EMP-111')
        ->assertSee('Bonifacio')
        ->assertDontSee('Jacinto');
});

test('the profile lists the training history', function () {
    $this->actingAs(User::factory()->hr()->create());

    $employee = Employee::factory()->create();
    TrainingRecord::factory()->for($employee)->approved()->create(['title' => 'Records Management Seminar']);

    $this->get(route('employees.show', $employee))
        ->assertOk()
        ->assertSee('Records Management Seminar');
});

test('an employee cannot open somebody elses profile', function () {
    $user = User::factory()->employee()->create();
    Employee::factory()->create(['user_id' => $user->id]);
    $other = Employee::factory()->create();

    $this->actingAs($user);

    $this->get(route('employees.show', $other))->assertForbidden();
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSectionId(): void
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
                        ->orWhere('employee_number', 'like', $term);
                });
            })
            ->when($this->sectionId !== null, fn (Builder $query) => $query->where('section_id', $this->sectionId))
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
    <flux:heading size="xl">{{ __('Employees') }}</flux:heading>

    <div class="flex flex-col gap-4 md:flex-row">
        <flux:input wire:model.live.debounce.300ms="search"
            :placeholder="__('Search name or employee number')" class="flex-1" />

        <flux:select wire:model.live="sectionId" class="md:w-64">
            <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
            @foreach ($this->sections as $section)
                <flux:select.option :value="$section->id">{{ $section->name }}</flux:select.option>
            @endforeach
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
                    <flux:table.cell>{{ $employee->employee_number }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('employees.show', $employee)" wire:navigate>
                            {{ $employee->full_name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $employee->position?->title ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->section?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $employee->section?->division?->name ?? '—' }}</flux:table.cell>
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

- [ ] **Step 4: Gawin ang profile page**

Gumawa ng `resources/views/pages/employees/⚡show.blade.php`:

```blade
<?php

use App\Models\Employee;
use App\Models\TrainingRecord;
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
     * The whole training history, newest first. This is what PDS page 4
     * will be built from in Phase 4.
     *
     * @return Collection<int, TrainingRecord>
     */
    #[Computed]
    public function records(): Collection
    {
        return $this->employee->trainingRecords()->orderByDesc('date_end')->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ $employee->full_name }}</flux:heading>
        <flux:text>
            {{ $employee->position?->title ?? '—' }} · {{ $employee->section?->name ?? '—' }}
        </flux:text>
    </div>

    <flux:card class="grid gap-4 md:grid-cols-4">
        <div>
            <flux:text size="sm">{{ __('Employee no.') }}</flux:text>
            <flux:heading size="lg">{{ $employee->employee_number }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Division') }}</flux:text>
            <flux:heading size="lg">{{ $employee->section?->division?->name ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Employment status') }}</flux:text>
            <flux:heading size="lg">{{ $employee->employment_status->label() }}</flux:heading>
        </div>
        <div>
            <flux:text size="sm">{{ __('Date hired') }}</flux:text>
            <flux:heading size="lg">{{ $employee->date_hired?->format('d M Y') ?? '—' }}</flux:heading>
        </div>
    </flux:card>

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
            @forelse ($this->records as $record)
                <flux:table.row :key="$record->id">
                    <flux:table.cell>
                        <flux:link :href="route('trainings.show', $record)" wire:navigate>
                            {{ $record->title }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $record->date_start->format('d M Y') }} – {{ $record->date_end->format('d M Y') }}
                    </flux:table.cell>
                    <flux:table.cell>{{ $record->hours }}</flux:table.cell>
                    <flux:table.cell>{{ $record->ld_type_label }}</flux:table.cell>
                    <flux:table.cell>{{ $record->conducted_by }}</flux:table.cell>
                    <flux:table.cell><x-training-status :record="$record" /></flux:table.cell>
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

- [ ] **Step 5: Irehistro ang mga route**

```php
Route::livewire('employees', 'pages::employees.index')->name('employees.index');
Route::livewire('employees/{employee}', 'pages::employees.show')->name('employees.show');
```

- [ ] **Step 6: Patakbuhin ang test**

```bash
php artisan test --compact tests/Feature/EmployeeScreensTest.php
```

Inaasahan: PASS, limang test.

- [ ] **Step 7: Commit message**

```
feat: add employee list and profile screens
```

---

## Task 12: Setup screens at pagtatakda ng head

**Files:**
- Create: `resources/views/pages/setup/⚡divisions.blade.php`, `⚡sections.blade.php`, `⚡positions.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SetupScreensTest.php`

**Interfaces:**
- Consumes: `Division`, `Section`, `Position`, `Employee`, `User::isAdminOrHr()`
- Produces: mga route na `setup.divisions`, `setup.sections`, `setup.positions`

Ito ang lunas sa pinakamalaking butas sa datos: 3 lang sa 28 na section ang may head, at hangga't ganoon, hindi umuusad ang karamihan ng submission. Dito iyon naitatakda.

Walang policy class para sa reference data — isang `abort_unless(auth()->user()->isAdminOrHr(), 403)` sa `mount()` at sa bawat mutating action. Mas kaunti kaysa tatlong halos magkaparehong policy.

- [ ] **Step 1: Isulat ang failing test**

Gumawa ng `tests/Feature/SetupScreensTest.php`:

```php
<?php

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

test('hr can designate a section head', function () {
    $this->actingAs(User::factory()->hr()->create());

    $section = Section::factory()->create(['section_head_employee_id' => null]);
    $head = Employee::factory()->for($section)->create();

    Livewire::test('pages::setup.sections')
        ->call('edit', $section->id)
        ->set('sectionHeadEmployeeId', $head->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($section->fresh()->section_head_employee_id)->toBe($head->id);
});

test('hr can designate a division head', function () {
    $this->actingAs(User::factory()->hr()->create());

    $division = Division::factory()->create();
    $head = Employee::factory()->for(Section::factory()->for($division))->create();

    Livewire::test('pages::setup.divisions')
        ->call('edit', $division->id)
        ->set('divisionHeadEmployeeId', $head->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($division->fresh()->division_head_employee_id)->toBe($head->id);
});

test('hr can add a position', function () {
    $this->actingAs(User::factory()->hr()->create());

    Livewire::test('pages::setup.positions')
        ->set('title', 'Administrative Officer V')
        ->set('salaryGrade', 18)
        ->call('save')
        ->assertHasNoErrors();

    expect(Position::where('title', 'Administrative Officer V')->first()->salary_grade)->toBe(18);
});

test('a section head cannot open setup', function () {
    $this->actingAs(User::factory()->sectionHead()->create());

    $this->get(route('setup.sections'))->assertForbidden();
});

test('the section list shows which sections still have no head', function () {
    $this->actingAs(User::factory()->hr()->create());

    Section::factory()->create(['name' => 'Headless Section', 'section_head_employee_id' => null]);

    Livewire::test('pages::setup.sections')
        ->assertSee('Headless Section')
        ->assertSee('No head');
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/SetupScreensTest.php
```

Inaasahan: FAIL — walang route na `setup.sections`.

- [ ] **Step 3: Gawin ang sections page**

Gumawa ng `resources/views/pages/setup/⚡sections.blade.php`:

```blade
<?php

use App\Models\Division;
use App\Models\Employee;
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

    public ?int $sectionHeadEmployeeId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    /**
     * @return Collection<int, Section>
     */
    #[Computed]
    public function sections(): Collection
    {
        return Section::query()->with(['division', 'head'])->withCount('employees')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed]
    public function divisions(): Collection
    {
        return Division::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employees(): Collection
    {
        return Employee::query()->active()->orderBy('last_name')->get();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $section = Section::findOrFail($id);

        $this->editingId = $section->id;
        $this->divisionId = $section->division_id;
        $this->name = $section->name;
        $this->code = $section->code;
        $this->sectionHeadEmployeeId = $section->section_head_employee_id;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $validated = $this->validate([
            'divisionId' => ['required', 'exists:divisions,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sections', 'code')->ignore($this->editingId)],
            'sectionHeadEmployeeId' => ['nullable', 'exists:employees,id'],
        ]);

        Section::updateOrCreate(['id' => $this->editingId], [
            'division_id' => $validated['divisionId'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'section_head_employee_id' => $validated['sectionHeadEmployeeId'],
        ]);

        $this->resetForm();
        unset($this->sections);

        Flux::toast(variant: 'success', text: __('Section saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'divisionId', 'name', 'code', 'sectionHeadEmployeeId');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Sections') }}</flux:heading>

    <flux:callout icon="information-circle">
        {{ __('A section with no head sends its submissions straight to the division head. If neither has a head, submissions cannot move at all.') }}
    </flux:callout>

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:select wire:model="divisionId" :label="__('Division')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach ($this->divisions as $division)
                        <flux:select.option :value="$division->id">{{ $division->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="code" :label="__('Code')" required />
            </div>

            <flux:select wire:model="sectionHeadEmployeeId" :label="__('Section head')">
                <flux:select.option value="">{{ __('No head') }}</flux:select.option>
                @foreach ($this->employees as $employee)
                    <flux:select.option :value="$employee->id">{{ $employee->full_name }}</flux:select.option>
                @endforeach
            </flux:select>

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
            <flux:table.column>{{ __('Head') }}</flux:table.column>
            <flux:table.column>{{ __('Employees') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->sections as $section)
                <flux:table.row :key="$section->id">
                    <flux:table.cell>{{ $section->name }}</flux:table.cell>
                    <flux:table.cell>{{ $section->division->name }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($section->head)
                            {{ $section->head->full_name }}
                        @else
                            <flux:badge color="amber">{{ __('No head') }}</flux:badge>
                        @endif
                    </flux:table.cell>
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

- [ ] **Step 4: Gawin ang divisions page**

Gumawa ng `resources/views/pages/setup/⚡divisions.blade.php`:

```blade
<?php

use App\Models\Division;
use App\Models\Employee;
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

    public ?int $divisionHeadEmployeeId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
    }

    /**
     * @return Collection<int, Division>
     */
    #[Computed]
    public function divisions(): Collection
    {
        return Division::query()->with('head')->withCount('sections')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employees(): Collection
    {
        return Employee::query()->active()->orderBy('last_name')->get();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $division = Division::findOrFail($id);

        $this->editingId = $division->id;
        $this->name = $division->name;
        $this->code = $division->code;
        $this->divisionHeadEmployeeId = $division->division_head_employee_id;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('divisions', 'code')->ignore($this->editingId)],
            'divisionHeadEmployeeId' => ['nullable', 'exists:employees,id'],
        ]);

        Division::updateOrCreate(['id' => $this->editingId], [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'division_head_employee_id' => $validated['divisionHeadEmployeeId'],
        ]);

        $this->resetForm();
        unset($this->divisions);

        Flux::toast(variant: 'success', text: __('Division saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'code', 'divisionHeadEmployeeId');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Divisions') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="code" :label="__('Code')" required />

                <flux:select wire:model="divisionHeadEmployeeId" :label="__('Division head')">
                    <flux:select.option value="">{{ __('No head') }}</flux:select.option>
                    @foreach ($this->employees as $employee)
                        <flux:select.option :value="$employee->id">{{ $employee->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

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
            <flux:table.column>{{ __('Head') }}</flux:table.column>
            <flux:table.column>{{ __('Sections') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->divisions as $division)
                <flux:table.row :key="$division->id">
                    <flux:table.cell>{{ $division->name }}</flux:table.cell>
                    <flux:table.cell>{{ $division->code }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($division->head)
                            {{ $division->head->full_name }}
                        @else
                            <flux:badge color="amber">{{ __('No head') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $division->sections_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $division->id }})">
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No divisions yet.') }}</flux:table.cell>
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

    public string $itemNumber = '';

    public ?int $salaryGrade = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);
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
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $position = Position::findOrFail($id);

        $this->editingId = $position->id;
        $this->title = $position->title;
        $this->itemNumber = $position->item_number ?? '';
        $this->salaryGrade = $position->salary_grade;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdminOrHr(), 403);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'itemNumber' => ['nullable', 'string', 'max:50'],
            'salaryGrade' => ['nullable', 'integer', 'between:1,33'],
        ]);

        Position::updateOrCreate(['id' => $this->editingId], [
            'title' => $validated['title'],
            'item_number' => $validated['itemNumber'] ?: null,
            'salary_grade' => $validated['salaryGrade'],
        ]);

        $this->resetForm();
        unset($this->positions);

        Flux::toast(variant: 'success', text: __('Position saved.'));
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'title', 'itemNumber', 'salaryGrade');
        $this->resetValidation();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Positions') }}</flux:heading>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-4 md:flex-row md:items-end">
            <flux:input wire:model="title" :label="__('Title')" class="flex-1" required />
            <flux:input wire:model="itemNumber" :label="__('Item number')" class="md:w-48" />
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
            <flux:table.column>{{ __('Item number') }}</flux:table.column>
            <flux:table.column>{{ __('Salary grade') }}</flux:table.column>
            <flux:table.column>{{ __('Employees') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->positions as $position)
                <flux:table.row :key="$position->id">
                    <flux:table.cell>{{ $position->title }}</flux:table.cell>
                    <flux:table.cell>{{ $position->item_number ?? '—' }}</flux:table.cell>
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
                    <flux:table.cell colspan="5">{{ __('No positions yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
```

- [ ] **Step 6: Irehistro ang mga route**

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

- [ ] **Step 8: Commit message**

```
feat: add setup screens and head designation
```

---

## Task 13: Dashboard, navigation, at pagpapatunay

**Files:**
- Create: `resources/views/pages/⚡dashboard.blade.php`
- Delete: `resources/views/dashboard.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app/sidebar.blade.php`
- Test: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: lahat ng nauna
- Produces: ang route na `dashboard` ay Livewire component na

- [ ] **Step 1: Dagdagan ang umiiral na test**

Idagdag sa `tests/Feature/DashboardTest.php` (at ang mga import na `App\Models\Employee`, `App\Models\TrainingRecord`, `Livewire\Livewire`):

```php
test('the dashboard counts my own records by state', function () {
    $user = User::factory()->employee()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    TrainingRecord::factory()->for($employee)->count(2)->create();
    TrainingRecord::factory()->for($employee)->approved()->create();
    TrainingRecord::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSet('myPending', 2)
        ->assertSet('myApproved', 1);
});

test('the dashboard tells a head how many records await them', function () {
    $user = User::factory()->sectionHead()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertSet('awaitingMe', 0);
});
```

- [ ] **Step 2: Patakbuhin para makitang bumagsak**

```bash
php artisan test --compact tests/Feature/DashboardTest.php
```

Inaasahan: FAIL — hindi Livewire component ang `dashboard`.

- [ ] **Step 3: Gawin ang dashboard**

Gumawa ng `resources/views/pages/⚡dashboard.blade.php`:

```blade
<?php

use App\Models\Employee;
use App\Models\TrainingRecord;
use App\Workflow\ApprovalRouter;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    public int $myPending = 0;

    public int $myApproved = 0;

    public int $awaitingMe = 0;

    public int $unroutable = 0;

    public function mount(): void
    {
        $user = auth()->user();
        $employee = $user->employee;

        if ($employee instanceof Employee) {
            $this->myPending = $employee->trainingRecords()->pending()->count();
            $this->myApproved = $employee->trainingRecords()->approved()->count();
            $this->awaitingMe = $this->countAwaitingMe($employee);
        }

        if ($user->isAdminOrHr()) {
            $this->unroutable = TrainingRecord::query()->unroutable()->count();
        }
    }

    private function countAwaitingMe(Employee $employee): int
    {
        $router = app(ApprovalRouter::class);

        return TrainingRecord::query()
            ->pending()
            ->whereNotNull('current_level')
            ->with('employee')
            ->get()
            ->filter(function (TrainingRecord $record) use ($router, $employee): bool {
                $approver = $router->approverFor($record->current_level, $record->employee);

                return $approver instanceof Employee && $approver->is($employee);
            })
            ->count();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text size="sm">{{ __('My pending trainings') }}</flux:text>
            <flux:heading size="xl">{{ $myPending }}</flux:heading>
            <flux:link :href="route('trainings.mine')" wire:navigate>{{ __('View mine') }}</flux:link>
        </flux:card>

        <flux:card>
            <flux:text size="sm">{{ __('My approved trainings') }}</flux:text>
            <flux:heading size="xl">{{ $myApproved }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:text size="sm">{{ __('Waiting for my decision') }}</flux:text>
            <flux:heading size="xl">{{ $awaitingMe }}</flux:heading>
            @if ($awaitingMe > 0)
                <flux:link :href="route('approvals')" wire:navigate>{{ __('Go to approvals') }}</flux:link>
            @endif
        </flux:card>
    </div>

    @if ($unroutable > 0)
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Records with no approver')">
            {{ __(':count records cannot move because no head is designated. Set one under Setup.', ['count' => $unroutable]) }}
        </flux:callout>
    @endif

    @if (auth()->user()->employee === null)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Your account is not linked to an employee record yet.') }}
        </flux:callout>
    @endif
</div>
```

- [ ] **Step 4: Palitan ang dashboard route**

Sa `routes/web.php`, palitan ang `Route::view('dashboard', 'dashboard')->name('dashboard');` ng:

```php
Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
```

Tapos:

```bash
rm resources/views/dashboard.blade.php
```

- [ ] **Step 5: Idagdag ang navigation**

Sa `resources/views/layouts/app/sidebar.blade.php`, palitan ang `Platform` group ng:

```blade
<flux:sidebar.group :heading="__('Training')" class="grid">
    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
        {{ __('Dashboard') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="academic-cap" :href="route('trainings.mine')" :current="request()->routeIs('trainings.*')" wire:navigate>
        {{ __('My trainings') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="check-circle" :href="route('approvals')" :current="request()->routeIs('approvals')" wire:navigate>
        {{ __('Approvals') }}
    </flux:sidebar.item>
    <flux:sidebar.item icon="users" :href="route('employees.index')" :current="request()->routeIs('employees.*')" wire:navigate>
        {{ __('Employees') }}
    </flux:sidebar.item>
</flux:sidebar.group>

@if (auth()->user()->isAdminOrHr())
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

Tanggalin din ang `Repository` at `Documentation` na link ng starter kit sa ibabang `flux:sidebar.nav`.

- [ ] **Step 6: Patakbuhin ang buong suite**

```bash
php artisan test --compact
```

Inaasahan: PASS lahat.

- [ ] **Step 7: Patunayan sa aktwal na app**

```bash
php artisan migrate:fresh
php artisan ldi:import-employees
php artisan tinker --execute 'App\Models\User::create(["name" => "HR Officer", "email" => "hr@example.test", "password" => bcrypt("password"), "role" => App\Enums\UserRole::Hr, "email_verified_at" => now()]);'
npm run build
php artisan route:list --except-vendor
```

Tapos buksan ang app, mag-login bilang `hr@example.test`, at tingnan: may 134 employees, may 5 divisions at 28 sections, at may badge na "No head" ang karamihan ng section.

Patunayan na walang nagalaw sa pinagkunan:

```bash
php artisan tinker --execute 'echo DB::connection("hris")->table("employees")->count()." employees pa rin sa hris_db\n"; echo DB::connection("mysql")->select("SELECT COUNT(*) c FROM hr_training_system.trainings")[0]->c." records pa rin sa hr_training_system\n";'
```

Inaasahan: 134 at 1002 — walang binago.

- [ ] **Step 8: Commit message**

```
feat: add the dashboard and navigation
```

---

## Matapos ang lahat ng task

1. `php artisan test --compact` — dapat berde lahat
2. `vendor/bin/phpstan analyse` — may `phpstan.neon` na ang project
3. Balikan ang apat na bukas na tanong sa §8 ng spec

## Isang bagay na lumitaw habang pinaplano ito

Ang division head na nagsu-submit ng sariling training, sa section na walang head, ay **walang makakapag-apruba** — sila mismo ang huling level. Lima sila. Hinahawakan ito ng plano: naitatala ang record bilang `pending` na walang level at lumalabas sa listahan ng HR. Pero walang paraan itong maaprubahan hangga't hindi nagtatakda ng section head. Kailangan ng desisyon ng user; nakalista ito sa §8 ng spec.

## Hindi kasama — Phase 2 pataas

LDI plan at budget, reports, LDI documents, calendar, PDS. Nakahanda na ang pundasyon: nasa training record na ang gastos, ang approval trail ay buo, at ang employee data ay galing na sa `hris_db`.
