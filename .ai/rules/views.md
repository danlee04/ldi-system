---
paths:
  - 'resources/views/**'
---

# Views

## Flux free tier only — no Pro components
Only `livewire/flux` (free) is installed; `flux-pro` is not. Adding it is a dependency change and needs approval.

Available: input, select, textarea, checkbox, radio, switch, button, table, modal, badge, card, callout, pagination, navlist, sidebar, heading, text, field, separator, dropdown, menu, toast, tooltip, breadcrumbs, avatar, otp, progress, skeleton.

Notably absent: there is no `flux:date-picker` or `flux:calendar`. Use `<flux:input type="date">` for dates. Check vendor/livewire/flux/stubs/resources/views/flux before using a component you have not used here before.

## Truncate on a wrapper, never on flux:link or a table cell
Two things silently defeat `truncate` in a table:

1. `flux:link` always adds `inline` to its own classes. Passing `class="block w-44 truncate"` leaves both `inline` and `block` on the same `<a>`, and an inline element ignores width — the text renders full length. Put the width and `truncate` on a wrapping `<div>` and leave the link plain inside it.

2. `max-w-*` on a `<flux:table.cell>` does nothing. An auto-layout HTML table sizes cells to their content and ignores max-width, so the column still stretches. Use a fixed `w-*` on the inner element, not `max-w-*` on the cell.

Carry the full text in a `title` attribute on the wrapper so it is readable on hover.
