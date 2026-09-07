---
paths:
  - 'resources/views/**'
---

# Views

## Flux free tier only — no Pro components
Only `livewire/flux` (free) is installed; `flux-pro` is not. Adding it is a dependency change and needs approval.

Available: input, select, textarea, checkbox, radio, switch, button, table, modal, badge, card, callout, pagination, navlist, sidebar, heading, text, field, separator, dropdown, menu, toast, tooltip, breadcrumbs, avatar, otp, progress, skeleton.

Notably absent: there is no `flux:date-picker` or `flux:calendar`. Use `<flux:input type="date">` for dates. Check vendor/livewire/flux/stubs/resources/views/flux before using a component you have not used here before.
