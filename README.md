# CampusIQ

AI-Augmented Academic Records and Integration System. Systems Integration and Architecture 2 project, CommIT Team, BSIT 4A, Holy Cross College.

Plain PHP 8 + MySQL on XAMPP, Tailwind CSS v4, vanilla JS. Three APIs: Gemini (AI Assistant), EmailJS (Email Alerts), PDFShift (PDF Reports).

_Setup steps and notes are filled in as each phase lands._

## Demo logins

All demo passwords are `password123`. You can log in with the ID number or the email.

| Role | Name | ID number | Lands on |
|---|---|---|---|
| Staff (Class Adviser) | Ms. Santos | `T-0012` | /dashboard |
| Staff (Teacher) | Mr. Garcia | `T-0015` | /dashboard |
| Student | Juan Dela Cruz | `10-24031` | /my/records |
| Parent (Juan's guardian) | Rosa Dela Cruz | `P-10-24031` | /my/records |

## Reset the database

```
bash database/reset.sh
```
Drops and recreates `campusiq`, imports `database/schema.sql`, clears `storage/reports/`, and runs `database/seed.php`. Attendance is generated for the last 15 school days counting back from today, so the dashboard always has fresh data after a reset.

## Decisions made during the build

- The project folder is `C:\xampp\htdocs\CampusIQ_Figma_Import`. A directory junction `C:\xampp\htdocs\campusiq` points at it, so the site runs at http://localhost/campusiq as CLAUDE.md expects without moving the folder.
- The 15 design screens were moved into `design-ref/`; `PROMPT.md` and `MCP_SETUP.md` were moved into `docs/`.
- Playwright MCP and MySQL MCP were not loaded in the build session, so `.mcp.json` registers both for future sessions. During the build the same checks ran with Playwright (Edge) from a scratch Node script and with `mysql.exe`.
- Login accepts the ID number or the email. Parent accounts get the ID `P-<student number>` (e.g. `P-10-24031`); student accounts use the student number.
- Sign up links an account by student number + the email the school has on file (student email for students, guardian email for parents). One student account and one parent account per student.
- The "Forgot password?" link from the design was left out: password reset is outside the scope lock. "Remember me" keeps the session cookie for 7 days.
- Demo shortcut buttons on the login page ("Enter as staff / student / parent") only show when `APP_ENV=local`.
- A failed CSRF check returns 403 "Your session expired" (Apache rewrites the non-standard 419 code to 500).
- Five failed logins in a row lock the login form for 60 seconds (per session).
- Mobile bottom tab bar for staff: Dashboard, Students, AI, Email, PDF (Students replaces Home, which stays reachable from the logo). Portal tabs: Records, Reports, Inbox.
- AI Assistant uses Gemini `models/{GEMINI_MODEL}:generateContent` with the `x-goog-api-key` header and a JSON response schema (`answer` + optional `table`). For `gemini-2.5-flash*` models thinking is turned off (`thinkingBudget: 0`) so answers come back fast. The question picks the records: a student named in it (or passed from the student page), a section (10-A / 10-B), record types from keywords, and dates ("today", "this week", "last week", "this month", "last N days"). At most 400 rows go to the model, plus pre-computed per-student totals so counts are exact.
- AI history on the page shows the last 6 questions of the logged-in staff member (tables are stored as plain lines in `ai_queries.answer`).
- Email Alerts: the server builds the subject and message (`app/Services/EmailTemplateService.php`), the browser sends with the EmailJS SDK (`@emailjs/browser@4`), one email per recipient about 1.1 s apart (EmailJS allows 1 request per second), then POSTs the result to `/api/email/log`. Auto emails fire only when a record is created (not when it is edited), for: any grade, attendance "Absent", attendance "Late", library "Overdue". "Present" attendance and returned/borrowed books don't email.
- `email_logs` has an extra `message` column (the full text), so failed emails can be retried exactly and the student/parent inbox can show the body. Retry updates the same log row.
- Email recipients are limited to the student's own email and guardian email on file; the log endpoint rejects anything else. Without the three EmailJS keys every email is logged with status `demo`, never `sent`.
- The seed adds 6 example email logs built with the real templates (status `demo`, one `failed` so Retry can be shown).
- PDF Reports: `app/Views/pages/reports/print.php` is a self-contained page (inline CSS, Google Fonts) because PDFShift renders it on its own servers and can't load files from localhost. The server POSTs it to `https://api.pdfshift.io/v3/convert/pdf` with the `X-API-Key` header, `format: A4`, `use_print: true` and `sandbox` from `PDFSHIFT_SANDBOX` (sandbox PDFs are free but watermarked; set it to `false` for clean PDFs). Every report keeps an `.html` snapshot next to the PDF in `storage/reports/` for the on-screen preview.
- Reports are only served through `/reports/{id}/download` and `/reports/{id}/preview`, which check ownership (staff: any; student/parent: only their linked student). In demo mode "Download" opens the print-ready page with a "Print / Save as PDF" button.
- "Email it to guardian" opens Email Alerts with the "Report ready" template addressed to the guardian only. EmailJS can't attach files on the free plan, so the email tells them to log in and download it.
