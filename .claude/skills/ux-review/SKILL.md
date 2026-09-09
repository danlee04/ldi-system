---
name: ux-review
description: Use when reviewing changes that are not yet merged — the working tree, staged changes, or a branch — for UX regressions specifically. Checks only what the diff touched: hand-rolled components Flux already provides, mutating actions without a confirmation modal, missing wire:loading, raw hex instead of tokens, status by colour alone, the truncate-on-flux:link trap, unlabelled inputs. Triggers on "review my changes", "check this branch", "did I break the UI", "review before commit". Narrower than /code-review, which hunts correctness bugs; this one only looks at user experience and view conventions.
---

# UX review

Reviews **changed lines** for UX regressions. Not a full audit — if the user wants
every issue on a page including pre-existing ones, that is `ux-audit`.

## Get the diff

Default to the working tree plus staged changes against `main`:

```bash
git diff main...HEAD --stat
git diff main...HEAD -- 'resources/views/**' 'resources/css/**'
git diff --stat && git diff -- 'resources/views/**' 'resources/css/**'
```

If the user names a branch or a PR, diff that instead. If nothing under
`resources/views` or `resources/css` changed, say so and stop — there is nothing for
this skill to review.

## Method

1. Read `../ux-design/references/design-contract.md` for the conventions.
2. Read `../ux-audit/references/checklist.md` for the item IDs — cite them the same way.
3. For each changed hunk, read enough surrounding context to judge it. A diff that
   adds a table column may break the empty-state `:colspan` twenty lines below it,
   which the hunk alone will not show.
4. Report only what the diff introduced or made worse.

## The regression list

These are what actually breaks in this codebase. Check every one against the diff:

| # | Regression | Checklist |
| --- | --- | --- |
| 1 | A hand-rolled element where a free-tier Flux component exists | P1 |
| 2 | A class passed to override a Flux component's own classes — it loses silently | P2 |
| 3 | `truncate` on a `flux:link`, or `max-w-*` on a `flux:table.cell` | P3 |
| 4 | A mutating or destructive action fired straight from a row button | P4, F5 |
| 5 | A modal closed before the action succeeded, discarding input on a validation error | P6 |
| 6 | A raw hex or off-ramp Tailwind colour instead of a token | P7 |
| 7 | Status distinguished by badge colour alone | A2, P11 |
| 8 | A `flux:input`/`select` with `:placeholder` but no `:label` | A5 |
| 9 | A mutating `wire:click` or `wire:submit` with no busy state | F1, F3 |
| 10 | A new user-facing string not wrapped in `__()` | P8 |
| 11 | A `<div wire:click>` where a `<button>` belongs — no focus, no keyboard | A7, A12 |
| 12 | An icon-only control with no accessible name | A3 |
| 13 | A new table column that leaves the empty-state `:colspan` stale | P12 |
| 14 | A new filter without `#[Url]`, or `updated()` without `resetPage()` | N2, N3 |
| 15 | A colour that passes in light mode and fails in dark — the accent tokens invert | A4 |
| 16 | A new fixed width or height around variable text | A1, C3 |

## Report format

One section, findings ordered most severe first. Each finding is three lines:

```
**P3 — truncate on flux:link will not apply**
resources/views/pages/ldi/⚡index.blade.php:88 (+)
Flux adds `inline` to the link's own classes, so `block w-44 truncate` leaves both on
the anchor and the width is ignored. Move `w-44 truncate` to a wrapping div.
```

Mark each with `(+)` for a line the diff added or `(~)` for one it modified.

If the diff is clean, say so in one line and name what you checked. Do not manufacture
findings to look thorough — a clean review is a useful result.

## Boundaries

- Changed lines only. Pre-existing issues in a file the diff touched go in a short
  "pre-existing, not introduced here" list at the end, or are left for `ux-audit`.
- No correctness, query, or authorization review — that is `/code-review`.
- Report only; do not apply fixes unless the user asks. If they do, apply them and
  run `vendor/bin/pint --dirty --format agent` if any PHP changed.
- Per `.ai/rules/general.md`, never run `git commit` — hand the user a one-sentence
  commit message if they want one.
