# LDI Training System — Design (Phase 1)

**Petsa:** 2026-09-07
**Status:** Draft, para sa review
**Stack:** Laravel 13, PHP 8.4, Livewire 4 (single-file components), Flux 2, Fortify, Pest 5, MySQL 8.4 (`ldi_db`)

---

## Rebisyon — bakit binago ang dokumentong ito

Ang unang bersyon ng spec na ito ay isinulat bilang bagong "compliance tracker" kung saan ang HR ang
manual na nag-a-assign ng training. **Mali iyon.** Nang matingnan ang MySQL server, may natagpuang
umiiral nang sistema — `hr_training_system`, na may 1,002 training records mula 2022 hanggang 2026 —
at ang aktwal na proseso doon ay kabaligtaran: **nagsu-submit ang empleyado, tapos inaaprubahan ng
section head at division head.** Kasama na rin sa bawat training record ang gastos, na inakala nating
Phase 3 sana.

Apat na desisyon ang binaligtad:

| Dating akala | Katotohanan |
|---|---|
| Manual na nag-a-assign ang HR (top-down) | Nagsu-submit ang empleyado, may approval chain (bottom-up) |
| Phase 3 ang budget | Nasa bawat training record na ang gastos — core ito |
| HR lang muna ang gagamit | Kailangang makapag-login ang empleyado para makapag-submit |
| Manual encoding ng employee data | May 134 na kumpletong employee record sa `hris_db` |

Hindi na greenfield ang proyekto. **Rewrite ito na may kilalang saklaw**, at ang lumang schema mismo
ang requirements document.

## 1. Layunin at hangganan

Palitan ang framework ng `hr_training_system` habang pinapanatili ang saklaw nito. Balak itong gamitin
bilang alternatibo kapag natapos.

**Mahigpit na hangganan:** ang `hr_training_system` at `hris_db` ay **basahin lamang**. Walang isusulat,
babaguhin, o buburahin sa alinman sa dalawa. Ang bagong sistema ay nasa hiwalay na schema, `ldi_db`.

## 2. Saklaw ng lumang sistema at ang pagkakahati

Ito ang aktwal na laman ng `hr_training_system`:

| Bahagi | Laman | Phase |
|---|---|---|
| Org at empleyado | divisions, sections, employment_statuses, eligibilities, employees (133) | 1 |
| Training records | 1,002 — PDS L&D fields, gastos, cpd_units, facilitator, location | 1 |
| Approval | submitted_by → section_head → division_head | 1 |
| LDI plan | `ldi_training` (26) — development_partner, target_attendees, budget, budget_source | 2 |
| Reports | buwanang aktibidad, walang training, budget utilization | 2 |
| LDI documents | photo_evidence, narrative_report, evidence_document, post_evaluation | 3 |
| Calendar | `calendar_activities` at ang `can_manage_calendar` na permiso | 3 |
| PDS | `employee_pds`, `employee_education`, `employee_emergency_contacts` | 4 |

Saklaw ng dokumentong ito ang **Phase 1**: org, empleyado, training records, at approval — ang
pangunahing loop.

## 3. Data model

Ang `hris_db` ang huwaran ng org at employee tables. Malinis ang schema nito, Laravel app din ito, at
doon manggagaling ang unang datos.

### 3.1 Org

**`divisions`** — name, code, `division_head_employee_id` (nullable), is_active

**`sections`** — division_id, name, code, `section_head_employee_id` (nullable), is_active

**`positions`** — title, item_number, salary_grade, is_active

Ang head ay nakatakda sa division at section mismo, hindi hinuhulaan mula sa role ng user. Dito
nakasalalay ang routing ng approval.

### 3.2 Empleyado

**`employees`**

| Column | Tala |
|---|---|
| user_id | nullable, unique — kabit sa login account |
| employee_number | unique |
| first_name, middle_name, last_name, suffix | hiwalay, gaya ng hinihingi ng PDS sa Phase 4 |
| position_id, section_id, division_id | |
| date_hired | |
| employment_status | `permanent`, `job_order`, `contract_of_service` — ito ang tatlong aktwal na ginagamit |
| is_active | |
| deleted_at | soft delete |

