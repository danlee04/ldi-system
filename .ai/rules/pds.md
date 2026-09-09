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

## Yes/no answers on the PDS are checkbox controls, not cells
Sex, civil status and citizenship on sheet C1, and every question on page 4 (sheet C4), are Excel form controls. Writing the word into the cell prints nothing: the template formats those link cells white on white so only the tick shows.

FillPersonalDataSheet::tick() sets both halves — the linked cell to TRUE, and checked="Checked" on the control, which lives in the workbook's unparsed loaded data ($book->getUnparsedLoadedData()['sheets'][$sheet->getCodeName()]['ctrlProps']), matched by its fmlaLink. PhpSpreadsheet carries ctrlProps and the VML through untouched, so this survives a save.

C1 boxes: sex D16/E16, civil status D17/E17/D18/E19/D20, citizenship J13/K13, dual basis L14/M14, country dropdown J16 (stores the 1-based line within Q11:Q217 of the same sheet).
C4 boxes: yes in column H, no in column J, at rows 3, 8, 13, 18, 23, 27, 31, 34, 37, 43, 45, 47.

A nullable boolean means unanswered: leave both boxes alone rather than ticking No.
