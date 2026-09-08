---
paths:
  - 'app/Actions/Pds/**'
---

# Pds

## The PDS filler writes into the agency's own CS Form 212 template
storage/app/templates holds the real CSC workbook (CS Form No. 212, Revised 2026). FillPersonalDataSheet opens it and writes cells, so the download is the commission's own file — never rebuild the layout by hand.

Traps found while mapping it:
- The caption sits BELOW the box it labels, so an answer's address is one row above the words describing it.
- Every date on the form is dd/mm/yyyy.
- Sheets are named C1 (Sections I-III), C2 (IV-V), C3 (VI-VIII), then the continuation sheets C5_L&D cont., C6_Work Exp cont., C7-C11. Section VI spills from C3 rows 5-21 onto "C5_L&D cont." rows 6-49; people here have more than seventeen trainings.
- Wide answers span merged cells (A:E, D:F, G:I, I:K). Write only the leftmost address.
- PhpSpreadsheet stores numeric strings as numbers, so assert those with toEqual, not toBe.
- Section VI is filled from approved TrainingRecords, not from anything the employee retypes on the my-pds page.
