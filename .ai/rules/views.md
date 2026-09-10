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

## Do not fight a Flux component's own classes — replace it
A Flux component merges its own utility classes with yours onto the same element, and when both set the same property the stylesheet order decides, not the attribute order. Passing an override silently loses, with no error to notice.

Seen twice: `flux:link` always adds `inline`, so `class="block w-44 truncate"` never truncates; its `ghost` variant adds `hover:underline`, so `hover:no-underline` does not remove the underline.

When you need a different behaviour, use a plain element you fully control rather than an override. Modal triggers in tables are a plain `<button type="button">` styled with `text-[var(--color-accent-content)]` for the accent, `cursor-pointer` because a button does not get one, and `block w-full truncate text-left`.

Keep `flux:link` for real navigation — a link that changes the page keeps its underline, a button that opens a modal does not.

## A flux:button slot adds a span that shifts an icon-only button off centre
flux:button renders `<span>{{ $slot }}</span>` whenever it has an icon and a non-empty slot, and that span stays in the button's flex row with its `gap-2`. On a square icon-only button (h-8 w-8, a 20px icon) the extra gap pushes the icon about 4px off centre — even when the slot's own content is absolutely positioned, because the wrapper is not.

So do not put a notification count, dot or badge inside such a button. Leave the slot empty, wrap the whole trigger in `<div class="relative inline-flex">`, and layer the count over the corner as a sibling. The wrapper must be inline-flex so it shrink-wraps the button rather than the sidebar's width.

Also: flux:badge is a rounded-md pill with px-2 py-1, far too large for a 32px button. A plain span with `flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none` is the shape wanted. resources/views/components/⚡notifications.blade.php is the worked example.

## Tailwind never sees a class a template pieces together
`bg-{{ $type->color() }}-100` compiles to nothing. Tailwind scans the source for whole class names and cannot resolve an interpolated one, so the element renders unstyled with no error.

Write the full class strings out in a `match` on the enum and return them as one string — see `ActivityType::chipClasses()`, used by the calendar's day chips. `color()` stays separate for `flux:badge`, which takes a colour name as a prop rather than a class.

## Look at a screen before calling it done
Chrome is installed and can shoot a page headless — use it on anything visual before reporting it finished. Twice now a design has been called done while a table sat empty and a mask faded the button it was meant to sit behind.

Public page:
`"/c/Program Files/Google/Chrome/Application/chrome.exe" --headless=new --disable-gpu --hide-scrollbars --window-size=1440,900 --virtual-time-budget=4000 --screenshot=out.png "http://hr-training-laravel.test/login"`

A page behind auth needs a session, which headless has none of. Render it instead: a throwaway Pest test with `actingAs()` that writes `$this->get(route('dashboard'))->getContent()` to a file, then shoot `file:///that.html`. Vite emits absolute asset URLs, so the CSS still loads.