Bakit **kapwa** `section_id` at `division_id`: ganito ang hugis ng `hris_db`, kumpleto ang dalawa sa
lahat ng 134 na record, at kailangan ng division nang tuwiran para sa routing ng approval. Ang model
ang magtatakda ng `division_id` mula sa section tuwing sine-save, para hindi sila maghiwalay.

### 3.3 Training records

**Isang row bawat empleyado bawat training** — hindi katalogo na may hiwalay na attendance.

Ito ang hugis ng lumang sistema at tama ito para sa domain: indibidwal ang pagsu-submit, at
**indibidwal ang gastos** — may sariling registration fee at TEV ang bawat tao. Kung ihihiwalay natin
ang katalogo sa kalahok, kakailanganin pa rin ng per-participant na talaan ng gastos, kaya wala
tayong napapala. Bonus: kapag nagdesisyon kang ilipat ang 1,002 na lumang record balang araw,
tumatapat ang hugis.

**`training_records`**

| Column | Tala |
|---|---|
| employee_id | kanino ang training |
| title | |
| date_start, date_end | |
| hours | |
| ld_type | `managerial`, `supervisory`, `technical`, `foundation`, `other` |
| ld_type_other | kailangan kapag `other` ang `ld_type`, dapat blangko kung hindi |
| conducted_by | ang `facilitator` sa luma |
| location | |
| expenses, registration_fee, tev | decimal(10,2), nullable |
| cpd_units | float, nullable |
| status | `pending`, `approved`, `rejected` |
| current_level | `section_head`, `division_head`, o null kapag tapos |
| submitted_by | user na nag-submit |
| rejection_reason | nullable |

**`training_approvals`** — ang audit trail

| Column | Tala |
|---|---|
| training_record_id | |
| level | `section_head` o `division_head` |
| approver_user_id | |
| decision | `approved` o `rejected` |
| remarks | nullable |
| decided_at | |

Isang pagpapabuti ito sa luma: iisang `approved_by_user_id` lang ang hawak noon, kaya nawawala kung
sino ang nag-apruba sa unang hakbang. Mura ang hiwalay na talaan at hindi na mababawi ang kasaysayan
kapag hindi ito ginawa ngayon.

### 3.4 Mga index

```
sections            index(division_id)
employees           unique(employee_number), index(section_id), index(division_id), index(is_active)
training_records    index(employee_id), index(status), index(date_end), index(current_level)
training_approvals  index(training_record_id)
```

### 3.5 Enums

| Enum | Values |
|---|---|
| `UserRole` | Admin, Hr, DivisionHead, SectionHead, Employee |
| `EmploymentStatus` | Permanent, JobOrder, ContractOfService |
| `TrainingStatus` | Pending, Approved, Rejected |
| `ApprovalLevel` | SectionHead, DivisionHead |
| `LdType` | Managerial, Supervisory, Technical, Foundation, Other |

Ang apat na una ay eksaktong nasa CS Form 212. Ang `Other` ang naglululan ng aktwal ninyong gamit —
Soft Skill, Workshop, Convention, Leadership — sa pamamagitan ng `ld_type_other`. Nananatiling
malinis ang PDS output dahil ang apat ang nakatakda; ang `Other` ay ililimbag gamit ang sariling
teksto nito.

Wala ang `chief_of_hospital` — nasa enum ito ng lumang sistema pero walang user na may ganoong role,
at kinumpirma mong section head at division head lang.

## 4. Ang approval workflow

1. Nagsu-submit ng training record ang empleyado. Status ay `pending`, `current_level` ay `section_head`.
2. Inaaprubahan ng section head ng section niya → `current_level` ay `division_head`.
3. Inaaprubahan ng division head → status ay `approved`, `current_level` ay null.
4. Kahit sinong level ay pwedeng tumanggi → status ay `rejected` na may dahilan. Dito nagtatapos.

