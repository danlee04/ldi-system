# Design contract

The single source of truth for how this app's UI is built. `ux-audit`, `ux-review`
and `/restyle` all read this file before they judge anything. Update it here — do
not restate it in the other skills.

## Stack

| Concern | What this app uses |
| --- | --- |
| Server UI | Livewire 4 single-file components (SFC), volt-style `new class extends Component` |
| Component kit | `livewire/flux` **free tier only** — `flux-pro` is NOT installed |
| CSS | Tailwind v4, `@theme` block in `resources/css/app.css` |
| Icons | Heroicons via `<flux:icon.*>` / the `icon` prop |
| Dark mode | `@custom-variant dark (&:where(.dark, .dark *))`, driven by `@fluxAppearance` |
| Toasts | `Flux::toast(variant: 'success', text: __('...'))` |
| Modals | `Flux::modal('name')->show()` / `->close()` |

Adding `flux-pro`, a component library, or a font is a dependency change and needs
the user's approval first.

## Where things live

```
resources/views/pages/<folder>/⚡<name>.blade.php   routable Livewire page → pages::<folder>.<name>
resources/views/components/⚡<name>.blade.php       non-routable Livewire → <livewire:name />
resources/views/components/<name>.blade.php        plain Blade → <x-name />
resources/views/layouts/app/sidebar.blade.php      the <html> shell, sidebar nav
resources/views/partials/head.blade.php            meta, @fonts, @vite, @fluxAppearance
resources/css/app.css                              @theme tokens
```

The `⚡` prefix is what distinguishes a Livewire component from a plain Blade one in
the same directory. Pages render a **single root element** and must not be wrapped in
`<x-layouts::app>` — `Route::livewire()` applies the layout already.

## Available Flux components (free tier)

`input` `select` `textarea` `checkbox` `radio` `switch` `button` `table` `modal`
`badge` `card` `callout` `pagination` `navlist` `sidebar` `heading` `text` `field`
`separator` `dropdown` `menu` `toast` `tooltip` `breadcrumbs` `avatar` `otp`
`progress` `skeleton`

**Absent:** there is no `flux:date-picker` and no `flux:calendar`. Use
`<flux:input type="date">`. Before using any component not already used in this repo,
check `vendor/livewire/flux/stubs/resources/views/` for its stub — if there is no
stub, it is Pro and unavailable.

## Colour and type tokens

Defined in `resources/css/app.css`, not in markup:

- A full `--color-zinc-50 … --color-zinc-950` neutral ramp.
- `--color-accent`, `--color-accent-content`, `--color-accent-foreground`, which
  **swap under `.dark`** — accent is `neutral-800` in light and `white` in dark.
- `--font-sans: 'Instrument Sans', …`

Use `text-[var(--color-accent-content)]` and the `zinc-*` utilities. A raw hex or an
off-ramp Tailwind colour (`text-blue-600`, `bg-slate-100`) in a Blade file is a
finding — the two exceptions are `flux:badge`'s own `color` prop, which takes named
colours, and semantic status colours listed below.

### Status colour convention

Established by `x-training-status`, `x-attendee-count` and `x-eligibility-expiry`:

| Meaning | Badge colour |
| --- | --- |
| Approved / met / healthy | `green` |
| Rejected / overdue / zero | `red` |
| Pending / warning / partial | `amber` |
| Neutral / not applicable | `zinc` |

Follow it. Introducing a fourth palette for the same idea is a consistency finding.

## Recorded traps — read before touching views

These are settled and load-bearing. Do not re-derive them:

- `.ai/rules/views.md` — Flux free tier; the `truncate`-on-`flux:link` trap; why
  `max-w-*` on a `flux:table.cell` does nothing; why you replace a Flux component
  rather than override its classes; the `flux:button` slot/icon centring bug.
- `.ai/rules/components.md` — the `⚡` naming split; icon-only buttons need `square`
  passed explicitly when they carry a badge.
- `.ai/rules/pages.md` — every mutating action goes through a `flux:modal`, never a
  bare row button; modals are two-column `grid gap-4 md:grid-cols-2`; `md:w-5xl` for
  short forms, `md:w-7xl` for full ones.

## Plugin data (ui-ux-pro-max)

Installed at project scope. `search.py` needs Python, which is **not** installed on
this machine, so read the CSVs directly. Resolve the path without pinning a version:

```bash
UXPM=$(ls -d ~/.claude/plugins/cache/ui-ux-pro-max-skill/ui-ux-pro-max/*/.claude/skills/ui-ux-pro-max | sort -V | tail -1)
```

| File | Holds | Read it for |
| --- | --- | --- |
| `data/ux-guidelines.csv` | 119 rules: Category, Issue, Platform, Do, Don't, Severity | already distilled into `ux-audit/references/checklist.md` — go to the CSV only for a rule the checklist omits |
| `data/colors.csv` | 192 palettes keyed by product type | `/restyle` with a colour direction |
| `data/typography.csv` | 74 font pairings + Google Fonts URLs | `/restyle` with a type direction |
| `data/styles.csv` | 88 visual styles, effects, anti-patterns | `/restyle` with a named aesthetic |
| `references/pro-rules.md` | polish checklist | final pass before delivery |

Its `data/stacks/laravel.csv` is **not** authoritative here: it is Livewire 3-era,
covers Inertia (unused in this app) and knows nothing about Flux. This file and
`.ai/rules` outrank it every time.

Parse a CSV with quoted fields using `node`, not `cut` — several fields contain commas.
