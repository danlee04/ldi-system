---
paths:
  - 'app/Console/Commands/**'
---

# Console commands

## The employee and eligibility imports are gone — this app owns that data
`ldi:import-employees` and `ldi:import-eligibilities` copied the 134 employees, the org tree and their civil service eligibility out of `hris_db` and `hr_training_system` once. That migration is done and both commands were deleted: employee records are edited in this app now, and re-running an import would overwrite what a user changed. Recover them from git history if a fresh copy is ever needed; do not add a scheduled sync.

## `ldi:import-user-accounts` — sign-ins from the HRIS
Creates a sign-in for every employee from their `hris_db.users` row, copying the bcrypt hash so people keep the password they already use, matched through `employee_number`. Idempotent: an existing email is relinked and its role corrected, never duplicated.

Roles are derived, not copied: `is_hr_officer` wins, then division head, then section head, then plain employee. HR outranks a head designation on purpose — `TrainingRecordPolicy::decide` grants approval from being the *designated head*, not from the role, so an HR officer who also heads a section still decides on that section. There is a test for that in `DecideOnTrainingRecordTest`.

New accounts after this point are made on the Admin screen at `setup/users`, not by re-importing.

## `ldi:import-training-history` — the 1,002 legacy rows
`hr_training_system.trainings` holds two different things. Rows **with** an employee are attendance and become `training_records`. The 28 rows **without** one are shells that `ldi_training` hangs its planning data off, and become `ldi_trainings`. Three rows are neither and are not imported.

Notes that matter if it is ever re-run or extended:

- Employees are matched on name, since the legacy roster has no employee number. Suffixes (JR/SR/II/III/IV) are stripped from both sides because the legacy table glues them into the first name.
- `type_of_ld` is free text there. The four CS Form 212 types map across; anything else becomes `Other` carrying its own words. Blank becomes `Other` with no text and is counted in a warning, because somebody has to set it by hand.
- Legacy attendance carries no link to a plan, so imported history is never attached to an `ldi_training`. That link only exists for attendance recorded in this app.
- The historical approval trail cannot be rebuilt: the legacy table holds one `approved_by_user_id` and its users have usernames, not emails. Imported records land approved with no `training_approvals` rows.
- Idempotency keys on employee + title + `whereDate(date_start)`. A plain `updateOrCreate` on the date does **not** match on a second run — the raw string is compared against the stored value.

## The two legacy schemas stay read-only
The `hris` and `legacy` connections are configured for reading only. This app owns `ldi_db` — never INSERT, UPDATE, DELETE or run DDL against either legacy schema.
