---
description: Restyle a page or component's presentation without changing its behaviour or Livewire wiring.
argument-hint: <file-or-page> [direction, e.g. "denser", "calmer", "match the reports pages"]
---

Restyle the target below. **Presentation only** — behaviour, Livewire wiring and data
must come out identical.

**Target and direction:** $ARGUMENTS

If no target was given, ask which page or component before doing anything. If a target
was given but no direction, propose two or three directions with a one-line rationale
each and let the user pick — do not guess and start editing.

## Read first

1. `.claude/skills/ux-design/references/design-contract.md` — tokens, Flux free-tier
   inventory, the status-colour convention, and where the recorded traps live.
2. `.claude/skills/ux-design/references/patterns.md` — the shape the target should
   keep. A restyle refines a pattern; it does not swap one pattern for another.
3. The target file in full, plus every component it renders.
4. Two or three sibling files in the same area, so the result stays consistent with
   its neighbours rather than becoming a one-off.

## Pulling a direction from the plugin data

Only when the user's direction calls for a palette, a type pairing or a named
aesthetic. Resolve the path without pinning a version:

```bash
UXPM=$(ls -d ~/.claude/plugins/cache/ui-ux-pro-max-skill/ui-ux-pro-max/*/.claude/skills/ui-ux-pro-max | sort -V | tail -1)
```

- `$UXPM/data/colors.csv` — 192 palettes by product type. This app is an internal
  government HR tool: look at the SaaS, dashboard and enterprise rows, not consumer ones.
- `$UXPM/data/typography.csv` — 74 pairings with Google Fonts URLs.
- `$UXPM/data/styles.csv` — 88 styles with effects and a "Do Not Use For" column. Read
  that column before proposing one.

Fields contain commas, so parse with `node`, not `cut`. Python is not installed on
this machine — do not try `search.py`.

**A palette or font from that data is a proposal, not a decision.** A new font is a
dependency change and needs the user's approval. New colours belong in the `@theme`
block of `resources/css/app.css` as tokens, never inline in Blade.

## Rules for the edit

- Change classes, spacing, sizing, ordering and wrapper markup. Do **not** change
  `wire:model`, `wire:click`, `wire:submit`, method names, computed properties,
  `#[Url]` attributes, query logic, or authorization.
- Colours come from `app.css` tokens or `flux:badge`'s `color` prop. No raw hex in Blade.
- Do not pass a class to override a Flux component's own classes — it loses silently.
  Replace the component with a plain element you control instead.
- Keep `truncate` on a fixed-width wrapper, never on a `flux:link` or a table cell.
- Keep every user-facing string `__()`-wrapped.
- Keep the mobile-first stack: `flex flex-col gap-3 lg:flex-row` for toolbars,
  `grid gap-4 md:grid-cols-2` inside modals.
- Do not add a JS library, a font, or `flux-pro` without asking.
- Do not restyle a screen into a lower contrast than it had. Check both themes — the
  accent tokens invert under `.dark`.

## Finish

1. Run `ux-audit` against the file you changed and fix anything the restyle introduced.
2. If any PHP changed, run `vendor/bin/pint --dirty --format agent`.
3. Report as a short list: what changed, why, and anything you chose not to do.
4. Tell the user to run `npm run dev` or `npm run build` to see it, since Tailwind
   classes that are new to the project need a rebuild.
5. Do not run `git commit` — per `.ai/rules/general.md` the user commits. Offer a
   one-sentence commit message if they want one.
