---
paths:
  - 'app/Actions/Ldna/**'
  - 'app/Actions/Reports/LdnaGapReport.php'
  - 'app/Models/Ldna*.php'
  - 'app/Workflow/LdnaRater.php'
  - 'resources/views/pages/ldna/**'
---

# Ldna

## A cycle's required levels are copied, never read back from the framework
ldna_ratings.required_level is copied from competencies / competency_position when an assessment is made or refreshed. The gap report and every screen read that copy. Reading the current framework for a past cycle would silently move last year's gaps whenever HR edits a level. Who rates somebody is the opposite: never stored, worked out by LdnaRater at the moment it is asked, so a new head takes over mid-cycle.

LdnaRater memoises the active-employee set per instance, so it must never be bound as a singleton.

## Never sync() a position's technical competencies — touch only the ones the form offered
SetPositionCompetencies loops over the competencies the form asked about and attaches or detaches each one, rather than calling sync() on the lot. The form offers active technical competencies only, so a deactivated one keeps the level it was set at.

That level is not decoration: ldna_ratings.required_level was copied from it, and the gap report of a past cycle reads that copy. Replacing the loop with sync() would wipe the deactivated rows and leave nothing to refresh a reopened assessment from.
