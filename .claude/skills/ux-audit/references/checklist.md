# UX audit checklist

Distilled from the 119 rows of `ui-ux-pro-max/data/ux-guidelines.csv`, filtered to
what applies to an internal Laravel/Livewire/Flux admin tool and rewritten in terms
of this codebase. Rules the plugin has no knowledge of — the Flux traps and this
app's own conventions — are the `P` block.

Dropped from the source data as not applicable: native-mobile-only rules (haptics,
pull-to-refresh, tap delay, mobile keyboards), VisionOS spatial UI, AI-interaction
disclaimers, marketing-site rules (smooth scroll, deep linking, third-party scripts),
and build-pipeline rules (code splitting, bundle size) that Vite already handles.

Cite the ID in every finding. Severity is the source severity unless this app's
context changes it, which is noted where it happens.

---

## A — Accessibility

| ID | Sev | Check | How it fails here |
| --- | --- | --- | --- |
| A1 | Critical | Text survives 200% zoom and narrow widths; no fixed-height box clips it | A fixed `h-*` on a card or cell that holds variable text |
| A2 | High | Status is never conveyed by colour alone | A `flux:badge` whose only distinction between states is `color="green"` vs `color="red"` with identical text shape — add a word or icon |
| A3 | High | Every icon-only control has an accessible name | `<flux:button icon="trash" />` with no `aria-label` and an empty slot |
| A4 | High | Text contrast >= 4.5:1 in **both** themes | `--color-accent` inverts under `.dark`; a hardcoded `text-zinc-400` on `bg-zinc-50` passes light and fails dark |
| A5 | High | Every input has a visible, associated label | A `flux:input` given `:placeholder` but no `:label` |
| A6 | High | Validation errors sit next to their field and are announced | Errors rendered only as a toast, or only at the top of a form |
| A7 | High | Full keyboard operation, tab order matching visual order | A `<div wire:click>` acting as a button — not focusable, not Enter-activatable |
| A8 | High | Focus stays visible; the sticky sidebar/header does not cover it | Focus ring hidden behind `flux:sidebar sticky` when tabbing a long table |
| A9 | High | Pointer targets >= 24x24 CSS px (WCAG 2.2 AA), or spaced to compensate | Row action buttons at `size="sm"` packed with `gap-1` — measure, do not assume |
| A10 | High | Any drag interaction has a single-pointer alternative | Drag-to-reorder with no move-up/move-down control |
| A11 | Critical | Authentication allows paste and password managers | A hand-rolled OTP field that blocks paste; `flux:otp` is fine |
| A12 | Critical | Interactive chips/pills are real buttons with a name and state | A clickable `<div>` styled as a pill |
| A13 | High | Async count/badge changes announce meaningful status, not a bare number | The notification bell count updating with no `aria-live` context |
| A14 | Medium | Heading levels descend without skipping; each page has one `h1` | `flux:heading size="xl"` styles size but `level` sets the tag — check `level`, not `size` |
| A15 | Medium | Decorative icons are hidden from assistive tech; meaningful ones are described | An icon carrying the only meaning in a cell |

## F — Forms and feedback

| ID | Sev | Check | How it fails here |
| --- | --- | --- | --- |
| F1 | High | Submit gives feedback: busy state, then success or error | A `wire:submit` with no `wire:loading` and no `Flux::toast` on success |
| F2 | Medium | Empty states say what to do next, not just that there is nothing | `{{ __('No employees found.') }}` alone in a spanning cell — offer the create action when the user may create |
| F3 | High | Buttons cannot be double-submitted | `wire:click` on a mutating method with no `wire:loading.attr="disabled"` |
| F4 | High | Failures surface — no silent catch | An action that swallows an exception and closes the modal anyway |
| F5 | High | Destructive actions are confirmed | Enforced by `.ai/rules/pages.md`; also flag a mutating action that fires straight from a row |
| F6 | Medium | Required fields are marked | `required` present on the Flux control, not only in the PHP rules |
| F7 | Medium | Long lists and slow filters show progress | A `wire:model.live` filter over a big table with no `wire:loading` indicator |
| F8 | Medium | Errors offer a recovery path, not just a statement | "Something went wrong" with no next step |
| F9 | Medium | Toasts auto-dismiss and are not the only record of an important result | A rejection reason shown once in a toast and nowhere else |
| F10 | High | A failed multi-field submit gets a focusable error summary **and** inline errors | Long form modals where the first invalid field is scrolled out of view |
| F11 | Medium | The user is not asked twice for what the app already knows | Re-entering a division the record already carries |

## I — Interaction

