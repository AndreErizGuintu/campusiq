# CampusIQ

AI-Augmented Academic Records and Integration System. Systems Integration and Architecture 2 project, CommIT Team, BSIT 4A, Holy Cross College.

Plain PHP 8 + MySQL on XAMPP, Tailwind CSS v4, vanilla JS. Three APIs:

| API | Page | Runs | Key(s) in `.env` |
|---|---|---|---|
| **API 1 · Gemini** | AI Assistant (`/ai`) | server side (PHP cURL) | `GEMINI_API_KEY`, `GEMINI_MODEL` |
| **API 2 · EmailJS** | Email Alerts (`/emails`) + auto emails on record save | browser (EmailJS SDK) | `EMAILJS_PUBLIC_KEY`, `EMAILJS_SERVICE_ID`, `EMAILJS_TEMPLATE_ID` |
| **API 3 · PDFShift** | PDF Reports (`/reports`) + "Download as PDF" in the portal | server side (PHP cURL) | `PDFSHIFT_API_KEY`, `PDFSHIFT_SANDBOX` |

Every API works before its keys exist: the page shows a "Demo mode: add KEY_NAME to .env" badge and gives realistic output from the real database. Fill the key, refresh, and the same code path goes live.

## Setup

1. **XAMPP**: start Apache and MySQL in the XAMPP Control Panel.
2. **Site folder**: the code lives in `C:\xampp\htdocs\CampusIQ_Figma_Import`, and the junction `C:\xampp\htdocs\campusiq` points to it, so the site is http://localhost/campusiq. (On a fresh machine, either name the folder `campusiq` or recreate the junction in PowerShell: `New-Item -ItemType Junction -Path C:\xampp\htdocs\campusiq -Target C:\xampp\htdocs\CampusIQ_Figma_Import`.)
3. **CSS**: `npm install`, then `npm run build:css` (or `npm run watch:css` while editing). The built `public/assets/css/app.css` is committed, so this is only needed after changing classes.
4. **Database**: `bash database/reset.sh` (Git Bash). Creates `campusiq`, imports the schema and seeds demo data.
5. **Keys**: fill in `.env` (see below). Nothing else needs changing.
6. Open http://localhost/campusiq and log in with a demo account.

### The `.env` keys

| Key | What it does |
|---|---|
| `APP_NAME`, `APP_URL`, `APP_ENV` | App name, base URL (`http://localhost/campusiq`; its path is the route prefix), `local` shows error details and the login demo shortcuts. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection (XAMPP defaults). Also read by `database/reset.sh`. |
| `GEMINI_API_KEY` | Google AI Studio key (aistudio.google.com, "Get API key"). Empty = demo answers built from the real records. |
| `GEMINI_MODEL` | Model name, default `gemini-2.5-flash`. |
| `EMAILJS_PUBLIC_KEY` | EmailJS > Account > General > Public Key. Rendered into staff pages (it is meant to be public). |
| `EMAILJS_SERVICE_ID` | EmailJS > Email Services (connect your Gmail). |
| `EMAILJS_TEMPLATE_ID` | EmailJS > Email Templates, using `docs/emailjs-template.html` (setup steps are in that file). Any of the three empty = demo: emails are logged with status `demo`, nothing is sent. |
| `PDFSHIFT_API_KEY` | pdfshift.io dashboard key (`sk_...`). Empty = demo: reports are saved as print-ready HTML pages. |
| `PDFSHIFT_SANDBOX` | `true` (default) = free, watermarked PDFs that use no credits. Set `false` for clean PDFs. |
| `DEMO_EMAIL` | Optional, your Gmail. Seeded student/guardian emails become plus addresses (`you+juan@gmail.com`, `you+juan.guardian@gmail.com`) so live emails reach your inbox. Run `bash database/reset.sh` after setting it. Empty = `@example.com` addresses. |

The Gemini and PDFShift keys never leave the server. Only the three EmailJS values are rendered into staff pages.

