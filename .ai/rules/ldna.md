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
