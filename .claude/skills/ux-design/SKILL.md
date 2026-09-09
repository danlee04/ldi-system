---
name: ux-design
description: Use when building or reshaping any user-facing screen in this app — a new Livewire page, a modal, a table, a form, a filter bar, a status badge, a dashboard panel, or an empty state. Establishes the design contract this codebase follows: Flux free-tier component inventory, the app.css token set, the index/modal/table page shapes, the status-colour convention, and the recorded Flux traps. Triggers on "new page", "add a screen", "build a form", "add a modal", "add a column", "make a component", "the layout", or any edit under resources/views. Read it before writing markup, not after.
---

# UX design

How UI gets built in this app. Read the contract first, copy the nearest existing
pattern, then write.

## Before writing any markup

1. Read `references/design-contract.md` — the stack, the Flux free-tier inventory,
   the tokens, and where the settled traps are recorded. This is shared with
   `ux-audit`, `ux-review` and `/restyle`; it is the one file to update when the
   stack changes.
2. Read `references/patterns.md` and find the shape closest to what you are building.
3. Open the worked example it names and copy that file's structure.

Per `CLAUDE.md`, also open the `.ai/rules` files whose globs cover the path you are
about to touch. The contract points at which ones matter for views.

## The five decisions

Every new screen answers these. Get them from the contract and the patterns file —
do not improvise:

| Decision | Where the answer is |
| --- | --- |
| Which component renders this? | Flux free-tier inventory. If it is not in the list and has no stub in `vendor/livewire/flux/stubs/`, it is Pro and unavailable — build it from a plain element you fully control. |
| What shape is the page? | `patterns.md` §1 index, §4 confirmation modal, §5 form modal |
| What colour is this? | Tokens and the status-colour table in the contract. Never a raw hex. |
| Does this action mutate data? | If yes it goes through a `flux:modal`. Always. `.ai/rules/pages.md` |
| Is the string user-facing? | Then it is `__()`-wrapped. `patterns.md` §8 |

## Non-negotiables

These come from bugs already paid for in this repo. Breaking one is a regression,
not a style preference:

- **Never override a Flux component's own classes.** Flux merges its classes onto the
  same element and stylesheet order decides, so your override loses silently with no
  error. When you need different behaviour, use a plain element you control.
- **Truncate on a wrapper, never on `flux:link` or a table cell.** Fixed `w-*` on an
  inner `<div>`, with the full text in a `title` attribute.
- **A destructive or mutating action never fires from a row button.** The button opens
  a modal; the modal carries the context and the confirm.
- **Close a modal only after the action succeeds**, so a validation error preserves input.
- **Pages render a single root element** and are never wrapped in `<x-layouts::app>`.
- **Adding a dependency needs approval** — that includes `flux-pro`, a font, and a JS
  component library.

## When the pattern does not exist yet

If nothing in `patterns.md` fits, say so before writing rather than bending an
existing shape past what it can carry. Propose the new shape, get agreement, build
it, then add it to `patterns.md` so the next screen inherits it.

If you settle something durable along the way — a trap, a constraint, a decision that
was expensive to work out — record it with the Boost `record-rule` tool against the
right glob, not in a comment.

## Checking your work

Run `/restyle` only for deliberate visual change. To check what you built, use
`ux-audit` on the file you touched; to check a whole branch before handing it over,
use `ux-review`. Do not skip straight to "done" — `ux-audit` on a single page is fast
and catches the colour-alone and empty-state misses that are easy to write.

If you changed PHP, run `vendor/bin/pint --dirty --format agent`. If a change is
behavioural, it needs a Pest test per `CLAUDE.md`.
