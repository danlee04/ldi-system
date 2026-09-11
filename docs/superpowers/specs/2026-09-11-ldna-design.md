# LDNA — Learning and Development Needs Assessment — Design

**Petsa:** 2026-09-11
**Status:** Draft, para sa review
**Stack:** Laravel 13, PHP 8.4, Livewire 4 (single-file components), Flux 2 (free tier), Pest, MySQL 8.4 (`ldi_db`)
**Kaugnay:** `docs/superpowers/specs/2026-09-07-hr-training-compliance-design.md` — dito unang itinabi ang LDNA bilang hiwalay na subsystem ("competency GAP")

---

## 1. Layunin at hangganan

Sukatin, kada taon, kung gaano kalayo ang kakayahan ng bawat empleyado sa kailangan ng posisyon niya, at
gawing batayan ng LDI plan ang pagitang iyon.

Walang LDNA sa `hr_training_system` o sa `hris_db`, kaya **bago ang subsystem na ito** at walang lumang
schema na huwaran. Ang dalawang legacy schema ay nananatiling **basahin lamang**; walang isusulat sa
alinman.

**Saklaw:** competency framework, taunang assessment (self + supervisor), gap report, at ang tag ng
competency sa LDI plan.

**Hindi saklaw:** IDP, gap ng team sa dashboard ng head, LDNA card sa dashboard ng HR, Excel export,
"kopyahin mula sa ibang posisyon". Tingnan ang §11.

## 2. Mga desisyong napagkasunduan

| Tanong | Desisyon |
|---|---|
| Uri ng LDNA | Competency-based, may rating ng antas |
| Pinagmumulan ng framework | May dictionary na ang DOH/Center; ie-encode ng HR sa Setup |
| Mga uri ng competency | Core, Leadership, Technical. (Walang Organizational; isang enum case lang kung kakailanganin.) |
| Sukatan | 4 na antas: Basic, Intermediate, Advanced, Superior — may paglalarawan ng kilos kada antas |
| Pagtatakda ng required | Ayon sa uri: Core = iisang antas para sa lahat; Leadership = iisang antas para sa mga naka-designate na head; Technical = kada posisyon, ilan lang |
| Nagre-rate | Sarili + supervisor. **Ang rating ng supervisor ang bilang sa gap** |
| Supervisor | Gaya ng approvals: section head → division head → HR |
| Ritmo | Taunang cycle na binubuksan ng HR, may simula at deadline |
| Kasaysayan | **Option A — snapshot.** Kinokopya ang required na antas sa bawat rating sa pagbuo ng cycle |
| Ugnay sa LDI plan | Tina-tag ng HR ang plan ng mga competency na tinutugunan nito |

## 3. Data model

Pitong bagong table sa `ldi_db`. Walang binabagong umiiral na table.

### 3.1 Ang framework

**`competencies`**
- `name`, `description` (nullable)
- `type` — `CompetencyType`: `core`, `leadership`, `technical`
- `required_level` — `ProficiencyLevel`, nullable. **Kailangan** sa Core at Leadership; **laging null** sa Technical
- `is_active`

**`competency_indicators`**
- `competency_id`, `level` (`ProficiencyLevel`), `description`
- unique (`competency_id`, `level`). Apat na hilera kada competency

**`competency_position`** — Technical lang
- `position_id`, `competency_id`, `required_level` (`ProficiencyLevel`)
- unique (`position_id`, `competency_id`)

### 3.2 Ang cycle

**`ldna_cycles`**
- `year` (unique) — ang taon ng **plano**. Ang "LDNA 2027" ay ginagawa sa huling bahagi ng 2026
- `opens_on`, `closes_on`, `created_by`
- Walang status column. Kinukuwenta: *paparating* bago ang `opens_on`, *bukas* mula `opens_on` hanggang
  `closes_on` (kasama), *sarado* pagkatapos

**`ldna_assessments`**
- `ldna_cycle_id`, `employee_id` — unique (cycle, employee)
- `position_id` — ang posisyon niya noong nabuo o huling na-Refresh
- `self_submitted_at`, `rated_at`, `rated_by` (user id, nullable)
- **Walang nakatabing magre-rate.** Hinahanap sa oras ng pag-rate (§4.4). Ang nakatabi ay kung sino ang
  aktwal na nag-rate (`rated_by`)

**`ldna_ratings`**
- `ldna_assessment_id`, `competency_id` — unique (assessment, competency)
- **`required_level`** — kinopya mula sa framework sa pagbuo o sa Refresh; hindi binabasa ulit
- `self_level`, `supervisor_level` (parehong nullable), `remarks` (nullable)

