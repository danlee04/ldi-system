---
paths:
  - 'app/Console/Commands/**'
---

# Console commands

## The legacy imports are gone — this app owns its employee data
`ldi:import-employees` and `ldi:import-eligibilities` copied the 134 employees, the org tree and their civil service eligibility out of `hris_db` and `hr_training_system` once. That migration is done and both commands were deleted: employee records are now edited in this app, and re-running an import would overwrite what a user changed. Recover them from git history if a fresh copy is ever needed; do not add a scheduled sync.

## The two legacy schemas stay read-only
The `hris` and `legacy` connections are still configured because Phase 2 reads the 1,002 historical training records out of `hr_training_system`. This app owns `ldi_db` only — never INSERT, UPDATE, DELETE or run DDL against either legacy schema.
