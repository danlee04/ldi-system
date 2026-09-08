---
paths:
  - 'app/Enums/**'
---

# Enums

## Write enums by hand — make:enum puts them in the wrong place
`php artisan make:enum UserRole` writes to `app/UserRole.php`, not `app/Enums/`. Passing `make:enum Enums/UserRole` works only while `app/Enums` does not exist yet — once it does, it nests into `app/Enums/Enums/`.

Create enum files by hand in `app/Enums/` with namespace `App\Enums`. Every enum here is backed by a snake_case string and carries a `label(): string`.
