# LDNA — ano ang naitayo

**Petsa:** 2026-09-14
**Para kanino:** review bago gamitin ang sistema
**Spec:** `docs/superpowers/specs/2026-09-11-ldna-design.md` · **Plano:** `docs/superpowers/plans/2026-09-11-ldna.md`

---

## 1. Ano ito, sa isang talata

Taunang **competency-based na needs assessment**. May listahan ng kakayahan (competency
dictionary) na may antas na hinihingi sa bawat tao. **Ang empleyado lang ang nagbibigay
ng antas sa sarili niya**; binabasa ito ng head niya at kinukumpirma. Ang pagitan ng
hinihingi at ng ibinigay niya sa sarili ay ang **gap**.
Ang buod ng mga gap ang batayan ng HR sa pagpaplano ng LDI trainings sa susunod na taon, at
makikita mismo kung aling gap ang **walang** planong tumutugon.

## 2. Ano ang makikita ng bawat tao

| Sino | Saan | Ano ang magagawa |
|---|---|---|
| Bawat empleyado | **My work → My LDNA** | Magre-rate sa sarili sa bawat competency, i-save nang paunti-unti, i-submit kapag kumpleto; makikita ang sariling gap pagsara ng cycle |
| Section at division head | **My work → LDNA ratings** (may badge ng bilang na hindi pa nare-rate) | Listahan ng mga tao niya; pagbukas sa isang tao, ire-rate ang bawat competency at maglalagay ng remarks |
| HR at admin | **Setup → Competencies** | Ie-encode ang dictionary: pangalan, uri, hinihinging antas, at paglalarawan ng apat na antas |
| HR at admin | **Setup → Positions** → icon na `puzzle-piece` | Itatakda kung anong Technical competency ang kailangan ng bawat posisyon, at sa anong antas |
| HR at admin | **Organization → LDNA** | Bubuo ng cycle; susubaybayan ang **Progress**; babasahin ang **Gaps**; magdadagdag ng bagong tao; magre-refresh ng nailipat; magpapalit ng petsa ng pagsasara |
| HR at admin | **LDI trainings** (modal) | Tatatakan ang bawat plan ng mga competency na tinutugunan nito |

## 3. Ang daloy, dulo-dulo

```
HR mag-e-encode ng dictionary ──► itatakda ang Technical kada posisyon
        │
        ▼
HR bubuo ng cycle (hal. LDNA 2027, Okt 1–31)
        │  ← dito kinokopya ang hinihinging antas ng bawat tao
        ▼
abiso sa lahat ng may account
        │
        └──► empleyado: self-assessment ──► submit ──► abiso sa magkukumpirma
                    │
                    └──► head: babasahin ──► kukumpirmahin
                                │
                                ▼
            pagsara ng cycle ──► gap report ──► LDI plan sa susunod na taon
```

Sino ang magkukumpirma: **section head → division head → HR**. Iisa ito sa tuntunin ng
approvals, kaya ang naga-aprub ng training mo ay siya ring kumukumpirma sa iyo. Hindi
kailanman ang sarili, at hindi ang head na inactive na.

## 4. Apat na desisyon sa disenyo, at ang dahilan

**1. Kinokopya ang hinihinging antas, hindi binabasa ulit.**
Pagbuo ng cycle, isinusulat sa bawat rating ang antas na hinihingi sa oras na iyon. Kapag
binago ng HR ang dictionary sa 2028, **hindi gagalaw** ang gap ng 2027. Kung live na binabasa,
tahimik na lalabas na lumiit ang gap ng nakaraang taon kahit walang nangyaring training —
mawawalan ng saysay ang paghahambing taon-taon.

**2. Nakatago ang target habang nagse-self-rate.**
Lumalabas lang ang hinihinging antas pagkatapos mag-submit. Kapag nakita muna ng tao ang target,
doon kakapit ang sagot niya, at mawawalan ng silbi ang self-rating bilang pangalawang tingin.