### 3.3 Ang tag

**`competency_ldi_training`** — pivot: `competency_id`, `ldi_training_id`

### 3.4 Mga enum

`app/Enums/`, isinulat nang mano-mano, string-backed, may `label()` — ayon sa `.ai/rules/enums.md`.

- `CompetencyType`: `Core`, `Leadership`, `Technical`
- `ProficiencyLevel`: `Basic`, `Intermediate`, `Advanced`, `Superior`, may `rank(): int` (1–4)

### 3.5 Ang gap

```
gap = max(0, required_level.rank − supervisor_level.rank)
```

Walang gap hangga't walang `supervisor_level`. Sa PHP kinukuwenta, hindi sa SQL. Mga 2,700 hilera lang ang
isang cycle (134 × ~20).

### 3.6 Pagbura

Hindi binubura ang competency na may rating na; dine-deactivate lang. Ang walang rating ay maaaring burahin.

## 4. Ang daloy

```
HR bumubuo ng cycle ──► bukas ──► self-rating ─────────┐
                                  rating ng supervisor ──► sarado ──► gap report
```

### 4.1 Pagbuo ng cycle (HR)

Ilalagay ng HR ang `year`, `opens_on`, `closes_on`, at kukumpirmahin sa modal. Sa sandaling iyon, sa loob
ng isang transaction, gagawan ng assessment ang bawat **aktibong** empleyado, gamit ang
`BuildCompetencyProfile`:

- lahat ng aktibong **Core**, sa `required_level` nito;
- lahat ng aktibong **Leadership**, sa `required_level` nito, **kung** head siya ng anumang section o
  division sa oras na iyon (`section_head_employee_id` / `division_head_employee_id`);
- ang aktibong **Technical** ng posisyon niya, sa antas na nasa `competency_position`.

Tinatanggihan ang pagbuo kapag: walang aktibong competency; may cycle na sa taong iyon; o nauna ang
`closes_on` sa `opens_on`.

### 4.2 Abiso

Sa pagbuo ng cycle, may isang `LdnaCycleOpened` na abiso sa bawat aktibong empleyadong may account, na
nakasulat ang window. **Walang scheduler** — hindi tumatakbo ang `schedule:run` sa Laragon nang hindi
sinasadyang ise-set up. Ang mga idinagdag ng Sync ay tumatanggap ng parehong abiso.

### 4.3 Self-rating (empleyado)

- Pipili ng antas sa bawat competency. Maaaring i-save nang paunti-unti
- Ang **Submit** ay nangangailangan na may `self_level` ang lahat, at nagtatakda ng `self_submitted_at`.
  Magpapadala ito ng `SelfRatingSubmitted` sa magre-rate (§4.4). Kapag HR ang magre-rate, sa lahat ng
  user na may role na HR
- **Nakatago ang `required_level` hanggang makapag-submit**, para hindi kumapit ang sagot sa target
- Pagsara ng cycle, lalabas sa kanya ang `supervisor_level` at ang gap

### 4.4 Rating ng supervisor

Ang magre-rate ay hinahanap sa oras ng pag-rate gamit ang `ApprovalRouter::approverFor()`:
`SectionHead`, kung wala ay `DivisionHead`, kung wala pa rin ay **HR**. Hindi kailanman ang sarili. Kapag
napalitan ang head habang bukas ang cycle, sa bagong head na lalabas.

- Kita ng supervisor ang `required_level` at ang `self_level` (kung naka-submit na)
- **Makakapag-rate kahit hindi pa nakakapag-submit ang empleyado**, para hindi maipit ang gap
- Ang **Submit** ay nangangailangan na may `supervisor_level` ang lahat, at nagtatakda ng `rated_at` at
  `rated_by`

### 4.5 Pagbabago habang bukas

- Parehong rating ay mababago hanggang sa pagsara. Ang huli ang bilang. Ang naka-submit ay kailangang
  manatiling kumpleto
- **Sync (HR):** gagawan ng assessment ang bawat aktibong empleyadong wala pa. Hindi ginagalaw ang mga
  mayroon na
- **Refresh (HR, kada tao):** kinukuha ulit ang listahan mula sa kasalukuyang posisyon, pagka-head, at
  aktibong framework:
  - ang competency na nasa bagong listahan pa rin: itinatabi ang `self_level`, `supervisor_level` at
    `remarks`, pero **ina-update ang `required_level`**;
  - ang wala na: inaalis;
  - ang bago: idinadagdag nang walang rating, at **inaalis ang `self_submitted_at` at `rated_at`** para
    makumpleto ulit.

