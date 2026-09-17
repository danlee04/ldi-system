---
paths:
  - 'app/Actions/Employees/**'
  - app/Models/Employee.php
  - 'resources/views/pages/employees/**'
---

# Employees

## One plantilla item, one person — enforced in validation, not by an index
employees.item_number is the plantilla item and is unique among people still on the roster. Two employees can hold the same position under different items — an Administrative Assistant II in HR sits in OSEC-DOHB-ADAS2-203-2014 while another in Billing and Claims sits in OSEC-DOHB-ADAS2-205-2014 — but no item is held by two at once.

The rule lives in the roster form's validation, scoped with ->whereNull('deleted_at'), and deliberately NOT as a unique index. Employee soft-deletes, so a retired person's row keeps their item number; an index would hold that seat shut against whoever fills it next, and would fail as a 500 rather than as a message on the field.
