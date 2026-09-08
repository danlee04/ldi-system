---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Two legacy databases, both strictly read-only
This app owns `ldi_db` only. Two other schemas are sources and must never receive an INSERT, UPDATE, DELETE or DDL:

- `hris` connection → `hris_db`: divisions, sections, positions, employees. Matched on `employee_number`.
- `legacy` connection → `hr_training_system`: the system this one replaces, and the only source of civil service eligibility. It has no `employee_number`, so `ldi:import-eligibilities` matches on first + last name with suffixes (JR/SR/II/III/IV) stripped from both sides — the legacy table glues them into the first name. That matches all 134 today; a mismatch is reported as a warning, never a failure.

Both importers are idempotent and take their connection and table names from `config('ldi.*')` so tests can point them at fixture tables instead of a real database.
