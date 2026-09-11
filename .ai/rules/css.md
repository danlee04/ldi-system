---
paths:
  - 'resources/css/**'
---

# Css

## A rule that overrides a Flux utility goes outside @layer
Flux's utilities live in Tailwind's `utilities` layer, and a later cascade layer beats an earlier one whatever the specificity. A rule written inside `@layer base` that sets the same property as a Flux class loses without a trace — the table inset in app.css did exactly that against `first:ps-0` until it was moved out.

So: a rule that only adds something Flux never sets (a shadow, a background) can sit in `@layer base`; a rule that changes something Flux already sets has to be unlayered, at the foot of app.css. Tables here also sit on a surface of their own now (`ui-table-scroll-area:has(> [data-flux-table])`), which is why their first and last columns need the inset Flux strips.