## Demo logins

All demo passwords are `password123`. You can log in with the ID number or the email. The login page also has "Enter as staff / student / parent" shortcuts while `APP_ENV=local`.

| Role | Name | ID number | Lands on |
|---|---|---|---|
| Staff (Class Adviser) | Ms. Santos | `T-0012` | /dashboard |
| Staff (Teacher) | Mr. Garcia | `T-0015` | /dashboard |
| Student | Juan Dela Cruz | `10-24031` | /my/records |
| Parent (Juan's guardian) | Rosa Dela Cruz | `P-10-24031` | /my/records |

Other students (Maria Reyes `10-24032`, Paolo Cruz `10-24033`, ...) have no account yet: sign up at /signup with the student number and the email on file, e.g. `10-24032` + `maria.reyes@example.com` (student) or `e.reyes@example.com` (parent).

## Reset the database

```
bash database/reset.sh
```
Drops and recreates `campusiq`, imports `database/schema.sql`, deletes generated files in `storage/reports/`, and runs `database/seed.php`: 2 staff, 10 students in 10-A and 10-B, attendance for the last 15 school days counting back from today (so the dashboard is always current), grades, library records, 6 example email logs, all auto email triggers ON.

## Checks

- `php tests/smoke.php`: hits every route as guest, staff, student and parent (status codes, PHP errors, role and ownership rules, 403 on private folders).
- `php -l <file>`: also runs automatically after every edit through the hook in `.claude/settings.json`.
- `npm run build:css`

## Project layout

```
app/        bootstrap, routes, helpers, Core (Env, Database, Router, Request, Response, View, Auth, Csrf, Model, Http),
            Models, Services (GeminiService, PdfShiftService, EmailTemplateService, StatsService), Controllers, Views
public/     index.php (front controller), assets/css (built), assets/js (app.js, ai.js, email.js, reports.js)
database/   schema.sql, seed.php, reset.sh
storage/    reports/ (generated files), logs/app.log   -- blocked from the web
docs/       emailjs-template.html, the original PROMPT.md and MCP_SETUP.md
design-ref/ the 15 Figma screens (read only, blocked from the web)
tests/      smoke.php
```

## Decisions made during the build

- The project folder is `C:\xampp\htdocs\CampusIQ_Figma_Import`. A directory junction `C:\xampp\htdocs\campusiq` points at it, so the site runs at http://localhost/campusiq as CLAUDE.md expects without moving the folder.
- The 15 design screens were moved into `design-ref/`; `PROMPT.md` and `MCP_SETUP.md` were moved into `docs/`.
- Playwright MCP and MySQL MCP were not loaded in the build session, so `.mcp.json` registers both for future sessions. During the build the same checks ran with Playwright (Edge) from a scratch Node script and with `mysql.exe`.
- Two small additions to the folder tree: `app/Core/Http.php` (shared cURL wrapper for Gemini and PDFShift) and `app/Controllers/Controller.php` (base controller helpers).
- Login accepts the ID number or the email. Parent accounts get the ID `P-<student number>` (e.g. `P-10-24031`); student accounts use the student number.
- Sign up links an account by student number + the email the school has on file (student email for students, guardian email for parents). One student account and one parent account per student.
- The "Forgot password?" link from the design was left out: password reset is outside the scope lock. "Remember me" keeps the session cookie for 7 days.
- A failed CSRF check returns 403 "Your session expired" (Apache rewrites the non-standard 419 code to 500).
- Five failed logins in a row lock the login form for 60 seconds (per session).
- Mobile bottom tab bar for staff: Dashboard, Students, AI, Email, PDF (Students replaces Home, which stays reachable from the logo). Portal tabs: Records, Reports, Inbox. Log out is in the top bar on mobile.
- The staff dashboard's "Present today" uses the latest school day with attendance (on a weekend it shows Friday and says so). The chart is server-rendered SVG with a hover title per bar and a screen-reader table.
- AI Assistant uses Gemini `models/{GEMINI_MODEL}:generateContent` with the `x-goog-api-key` header and a JSON response schema (`answer` + optional `table`). For `gemini-2.5-flash*` models thinking is turned off (`thinkingBudget: 0`) so answers come back fast. The question picks the records: a student named in it (or passed from the student page), a section (10-A / 10-B), record types from keywords, and dates ("today", "this week", "last week", "this month", "last N days"). At most 400 rows go to the model, plus pre-computed per-student totals so counts are exact.
- AI history on the page shows the last 6 questions of the logged-in staff member (tables are stored as plain lines in `ai_queries.answer`).
- Email Alerts: the server builds the subject and message (`app/Services/EmailTemplateService.php`), the browser sends with the EmailJS SDK (`@emailjs/browser@4`), one email per recipient about 1.1 s apart (EmailJS allows 1 request per second), then POSTs the result to `/api/emails/logs`. Auto emails fire only when a record is created (not when it is edited), for: any grade, attendance "Absent", attendance "Late", library "Overdue". "Present" attendance and returned/borrowed books don't email.
- `email_logs` has an extra `message` column (the full text), so failed emails can be retried exactly and the student/parent inbox can show the body. Retry updates the same log row.
- Email recipients are limited to the student's own email and guardian email on file; the log endpoint rejects anything else. Without the three EmailJS keys every email is logged with status `demo`, never `sent`.
- The seed adds 6 example email logs built with the real templates (status `demo`, one `failed` so Retry can be shown).
- PDF Reports: `app/Views/pages/reports/print.php` is a self-contained page (inline CSS, Google Fonts) because PDFShift renders it on its own servers and can't load files from localhost. The server POSTs it to `https://api.pdfshift.io/v3/convert/pdf` with the `X-API-Key` header, `format: A4`, `use_print: true` and `sandbox` from `PDFSHIFT_SANDBOX`. Every report keeps an `.html` snapshot next to the PDF in `storage/reports/` for the on-screen preview.
- Reports are only served through `/reports/{id}/download` and `/reports/{id}/preview`, which check ownership (staff: any; student/parent: only their linked student). In demo mode "Download" opens the print-ready page with a "Print / Save as PDF" button.
- "Email it to guardian" opens Email Alerts with the "Report ready" template addressed to the guardian only. EmailJS can't attach files on the free plan, so the email tells them to log in and download it.
- Portal: students and parents only ever see the student linked to their account (enforced in every portal route and in report downloads). "Download as PDF" always makes a full record of all dates. The portal sidebar has a "Reports" page (design-ref 10 shows it) listing every report made from that record, by staff or by the family.
- Notifications are the `email_logs` rows for the linked student with status `sent` or `demo` (failed sends never reached anyone). Unread = arrived after the user's last visit to the inbox (`users.notifications_seen_at`, an extra column).
- Final review (a fresh subagent checked the project against CLAUDE.md): no security holes found. Fixed from its report: array-shaped input (`?q[]=x`) no longer crashes pages (`Request::input()/query()` only return scalars, list fields use `Request::array()`); "Remember me" now really lasts 7 days (`session.gc_maxlifetime`); demo badges show the full "Demo mode: add KEY_NAME to .env" text on phones and also appear on the portal pages and the student page; a student's inbox only shows emails sent to the student's address (a parent's, those sent to the guardian address); students/parents can make one PDF every 20 seconds; the demo password is only in the page when `APP_ENV=local`; JSON email endpoints use the plural `/api/emails/...`.
- Left as is on purpose: sign up proves ownership with student number + the email on file (no one-time codes, that would be a new feature); the login throttle is per session; the footer keeps the `[Member 1]`, `[team email]`, `Instructor: [name]` placeholders for the team to fill in; `APP_ENV=local` stays as requested (set it to `production` to hide error details and the demo login buttons).
