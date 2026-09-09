# Page patterns

The shapes this app already uses. Copy the nearest one rather than inventing a
layout. Every snippet below is reduced from real code in this repo — the cited file
is the worked example to open.

## 1. Index page — filter bar over a paginated table

Worked example: `resources/views/pages/employees/⚡index.blade.php`

```blade
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Employees') }}</flux:heading>
        @if ($this->canManage)
            <flux:button variant="primary" wire:click="createEmployee">{{ __('Add employee') }}</flux:button>
        @endif
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <flux:input size="sm" class="lg:flex-1" wire:model.live.debounce.300ms="search"
            :placeholder="__('Search name or employee number')" />
        <flux:select size="sm" class="lg:w-52" wire:model.live="divisionId"> … </flux:select>
    </div>

    <flux:table :paginate="$this->employees"> … </flux:table>
</div>
```

Rules this pattern carries:

- `space-y-6` between the header, the filter bar and the table. Not `gap`, not `mb-*`.
- Filters stack on mobile and go horizontal at `lg:`. Text filters take `lg:flex-1`;
  selects take a fixed `lg:w-*` sized to their longest option.
- Every filter is `size="sm"`, `wire:model.live`, and debounced only if it is free text.
- Filter state is `#[Url]` so a filtered list is linkable and survives a refresh.
- `updated()` calls `resetPage()` — filtering while on page 4 must not strand the user.
- Pagination is `:paginate="$this->rows"` on `flux:table`, never a separate control.
- The primary action sits top-right and is gated by the same check as the row actions.

## 2. Table cells — truncation

```blade
<flux:table.cell>
    <div class="w-56 truncate" title="{{ $employee->full_name }}">
        <flux:link :href="route('employees.show', $employee)" wire:navigate>
            {{ $employee->listing_name }}
        </flux:link>
    </div>
</flux:table.cell>
```

The fixed width and `truncate` go on a wrapping `<div>`; the link stays plain inside.
`max-w-*` on the cell does nothing, and `truncate` on the `flux:link` itself is
defeated by the `inline` class Flux adds. See `.ai/rules/views.md`.

Widths in use: `w-56` names, `w-36` section/position, `w-28` short codes. Reuse them.

## 3. Row actions

```blade
<flux:table.cell>
    <div class="flex gap-1">
        <flux:button size="sm" variant="ghost" wire:click="editEmployee({{ $employee->id }})">{{ __('Edit') }}</flux:button>
        <flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $employee->id }})">{{ __('Delete') }}</flux:button>
    </div>
</flux:table.cell>
```

`ghost` for the non-destructive action, `danger` for the destructive one. The
destructive button only **opens a modal** — it never mutates. Keep the plain public
method (`deleteEmployee()`) callable on its own so tests drive it directly.

## 4. Confirmation modal

Worked shape, per `.ai/rules/pages.md`:

```blade
<flux:modal name="employee-delete" class="md:w-5xl">
    <div class="grid gap-4 md:grid-cols-2">
        {{-- left: what is being decided on --}}
        {{-- right: remarks / reason field --}}
    </div>
</flux:modal>
```

- `md:w-5xl` for a confirmation or short form, `md:w-7xl` for a full record form.
  Narrower collapses the two-column grid back to one on a laptop, which defeats it.
- Fields that read badly halved — a long title, an employee picker, a separator —
  take `md:col-span-2`.
- Close with `->close()` **only after the action succeeds**, so a validation error
  leaves the modal open with the user's input intact.
- Reset stale field state when opening, or the previous attempt's remarks leak in.
- Confirm with `Flux::toast(variant: 'success', text: __('...'))`.

## 5. Form modal

`grid gap-4 md:grid-cols-2` inside a `<form wire:submit="save">`, wrapped in
`space-y-6`. Every control is a `flux:input` / `flux:select` / `flux:switch` with a
`:label` prop — the label is a prop, never a placeholder, never a bare `<label>`.
Mark required fields with the `required` attribute so Flux renders the indicator.

## 6. Status badges

Small plain Blade components, one file each, that map a domain value onto the badge
colour convention in the design contract:

```blade
@props(['date'])

@if ($date === null)
    <flux:badge color="zinc">{{ __('No expiry') }}</flux:badge>
@elseif ($date->isPast())
    <flux:badge color="red">{{ $date->format('d M Y') }}</flux:badge>
@else
    <flux:badge color="green">{{ $date->format('d M Y') }}</flux:badge>
@endif
```

Worked examples: `x-training-status`, `x-attendee-count`, `x-eligibility-expiry`.
When you add one, give the badge a **text** distinction too, not colour alone — see
checklist item A2.

## 7. Empty state

Currently a single cell spanning the table:

```blade
@empty
    <flux:table.row>
        <flux:table.cell :colspan="$this->canManage ? 9 : 8">{{ __('No employees found.') }}</flux:table.cell>
    </flux:table.row>
@endforelse
```

Keep the `:colspan` expression in step with the conditional columns above it, or the
empty row misaligns. A new empty state should also offer the next action (see F2).

## 8. Everything user-facing is translated

`{{ __('...') }}` in text, `:label="__('...')"` and `:placeholder="__('...')"` as
bound props. A bare English string in markup is a finding.