### 4.6 Pagsasara

Pagdaan ng `closes_on`, read-only ang lahat, at tinatanggihan ito ng mga action — hindi lang itinatago sa
UI. Maaaring **i-extend ng HR ang `closes_on`**. Ang empleyadong nag-inactive ay nananatili sa kasaysayan
pero hindi binibilang sa report.

## 5. Ang mga screen

### 5.1 Sidebar

| Grupo | Item | Para kanino |
|---|---|---|
| My work | **My LDNA** | bawat may employee record |
| My work | **LDNA ratings**, may badge ng hindi pa nare-rate sa bukas na cycle | sinumang may taong ire-rate, at ang HR |
| Organization | **LDNA** | HR at admin |
| Setup | **Competencies** | HR at admin |

### 5.2 Setup → Competencies — `pages::setup.competencies`

- Talaan na may filter ayon sa uri
- Modal na `md:w-5xl md:max-w-[calc(100vw-4rem)]`, dalawang kolum:
  - name, type, description;
  - `required_level`, na lalabas lang sa Core at Leadership;
  - apat na textarea, Basic hanggang Superior.
- Ang aksiyon ay **Deactivate** kapag may rating na, **Delete** kapag wala. Parehong nasa confirmation
  modal, ayon sa `.ai/rules/pages.md`

### 5.3 Setup → Positions (dagdag)

Bagong icon button sa bawat hilera, **Competencies**. Bubukas ang modal na nakalista ang lahat ng aktibong
Technical: may checkbox at antas sa bawat isa. Ito ang sumusulat sa `competency_position`.

### 5.4 LDNA (HR) — `pages::ldna.index` at `pages::ldna.show`

**Index:** mga cycle — taon, window, status, at progress (*87 sa 134 na-rate*). May **New cycle** na modal.

**Show:** header na may window at **Extend**, at dalawang tab:

- **Progress:** bawat tao — section, self-rating (oo/hindi), rating ng supervisor (oo/hindi), at ang
  magre-rate (o "HR"). May filter ng division at status. Nasa itaas ang **Sync**. Nasa bawat hilera ang
  **Refresh**, at ang **Rate** kapag HR ang magre-rate. May mga tanda:
  - *walang Technical ang posisyon*
  - *walang account*
- **Gaps:** ang report, §6

### 5.5 My LDNA — `pages::ldna.mine`

- Mga competency ng bukas na cycle, nakagrupo ayon sa uri
- Bawat competency ay card na may apat na pagpipiliang antas, bawat isa may paglalarawan
- Nasa ibaba ang progress (*12 sa 18*), **Save**, at **Submit**
- May pili ng taon para sa mga nakaraang cycle
- Kapag walang bukas na cycle at walang nakaraan: *"Walang bukas na LDNA ngayon."*
- Kapag may bukas na cycle pero wala pa siyang assessment (bagong empleyadong hindi pa naisa-Sync):
  *"Wala ka pa sa LDNA {taon}. Ang HR ang magdadagdag sa iyo."*

### 5.6 LDNA ratings — `pages::ldna.ratings` at `pages::ldna.rate`

- **Ratings:** ang mga taong itinuturo sa kanya ng §4.4 sa bukas na cycle, kasama ang status. Para sa HR,
  ang mga walang head
- **Rate:** isang buong page, hindi modal, dahil 10 hanggang 20 hilera ang laman. Bawat hilera:
  competency, required, self-rating, ang pipiliing antas (may paglalarawan), at remarks

### 5.7 LDI Trainings (dagdag)

Sa kasalukuyang modal: checkbox group ng mga aktibong competency, nakagrupo ayon sa uri, `md:col-span-2`.
Ito ang sumusulat sa `competency_ldi_training`.

## 6. Ang gap report — `LdnaGapReport`

Nasa `app/Actions/Reports/`, nagsasauli ng plain data gaya ng ibang report. Tab ng cycle; may filter ng
division at section; may print view gaya ng Reports.

| Kolum | Kahulugan |
|---|---|
| Competency, Uri | |
| Na-rate | mga aktibong empleyadong may `supervisor_level` sa competency na ito |
| May gap | sa mga na-rate, ilan ang may gap > 0 |
| % | may gap ÷ na-rate |
| Avg. gap | karaniwang gap **ng mga may gap**, sa antas |
| LDI plan sa {taon} | bilang ng LDI plan na ang `date_start` ay nasa taon ng cycle at naka-tag sa competency |

