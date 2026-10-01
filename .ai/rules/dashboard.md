---
paths:
  - 'resources/views/components/dashboard/**'
---

# Dashboard

## Dashboard panels are composed, not hand-rolled
A list block on the dashboard is x-dashboard.panel (card + heading + subtitle + either a count pill or an "open the page" link), with x-dashboard.empty-state for the nothing-to-show case and x-dashboard.list-row for each line (icon plate, title, subtitle, optional note, trailing slot). Six panels used to repeat that markup by hand and had already drifted apart. x-dashboard.figures takes the KPI cards as an array and a columns prop (3 or 4); build that array in a #[Computed] on the page, never inline in the Blade attribute.
