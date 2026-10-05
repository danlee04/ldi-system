---
paths:
  - 'resources/views/components/dashboard/**'
---

# Dashboard

## Dashboard panels are composed, not hand-rolled
A list block on the dashboard is x-dashboard.panel (card + heading + subtitle + either a count pill or an "open the page" link), with x-dashboard.empty-state for the nothing-to-show case and x-dashboard.list-row for each line (icon plate, title, subtitle, optional note, trailing slot). Six panels used to repeat that markup by hand and had already drifted apart. x-dashboard.figures takes the KPI cards as an array and a columns prop (3 or 4); build that array in a #[Computed] on the page, never inline in the Blade attribute.

## The dashboard calendar keeps one height in every month
The user asked twice for the dashboard month not to change height when stepping between months. So x-calendar.month compact is a fixed h-124 that its 4, 5 or 6 weeks share, the dashboard asks BuildCalendarMonth for maxLanes: 2 (what does not fit shows as a "+N" by the day, titles on hover), and the legend is ActivityType::legend() — every kind, not just the ones on show. "Open the calendar" sits in the header beside the arrows and opens the month on show. Change the compact bar or day-number sizes and the cap together, or a six-week month clips.
