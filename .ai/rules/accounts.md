---
paths:
  - 'app/Actions/Users/**'
  - app/Models/User.php
  - 'resources/views/pages/setup/⚡users.blade.php'
---

# Accounts

## email_verified_at is not fillable — stamp it with forceFill
User's #[Fillable] lists name, email, role, is_active, can_manage_calendar and password only. Passing email_verified_at through create()/update()/updateOrCreate() is dropped without an error.

SaveUserAccount stamps it with forceFill after the account is created, because the admin makes an account in front of the person rather than mailing them a link. This matters more than it looks: every route in routes/web.php and routes/settings.php sits behind the 'verified' middleware, and the model still has a commented-out MustVerifyEmail import. Implement that interface and every admin-made account would be locked out of the whole app if the stamp were silently discarded.

## An employee holds at most one account
SaveUserAccount::linkEmployee releases whoever held the account before writing the new link, so moving an account to a different person never leaves two employees pointing at the same sign-in. Setting the employee to none releases them too.
