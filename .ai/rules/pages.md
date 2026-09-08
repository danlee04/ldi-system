---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Livewire pages go in resources/views/pages, not via make:livewire
Routable Livewire 4 single-file components live at resources/views/pages/<folder>/⚡<name>.blade.php and are referenced as `pages::<folder>.<name>` (e.g. Route::livewire('employees', 'pages::employees.index')).

Do NOT use `php artisan make:livewire <name> --sfc` for these — it writes to resources/views/components/pages/... which is a different namespace and will not resolve. Create the file by hand; copy the shape from resources/views/pages/settings/⚡profile.blade.php.

Do not wrap a page in <x-layouts::app>. Livewire's default `component_layout` is `layouts::app` and Route::livewire() applies it automatically; the layout already provides <flux:main>. Pages render a single root element.

## Actions go in a confirmation modal, never a bare button
Any action that changes or destroys data — approve, reject, delete, designate a head — must be confirmed in a `<flux:modal>`, not fired straight from a row button. The row button only opens the modal; the modal carries the context (what record, what decision), any remarks or reason field, and the confirm button.

Open with `Flux::modal('name')->show()` and close with `->close()` only after the action succeeds, so a validation error leaves the modal open with the user's input intact. Reset stale field state when opening, or the previous attempt's remarks leak into the next one.

Keep the plain public methods (`approve($id)`, `reject($id)`) callable on their own — the modal is UI on top of them, and tests drive the methods directly.

## Modals are two columns and wide, not a narrow stack
Lay modal bodies out as `grid gap-4 md:grid-cols-2` rather than one stacked column, and size the modal to fit it: `md:w-2xl` for a short one, `md:w-4xl` for a full form. Fields that read badly when halved — a long title, an employee picker, a separator — take `md:col-span-2`.

For a confirmation modal, put the record's details in the left column and the remarks or reason field in the right, so the approver sees what they are deciding on without leaving the modal.
