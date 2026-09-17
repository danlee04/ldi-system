---
paths:
  - 'app/Actions/Ldna/**'
  - 'app/Actions/Reports/LdnaGapReport.php'
  - 'app/Models/Ldna*.php'
  - 'app/Workflow/LdnaConfirmer.php'
  - 'resources/views/pages/ldna/**'
---

# Ldna

## The LDNA is a self assessment — nobody rates anybody else
The only level on a rating is ldna_ratings.self_level, given by the person themselves. The head's part is to read it and confirm it; confirming writes ldna_assessments.confirmed_at / confirmed_by and adds nothing to the levels. LdnaRating::gap() is therefore required_level minus self_level.

The gap report counts a rating only when the assessment carries confirmed_at. An answer nobody has agreed to is not planning material, and a draft is the person's own until they submit it.

There was a supervisor_level column and a rating screen for it. Both were dropped on 2026-09-17; do not reintroduce a second opinion on a level without asking.

## A cycle's required levels are copied, never read back from the framework
ldna_ratings.required_level is copied from competencies / competency_position when an assessment is made or refreshed. The gap report and every screen read that copy. Reading the current framework for a past cycle would silently move last year's gaps whenever HR edits a level. Who confirms somebody is the opposite: never stored, worked out by LdnaConfirmer at the moment it is asked, so a new head takes over mid-cycle.

LdnaConfirmer memoises the active-employee set per instance, so it must never be bound as a singleton.

## Never sync() a position's technical competencies — touch only the ones the form offered
SetPositionCompetencies loops over the competencies the form asked about and attaches or detaches each one, rather than calling sync() on the lot. The form offers active technical competencies only, so a deactivated one keeps the level it was set at.

That level is not decoration: ldna_ratings.required_level was copied from it, and the gap report of a past cycle reads that copy. Replacing the loop with sync() would wipe the deactivated rows and leave nothing to refresh a reopened assessment from.
