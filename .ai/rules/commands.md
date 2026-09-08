---
paths:
  - 'app/Console/Commands/**'
---

# Console commands

## The employee and eligibility imports are gone — this app owns that data
`ldi:import-employees` and `ldi:import-eligibilities` copied the 134 employees, the org tree and their civil service eligibility out of `hris_db` and `hr_training_system` once. That migration is done and both commands were deleted: employee records are edited in this app now, and re-running an import would overwrite what a user changed. Recover them from git history if a fresh copy is ever needed; do not add a scheduled sync.

## `ldi:import-user-accounts` is the one import that stays
It creates a sign-in for every employee from their existing `hris_db.users` row, copying the bcrypt hash so people keep the password they already use, and links it through `employee_number`. It is idempotent: an account whose email already exists is relinked and has its role corrected rather than duplicated.

Roles are derived, not copied: `is_hr_officer` wins, then division head, then section head, then plain employee. HR outranks a head designation on purpose — `TrainingRecordPolicy::decide` grants approval from being the *designated head*, not from the role, so an HR officer who also heads a section still decides on that section. There is a test for that in `DecideOnTrainingRecordTest`.

New accounts after this point are made on the Admin screen at `setup/users`, not by re-importing.

## The two legacy schemas stay read-only
The `hris` and `legacy` connections are still configured because Phase 2 reads the 1,002 historical training records out of `hr_training_system`. This app owns `ldi_db` only — never INSERT, UPDATE, DELETE or run DDL against either legacy schema.
