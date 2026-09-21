---
paths:
  - config/app.php
---

# Config

## The app runs on Asia/Manila; pre-2026-09-21 timestamps are UTC
config/app.php timezone is Asia/Manila (was UTC until 2026-09-21). Timestamps written before then — created_at/updated_at, ldna self_submitted_at/confirmed_at, notification read_at — were stamped in UTC and now read eight hours early. The user chose not to shift them (all from the setup period). Date-only columns (training dates, eligibility validity, birthdays) were never affected. Do not "fix" the old rows without asking.
