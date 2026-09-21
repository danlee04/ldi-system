---
paths:
  - 'resources/views/components/**'
  - resources/views/components/idle-logout.blade.php
---

# Components

## Non-routable Livewire components go in resources/views/components
A Livewire 4 component that is not a page lives at resources/views/components/⚡name.blade.php and is used as <livewire:name /> — no namespace prefix, unlike the pages:: ones. Livewire registers resources/views/components as a component location by default, which is also why make:livewire --sfc writing there breaks a page but is right for these.

The same directory holds plain Blade components (x-name), so the ⚡ prefix is what tells the two apart.

Flux traps seen here: flux:button only treats itself as square when its slot is empty, so an icon-only button that carries a badge needs `square` passed explicitly, and the badge needs absolute positioning to stay out of the flow.

## Idle sign-out lives in the browser, because the bell keeps the session alive
The notification bell's wire:poll.60s touches the session every minute, so SESSION_LIFETIME alone never treats an open tab as idle. x-idle-logout (persisted in layouts/app/sidebar.blade.php) watches real input, shares one clock across tabs via localStorage, warns in the last minute, and posts logout with reason=idle; App\Http\Responses\LogoutResponse turns that into a login-page notice. The minutes are config('session.idle_timeout'), deliberately not read from .env. "Remember me" was removed from the login form because its cookie would sign a closed browser back in past the limit.