| ID | Sev | Check | How it fails here |
| --- | --- | --- | --- |
| I1 | High | Visible focus ring on every control, including inside modals | A custom `<button>` replacing a Flux component that drops the ring |
| I2 | Medium | Disabled state is visually distinct and non-interactive | Styled dim but still clickable |
| I3 | Medium | Hover is never the only way to reach information or an action | `title="..."` as the sole route to truncated text — keyboard and touch users cannot reach it (see P3) |
| I4 | Medium | Transitions are ~150-250ms and respect `prefers-reduced-motion` | A `transition-colors` with no reduced-motion guard on a large surface |
| I5 | High | State correctness never depends on `transitionend`/`animationend` | Rapid toggles leaving a control stuck mid-state |

## L — Layout and responsive

| ID | Sev | Check | How it fails here |
| --- | --- | --- | --- |
| L1 | High | No horizontal page scroll at 320px | A wide `flux:table` that pushes the body rather than scrolling in its own container |
| L2 | Medium | Wide tables scroll inside themselves or restack | Nine-column tables on a phone |
| L3 | High | Content does not jump when async state resolves | A `wire:loading` block that replaces content of a different height |
| L4 | High | Long unbroken tokens (emails, IDs, URLs) wrap instead of overflowing | An email column with no `overflow-wrap` |
| L5 | Medium | Filters and toolbars stack on mobile, go horizontal at `lg:` | The `flex flex-col gap-3 lg:flex-row` pattern — flag toolbars that skip it |
| L6 | High | Body text >= 16px; nothing under 12px without cause | `text-[10px]` is used deliberately for the notification count only |
| L7 | Medium | Z-index is managed — modals, dropdowns, sidebar, toasts do not fight | Custom `z-*` next to Flux's own layering |
| L8 | Medium | Print output is sane where the app is printed | The sidebar carries `print:hidden`; reports should too |

## C — Content and typography

| ID | Sev | Check | How it fails here |
| --- | --- | --- | --- |
| C1 | Critical | Essential text — headings, actions, errors, distinguishing names — is never clipped with no way to read it in full | A truncated name that is the only way to tell two records apart |
| C2 | High | A badge or pill label stays on one line and discloses truncation accessibly | A wrapping badge in a narrow column |
| C3 | Medium | Truncation is deliberate: fixed `w-*` on a wrapper, full text reachable | Widths in use are `w-56`, `w-36`, `w-28` — flag a new arbitrary width |
| C4 | Medium | Dates and numbers are formatted consistently | `d M Y` is this app's date format; flag a second format |
| C5 | Medium | Line height is unitless and generous enough to read | A fixed `leading-[14px]` on prose |
| C6 | Low | No lorem ipsum or placeholder copy shipped | `placeholder-pattern` left in a real page |

## N — Navigation

| ID | Sev | Check | How it fails here |
| --- | --- | --- | --- |
| N1 | Medium | The current nav item is marked | `:current="request()->routeIs('...')"` present and matching the route pattern |
| N2 | Medium | List state — filters, page, sort — is linkable and survives refresh | `#[Url]` on filter properties |
| N3 | Medium | Filtering resets to page 1 | `updated()` calling `resetPage()` |
| N4 | Low | Deep pages give a way back | Breadcrumbs or a titled heading on show pages |

## P — Project conventions

Not in the plugin's data. These come from `.ai/rules` and from this app's own code;
they are the findings a generic UX tool cannot produce.

| ID | Sev | Check | Source |
| --- | --- | --- | --- |
| P1 | High | No `flux-pro` component is used — only the free-tier inventory | `.ai/rules/views.md` |
| P2 | High | No override passed against a Flux component's own classes | `.ai/rules/views.md` |
| P3 | High | `truncate` sits on a wrapping `div` with a fixed `w-*`, not on `flux:link` or a cell | `.ai/rules/views.md` |
| P4 | High | Mutating actions go through a `flux:modal`; the public method stays independently callable | `.ai/rules/pages.md` |
| P5 | Medium | Modals are `grid gap-4 md:grid-cols-2` at `md:w-5xl` or `md:w-7xl` | `.ai/rules/pages.md` |
| P6 | Medium | Modal closes only after success; stale field state reset on open | `.ai/rules/pages.md` |
| P7 | High | Colours come from tokens or the badge convention — no raw hex, no off-ramp Tailwind colour | design contract |
| P8 | Medium | User-facing strings are `__()`-wrapped | design contract |
| P9 | Medium | A Livewire page renders one root element and is not wrapped in a layout | `.ai/rules/pages.md` |
| P10 | Medium | Icon-only `flux:button` carrying a badge passes `square` explicitly | `.ai/rules/components.md` |
| P11 | Medium | Status badges follow green/red/amber/zinc for approved/rejected/pending/neutral | design contract |
| P12 | Medium | `:colspan` on an empty-state row matches the conditional column count above it | `patterns.md` section 7 |
