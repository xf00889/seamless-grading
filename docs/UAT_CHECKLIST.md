# UAT Checklist

Use this checklist after seeding the explicit UAT package:

```bash
php artisan migrate:fresh --seed --seeder=Database\\Seeders\\UatDemoSeeder
```

All demo accounts use the password `password`.

## Shared Preconditions

- Active school year is `2025-2026`
- Open grading period is `Q2`
- `Grade 7 - Narra` is the primary in-progress advisory section
- `Grade 7 - Molave` is the completed advisory section
- `Grade 6 - Acacia` provides finalized historical SF10 records

## Admin Scenarios

| Scenario | Steps | Expected Outcome |
| --- | --- | --- |
| Academic setup sanity check | Sign in as `admin.uat@example.test`. Open academic setup pages for school years, grading periods, sections, and subjects. | `2025-2026` is active, `Q2` is open, `Narra` and `Molave` exist under Grade 7, and seeded core subjects are visible. |
| User and load management | Open user management and teacher loads. Filter by teacher and section. | Demo accounts are active with correct roles. Narra shows multiple loads split across the two teacher accounts. |
| SF1 import history | Open the SF1 import module and review the confirmed batch for Narra. | The batch is confirmed, rows are imported, and the source workbook exists as a stored file-backed record. |
| Submission monitoring | Open submission monitoring for `2025-2026`, `Q2`. Filter to `Narra`, then `Molave`. | Narra surfaces missing, draft, submitted, returned, reopened, locked, and approved states. Molave appears as completed and includes finalized SF9 records. |
| Audit log review | Open the aggregated audit log page and filter by module or action. | Audit rows are available for template activation, grading actions, exports, finalization, learner movement exceptions, and quarter workflow events. |

## Teacher Scenarios

| Scenario | Steps | Expected Outcome |
| --- | --- | --- |
| Dashboard and owned loads | Sign in as `teacher.uat@example.test`. Open the dashboard and `My Teaching Loads`. | Only the teacher’s own loads are visible. Narra Mathematics, English, Science, AP, and Molave Mathematics appear as assigned loads. |
| Grade entry draft state | Open Narra English for `Q2` grade entry. | The submission is in `Draft`, grades are prefilled for active learners, and the page remains editable. |
| Submitted state visibility | Open Narra Science for `Q2`. | The submission is `Submitted`, visible as pending adviser review, and should not behave like an approved or returned record. |
| Returned submission correction context | Open `Returned Submissions` and review Narra TLE. | Adviser remarks are visible, the status is `Returned`, and the record is clearly flagged for correction. |
| Locked submission protection | Review Narra MAPEH for `Q2`. | The submission is `Locked` and clearly shown as non-editable until reopened. |
| Grading-sheet export history | Open Narra Mathematics grading-sheet preview/export for `Q2`. | A valid active template is available, persisted data is shown, and prior export history contains at least two versioned rows. |

## Adviser Scenarios

| Scenario | Steps | Expected Outcome |
| --- | --- | --- |
| Dashboard summary | Sign in as `adviser.uat@example.test`. Open the adviser dashboard. | Narra is shown as the active advisory section with incomplete quarter progress. Acacia appears as historical context where applicable. |
| Subject submission tracker | Open Narra submission tracking for `Q2`. | Missing AP, draft English, submitted Science, returned TLE, reopened ESP, locked MAPEH, and approved Mathematics are all visible with clear status badges. |
| Consolidation blocker review | Open learner and subject consolidation views for Narra `Q2`. | Post-transfer and dropped learners are clearly marked with ineligibility context and do not inflate normal active grading requirements after the exception date. |
| SF9 finalized history | Open SF9 preview/history for a Narra learner in `Q1`. | Finalized SF9 data is visible with template version and finalization metadata. |
| Year-end learner status review | Open the year-end learner status page. | Historical Acacia statuses include promoted and retained learners. Narra movement exceptions show transferred-out and dropped learners with recorded context. |
| SF10 historical verification | Open SF10 preparation/history for Maria Lopez or Carlo Bautista in Acacia. | Finalized SF10 records are visible with template metadata, export history, and finalization context. |

## Registrar Scenarios

| Scenario | Steps | Expected Outcome |
| --- | --- | --- |
| Final records repository | Sign in as `registrar.uat@example.test`. Open the records repository. | Only finalized SF9 and SF10 records are listed. Draft or unofficial records are not exposed. |
| Learner search | Search for `Lopez`, then search by LRN `202500000001`. | Finalized record rows for Maria Lopez are returned with document type, section, school year, version, and finalization metadata. |
| Record history | Open Maria Lopez’s history view. | Both finalized SF9 and finalized SF10 history are visible where available, with version and generation details. |
| Verification view | Open an SF10 record verification page. | The record is read-only, includes template version and finalization metadata, and does not expose workflow edit controls. |

## Exception Cases To Verify

- `Angela Cruz` is transferred out effective `2025-10-15`
- `Jonah Santos` is dropped effective `2025-10-20`
- These learners should remain visible as historical roster members but should not behave like normal active learners for later-quarter grading readiness after the exception takes effect

## Exit Criteria

- All four main roles can sign in and reach their intended work areas
- Demo data surfaces both happy-path and blocker-path workflow states
- Finalized SF9 and SF10 records are visible to the registrar only through official finalized paths
- Templates, export files, and import source files exist as real stored files, not metadata-only placeholders
