---
paths:
  - 'resources/views/errors/**'
---

# Errors

## Error pages need no session, database or Livewire
Every page in resources/views/errors wears x-layouts::error (the sign-in ground and split card, seals via x-seals). Keep that layout free of auth(), queries, Livewire and @csrf: a mistyped address 404s before the session starts, and a 500 can be the database itself. Never print $exception->getMessage() on 404 or 5xx (it names models and SQL); 403 prints it only when it is a reason written for people, not "This action is unauthorized.". Laravel's own 404/500/503 pages win over 4xx/5xx, so those three need files of their own. Test them with app.debug off: with it on, a status without a page falls to the debug screen, which quotes the test file and makes assertSee pass.