- Nakaayos ayon sa *may gap*, pababa
- **Namumukod ang hilerang may gap pero 0 ang LDI plan** — ang gap na walang tumutugon
- Ang pagbukas sa isang hilera ay naglalabas ng mga pangalan, ang required, ang rating, at ang gap

## 7. Pahintulot — `LdnaAssessmentPolicy`

| Aksiyon | Sino |
|---|---|
| Self-rate | ang may-ari lang, habang bukas |
| Supervisor-rate | ang itinuturo ng §4.4; kapag walang itinuturo, sinumang user na HR o admin (`isAdminOrHr()`); hindi ang sarili; habang bukas |
| Framework, cycle, Sync, Refresh, Extend, gap report | HR at admin |
| Tingnan ang sariling gap | ang may-ari, pagkasara |

Tinatawag ng bawat action ang policy, kaya iisa ang tuntunin sa page at sa action.

## 8. Mga kaso sa gilid

| Kaso | Gagawin |
|---|---|
| Nire-rate habang sarado | tinatanggihan ng action |
| Rating ng competency na wala sa assessment | validation error |
| Posisyong walang Technical | Core at Leadership lang; may tanda sa Progress |
| Empleyadong walang account | hindi makakapag-self-rate; nare-rate pa rin; may tanda |
| Nag-deactivate ng competency habang bukas | nananatili sa mga nagawang assessment; mawawala sa Refresh |
| Nailipat ng posisyon o naging head habang bukas | Refresh (§4.5) |
| Bagong empleyado habang bukas | Sync |

## 9. Mga bahagi sa code

- `app/Enums/CompetencyType.php`, `app/Enums/ProficiencyLevel.php`
- Models: `Competency`, `CompetencyIndicator`, `LdnaCycle`, `LdnaAssessment`, `LdnaRating`, may factories
- `app/Actions/Ldna/`:
  - `BuildCompetencyProfile` — ang listahan at required ng isang tao; ginagamit ng pagbuo, ng Sync, at ng
    Refresh
  - `OpenLdnaCycle`, `SyncLdnaCycle`, `RefreshLdnaAssessment`
  - `SaveSelfRating`, `SaveSupervisorRating` — may `submit` na flag
- `app/Policies/LdnaAssessmentPolicy.php`
- `app/Notifications/LdnaCycleOpened.php`, `app/Notifications/SelfRatingSubmitted.php`
- `app/Actions/Reports/LdnaGapReport.php`
- Mga page sa §5

## 10. Testing

Pest feature tests. Ang mga dapat mapatunayan:

1. **Hindi nagbabago ang gap kapag binago ang framework pagkabuo ng cycle.** Ito ang dahilan ng Option A
2. Tamang magre-rate: section head → division head → HR; lumilipat sa bagong head kapag napalitan
3. Nakatago ang required habang nagse-self-rate; lumalabas pagkatapos mag-submit
4. Makakapag-rate ang supervisor kahit walang self-rating; tinatanggihan ang ibang head (403)
5. Read-only ang lahat kapag sarado
6. Itinatabi ng Refresh ang mga rating na nasa bagong listahan at ina-update ang required; ang Sync ay
   nagdadagdag lang ng wala pa
7. Sa pagbuo: Core sa lahat, Leadership sa head lang, Technical ayon sa posisyon
8. Tama ang bilang ng gap report; hindi binibilang ang inactive; namumukod ang gap na walang plan
9. Naise-save ang tag sa LDI plan

## 11. Ayos ng trabaho

Iisang plano, limang bahagi. Bawat isa ay magagamit na kapag natapos:

1. Framework — enums, `competencies`, `competency_indicators`, `competency_position`; Setup → Competencies;
   ang modal sa Positions
2. Cycle — `ldna_cycles`, `ldna_assessments`, `ldna_ratings`; `BuildCompetencyProfile`, pagbuo, Sync,
   Refresh, Extend; ang index at ang Progress tab; `LdnaCycleOpened`
3. Self-rating — My LDNA; `SelfRatingSubmitted`
4. Rating ng supervisor — LDNA ratings, Rate, ang badge sa sidebar
5. Gap report at ang tag sa LDI plan

**Bago buksan ang unang cycle:** 3 lang sa 28 section ang may head, kaya halos lahat ng 134 ay mapupunta sa
mga division head para i-rate. Hindi nito sinisira ang disenyo, pero mabigat iyon. Italaga ang mga section
head sa `/setup/sections` bago buuin ang LDNA 2027.

**Hindi kasama, maaaring idagdag sa huli:** IDP; gap ng team sa dashboard ng head; LDNA card sa dashboard
ng HR; "kopyahin mula sa ibang posisyon"; Excel export; Organizational na uri ng competency.