**3. Walang nakukumpirma hangga't hindi pa naisusumite.**
Ang sagot ay sa tao hanggang i-submit niya. Kung makukumpirma ang draft, mai-freeze ang
sagot na hindi pa naman niya tapos. At ang kumpirmasyon lang ang naglalagay ng gap sa
report — ang hindi pa nakukumpirma ay hindi pa batayan ng plano.

**4. Hindi binubura ang competency na nagamit na — dine-deactivate.**
Kung buburahin, kasama nitong mawawala ang mga sagot ng nakaraang taon. Ang Delete ay lumalabas
lang para sa competency na wala pang rating.

## 5. Data model — pitong bagong table

| Table | Laman |
|---|---|
| `competencies` | name, description, `type` (Core/Leadership/Technical), `required_level` (Core at Leadership lang), is_active |
| `competency_indicators` | paglalarawan ng competency sa bawat antas — apat kada isa |
| `competency_position` | Technical lang: anong posisyon ang kailangan nito, sa anong antas |
| `ldna_cycles` | taon (unique), opens_on, closes_on, created_by |
| `ldna_assessments` | isa kada tao kada cycle: posisyon noong nabuo, self_submitted_at, confirmed_at, confirmed_by |
| `ldna_ratings` | **`required_level` (kopya)**, self_level, remarks (galing sa head) |
| `competency_ldi_training` | anong competency ang tinutugunan ng isang LDI plan |

`gap = required − antas na ibinigay niya sa sarili`, at hindi bumababa sa zero. Walang gap
hangga't hindi pa siya sumasagot, at hindi ito pumapasok sa report hangga't hindi pa
nakukumpirma ng head.

## 6. Saan nakatira ang code

| Bahagi | File |
|---|---|
| Uri at antas | `app/Enums/CompetencyType.php`, `ProficiencyLevel.php` |
| Models | `app/Models/Competency.php`, `CompetencyIndicator.php`, `LdnaCycle.php`, `LdnaAssessment.php`, `LdnaRating.php` |
| Sino ang magkukumpirma | `app/Workflow/LdnaConfirmer.php` |
| Pahintulot | `app/Policies/LdnaAssessmentPolicy.php` |
| Tuntunin ng negosyo | `app/Actions/Ldna/` — `BuildCompetencyProfile`, `EnrolInLdnaCycle`, `OpenLdnaCycle`, `SyncLdnaCycle`, `RefreshLdnaAssessment`, `SaveSelfRating`, `ConfirmLdnaAssessment`, `ValidateRatingInput`, `CountLdnaConfirmationsDue`, `SaveCompetency`, `DeleteCompetency`, `SetPositionCompetencies` |
| Report | `app/Actions/Reports/LdnaGapReport.php` |
| Mga screen | `resources/views/pages/ldna/` (`index`, `show`, `mine`, `confirmations`, `review`), `pages/setup/⚡competencies.blade.php`, at ang modal sa `pages/setup/⚡positions.blade.php` |
| Pinagsasaluhang piraso | `resources/views/components/ldna/level-picker.blade.php` |
| Abiso | `app/Notifications/LdnaCycleOpened.php`, `SelfRatingSubmitted.php` |
| Tuntuning naitala | `.ai/rules/ldna.md` |

Mga route: `/ldna`, `/ldna/mine`, `/ldna/confirmations`, `/ldna/confirmations/{assessment}`,
`/ldna/{cycle}`, `/setup/competencies`.

## 7. Testing

**83 bagong test** sa sampung file. Buong suite: **539 test, 537 pumasa, 2 skipped** (parehong
skip bago pa ang LDNA). Malinis ang Pint, 0 error ang PHPStan.

