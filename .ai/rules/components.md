---
paths:
  - 'resources/views/components/**'
---

# Components

## Non-routable Livewire components go in resources/views/components
A Livewire 4 component that is not a page lives at resources/views/components/⚡name.blade.php and is used as <livewire:name /> — no namespace prefix, unlike the pages:: ones. Livewire registers resources/views/components as a component location by default, which is also why make:livewire --sfc writing there breaks a page but is right for these.

The same directory holds plain Blade components (x-name), so the ⚡ prefix is what tells the two apart.

Flux traps seen here: flux:button only treats itself as square when its slot is empty, so an icon-only button that carries a badge needs `square` passed explicitly, and the badge needs absolute positioning to stay out of the flow.