**Fallum kapag walang head:** 3 lang sa 28 na section sa `hris_db` ang may nakatakdang
`section_head_employee_id`. Kapag walang head ang section ng empleyado, **laktawan ang unang hakbang**
at diretso sa division head. Kung wala ring head ang division, mananatiling nakabinbin ito at
lalabas sa isang "walang approver" na listahan para sa HR. Kung hindi natin ito hahawakan, 25 sa 28
na section ang hindi makakapag-submit.

**Ang papel ng HR at Admin:** hindi sila bahagi ng chain, at **walang override**. Dumadaan sa
dalawang hakbang ang bawat training record, walang eksepsiyon — ito ang nagpapanatiling buo ang
audit trail. Ang kaya nila: makita ang lahat, at mag-submit para sa empleyadong wala pang account o
hindi makapag-login. Dumadaan pa rin sa parehong dalawang hakbang ang isinumite nila.

## 5. Roles at saklaw

| Role | Nakikita at nagagawa |
|---|---|
| Admin | Lahat, kasama ang pamamahala ng user account |
| Hr | Lahat ng empleyado at training record; nakakapag-submit para sa iba; humahawak ng reference data |
| DivisionHead | Ang division niya; inaaprubahan ang pangalawang hakbang |
| SectionHead | Ang section niya; inaaprubahan ang unang hakbang |
| Employee | Sarili lang; nagsu-submit ng sariling training |

Ang saklaw ay hinuhugot sa employee record na nakakabit sa user. Ang pagiging approver ay hindi
galing sa role kundi sa `section_head_employee_id` at `division_head_employee_id` — ang role ang
nagbubukas ng pinto, ang pagiging nakatakdang head ang nagtatakda kung kaninong queue.

**Kailangang isara ang public registration.** Buhay pa ang `Features::registration()` sa
`config/fortify.php`.

## 6. Pagpasok ng datos

Isang artisan command, `ldi:import-employees`, na **bumabasa lamang** sa `hris_db` sa pamamagitan ng
pangalawang read-only na koneksyon sa `config/database.php`.

Ililipat nito ang 5 divisions, 28 sections, 59 positions, at 134 employees — kasama ang
`division_head_employee_id` at `section_head_employee_id`, na kailangan ng approval routing.
Idempotent ito: `employee_number` ang susi, kaya ligtas itong ulitin.

Hindi kasama ang user account. Ang mga iyon ay gagawin sa `ldi_db` at ikakabit sa `employee_number`.

## 7. Mga screen

| Route | Para kanino | Nilalaman |
|---|---|---|
| `dashboard` | lahat | Sariling training, at bilang ng naghihintay ng aksyon mo |
| `trainings/mine` | lahat | Sariling talaan, may pindutang mag-submit |
| `trainings/create` | lahat | Submission form |
| `trainings/{record}` | may saklaw | Detalye at buong approval trail |
| `approvals` | head, HR | Pila ng naghihintay ng desisyon mo |
| `employees` | HR, Admin, head | Talaan, saklaw ayon sa role |
| `employees/{employee}` | may saklaw | Profile at kasaysayan ng training |
| `setup/divisions`, `setup/sections`, `setup/positions` | HR, Admin | Reference data |

Wala pang report sa Phase 1 — Phase 2 iyon.

## 8. Bukas na tanong

1. **Sino ang nagtatakda ng head?** Ang `section_head_employee_id` ay nasa 3 lang sa 28. Ang HR ba
   ang maglalagay nito sa bagong sistema, o palaging galing sa `hris_db`?
2. **Maaari bang mag-edit pagkatapos ma-approve?** Nakalock na ba, o pwede pang baguhin ng HR?
3. **Kailan bumibilang ang training sa isang taon?** Ipinapalagay ko na sa taon ng `date_end`,
   at mga `approved` lang ang binibilang sa report.
4. **Kailangan ba ng account ang lahat ng 134?** O ang HR muna ang magsu-submit para sa lahat
   habang unti-unting binubuksan?

**Nasagot na:** mahigpit na apat na PDS type kasama ang `Other` na may sariling teksto (§3.3, §3.5);
walang override ang HR, laging dalawang hakbang (§4).
