---
name: ux-audit
description: Use when asked to audit, assess, evaluate, or "check the UX" of screens that already exist in this app — one page, a feature area, or a whole-app sweep. Produces a severity-ranked findings report with file:line and a concrete fix for each, checked against a project-specific accessibility, forms, layout, content and Flux-convention checklist. Read-only — it never edits. Triggers on "audit", "UX review of the X page", "is this accessible", "what's wrong with this screen", "check the UI". For changes not yet committed use ux-review instead; for making the changes use ux-design or /restyle.
---

# UX audit

Systematic evaluation of screens that already exist. Output is a report, not edits.

## Scope first

Establish what is being audited before reading anything:

- **One page** — `resources/views/pages/<area>/⚡<name>.blade.php` plus every component it renders.
- **A feature area** — every page under one folder, plus their shared components.
- **The whole app** — 69 Blade files. Do not attempt this in one pass. Audit by area, report per area, and keep a running list of cross-cutting findings that repeat.

If the user names a route or a feature rather than a file, resolve it with
`php artisan route:list --except-vendor` first so you audit the file that actually serves it.

## Method

1. Read `../ux-design/references/design-contract.md` — the stack, tokens, Flux
   inventory and conventions the findings are measured against.
2. Read `references/checklist.md` — the A/F/I/L/C/N/P items, each with an ID.
3. Read the target file(s) **in full**, plus every `x-` and `<livewire:` component
   they render. A finding about a badge lives in the badge component, not the page.
4. Walk the checklist against what you read. Work top-down by severity band:
   Critical, then High, then Medium, then Low.
5. Verify each candidate finding against the actual source before reporting it —
   open the line, confirm the code says what you think. A finding you cannot point
   at is a guess, and guesses make the report worthless.

## What counts as a finding

A finding must name a specific line of this repo and a specific fix. If you cannot
write the fix as a concrete edit, it is an observation, not a finding — put it in the
notes section or drop it.

**Not findings:** anything the design contract explicitly sanctions; generic advice
that would apply to any web app; style preferences with no checklist item behind
them; anything already recorded as a settled decision in `.ai/rules`.

**Deliberate choices are not findings.** `text-[10px]` on the notification count and
the `title` attribute on truncation wrappers are both recorded decisions. If you
believe a recorded decision is wrong, say so once, separately, with the reasoning —
do not list it as a defect.

## Report format

Group by severity. Nothing else in the report.

```
## Critical

**C1 — Employee name truncated with no full-text path**
resources/views/pages/employees/⚡index.blade.php:373
The `w-56 truncate` wrapper carries `title="{{ $employee->full_name }}"`, which is
hover-only. Two employees sharing a truncated prefix are indistinguishable by
keyboard or on touch.
Fix: link the cell to the show page (already the case here) and confirm the show
page heading carries the full name — or widen to `w-72`, which fits the longest
name in the seeded data.

## High
...

## Notes
Observations without a concrete fix, and any recorded decision you want to challenge.
```

End with one line: how many findings at each severity, and which single fix buys the
most. No preamble, no "great job", no summary of what the page does.

## Boundaries

- **Never edit.** If the user wants the fixes applied, say so and offer `/restyle`
  for presentation-only changes or `ux-design` for structural ones.
- Do not run the app or take screenshots unless asked — this is a source audit.
- Do not audit PHP correctness, queries or authorization. That is `/code-review`.
  A missing `$this->authorize()` is out of scope here even when you notice it —
  mention it in Notes and move on.
- If a whole-app sweep turns up the same finding on five pages, report it once as a
  cross-cutting finding with the list of files, not five times.