| File | Bilang | Ano ang binabantayan |
|---|---|---|
| `CompetencySetupTest` | 8 | Pag-encode ng dictionary, apat na paglalarawan, deactivate |
| `PositionCompetenciesTest` | 5 | Technical kada posisyon; hindi nagagalaw ang deactivated |
| `LdnaConfirmerTest` | 4 (may dataset) | **Magkatugma ang `LdnaConfirmer` at `ApprovalRouter`** sa limang sangay |
| `LdnaCycleTest` | 9 | Pagbuo, kung sino ang binibilang, **hindi gumagalaw ang kopya**, abiso, bantay sa pagbura |
| `LdnaChangesTest` | 5 | Sync at Refresh, at ang pagtanggi sa saradong cycle |
| `LdnaCycleScreensTest` | 14 | Mga screen ng HR, mga babala, petsa ng pagsasara |
| `SelfRatingTest` | 14 | Nakatagong target, save vs submit, abiso nang minsan lang |
| `ConfirmAssessmentTest` | 13 | Sino ang pwede, palit ng head, saradong cycle, badge, at na **hindi nakukumpirma ang hindi pa naisusumite** |
| `LdiCompetencyTagTest` | 5 | Tag sa plan; hindi nalalaglag ang deactivated |
| `LdnaGapReportTest` | 8 | Bilang, karaniwan, filter, ayos, "No plan" |

Tiningnan din sa headless Chrome ang pitong screen (Competencies, Positions modal, Progress,
Gaps, My LDNA bago at pagkatapos mag-submit, at ang rate page). Walang kailangang ayusin sa
hitsura.

## 8. Bago mo buksan ang unang cycle

1. **I-encode ang dictionary** sa Setup → Competencies. Hindi mabubuo ang cycle habang walang
   kahit isang aktibong competency.
2. **Itakda ang Technical kada posisyon** sa Setup → Positions. Ang posisyong walang Technical ay
   may babalang *"No technical"* sa Progress.
3. **Italaga ang mga section head** sa `/setup/sections`. Ngayon, **3 sa 28 section lang ang may
   head**, kaya halos lahat ng 134 na tao ay mapupunta sa mga division head para i-rate — 10
   hanggang 20 competency kada tao. Ito ang pinakamalaking gawain bago ang LDNA 2027.
4. Ang taon ng cycle ay ang taon ng **plano**: ang LDNA 2027 ay ginagawa sa huling bahagi ng 2026.

## 9. Kilalang limitasyon, sinadyang iwan

- **Walang IDP.** Ang Individual Development Plan ay susunod na bahagi.
- **Walang gap ng team sa dashboard ng head**, at walang LDNA card sa dashboard ng HR.
- **Walang Excel export** ng gap report — may print view.
- **Walang Organizational** na uri ng competency. Isang dagdag na enum case lang kung kailangan.
- **Ang competency na wala pang rating ay hindi lumalabas sa Gaps.** Hindi makikita roon ang
  "hindi pa na-assess".
- **Mabigat ang pagbukas ng cycle:** mga 3,200 query para sa 134 na tao, sa iisang transaction.
  Kaya pa ng sukat natin; may dalawang linyang lunas kung bumagal.
- **Isang abiso lang** ang ipinapadala, sa oras ng pagbuo ng cycle, dahil walang scheduler na
  tumatakbo sa server. Kapag pinalitan ang petsa ng pagsasara, luma pa rin ang petsang nasa dating
  abiso.

## 10. Mga commit

| Commit | Laman |
|---|---|
| `af6dd4d`, `cfa4765` | Ang spec at ang siyam na task na plano |
| `0a0f181` | Dictionary, Technical kada posisyon, cycle at snapshot, mga screen ng HR, self-rating |
| *(hindi pa naka-commit)* | Rating ng supervisor, tag sa LDI plan, gap report, verification, at ang mga fix mula sa huling review |

Ang mensahe para sa huling bahagi:

```
feat: let heads rate their people's competencies, tag each LDI plan with what it builds, and report every cycle's gaps beside the plans that answer them
```
