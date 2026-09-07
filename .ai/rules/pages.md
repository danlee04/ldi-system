---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Livewire pages go in resources/views/pages, not via make:livewire
Routable Livewire 4 single-file components live at resources/views/pages/<folder>/⚡<name>.blade.php and are referenced as `pages::<folder>.<name>` (e.g. Route::livewire('employees', 'pages::employees.index')).

Do NOT use `php artisan make:livewire <name> --sfc` for these — it writes to resources/views/components/pages/... which is a different namespace and will not resolve. Create the file by hand; copy the shape from resources/views/pages/settings/⚡profile.blade.php.

Do not wrap a page in <x-layouts::app>. Livewire's default `component_layout` is `layouts::app` and Route::livewire() applies it automatically; the layout already provides <flux:main>. Pages render a single root element.
