---
paths:
  - config/app.php
  - config/fortify.php
---

# Config

## The app runs on Asia/Manila; pre-2026-09-21 timestamps are UTC
config/app.php timezone is Asia/Manila (was UTC until 2026-09-21). Timestamps written before then — created_at/updated_at, ldna self_submitted_at/confirmed_at, notification read_at — were stamped in UTC and now read eight hours early. The user chose not to shift them (all from the setup period). Date-only columns (training dates, eligibility validity, birthdays) were never affected. Do not "fix" the old rows without asking.

## No password reset by email — HR sets forgotten passwords
Features::resetPasswords() is off (2026-09-21): MAIL_MAILER is log, so a reset link would never arrive. HR sets a new password in Setup → Users. The password-reset tests skip themselves through skipUnlessFortifyHas; keep them rather than deleting them, so they come back if mail is ever set up. The forgot-password and reset-password views are unused while the feature is off.
