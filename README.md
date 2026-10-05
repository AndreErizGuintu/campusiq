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
| `GEMINI_MODEL` | Gemini model ID, e.g. `gemini-3.8-flash` (the code falls back to that if empty). |
| `GEMINI_THINKING_LEVEL` | Optional. Gemini 3 thinking depth: `minimal`, `low` (default), `medium`, `high`. Lower is faster and cheaper; `minimal` is Flash-only. |
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
- `php -l <file>`
- `npm run build:css`

## Project layout

```
app/        bootstrap, routes, helpers, Core (Env, Database, Router, Request, Response, View, Auth, Csrf, Model, Http),
            Models, Services (GeminiService, PdfShiftService, EmailTemplateService, StatsService), Controllers, Views
public/     index.php (front controller), assets/css (built), assets/js (app.js, ai.js, email.js, reports.js)
database/   schema.sql, seed.php, reset.sh
storage/    reports/ (generated files), logs/app.log   -- blocked from the web
docs/       emailjs-template.html (paste into the EmailJS dashboard)
design-ref/ the 15 design screens (read only, blocked from the web)
tests/      smoke.php
```

## Design notes

### Architecture

- Custom lightweight MVC in plain PHP 8, no framework and no Composer. `public/index.php` is the only entry point; the root `.htaccess` sends every request there and returns 403 for `app/`, `database/`, `storage/`, `design-ref/`, `docs/`, `resources/`, `tests/` and any dotfile.
- `app/Core/` is the whole framework: router, request / response, view renderer, PDO database, auth, CSRF, a base model that only writes whitelisted columns, and `Http.php`, a shared cURL wrapper for Gemini and PDFShift. Controllers extend `app/Controllers/Controller.php` for shared helpers.
- Views are plain PHP templates: three layouts (public, staff app, portal) and shared partials (sidebar, top bar, stat cards, toasts, record pills).
- The staff dashboard's attendance chart is server-rendered SVG with a hover title per bar and a screen-reader table. "Present today" uses the latest school day with attendance (on a weekend it shows Friday and says so).
- Under 768px the sidebar becomes a bottom tab bar. Staff tabs: Dashboard, Students, AI, Email, PDF (Home stays reachable from the logo). Portal tabs: Records, Reports, Inbox. Log out moves to the top bar.

### Demo mode

Each service checks its key at runtime, so the app is fully usable before any key is filled:

- **Gemini empty**: answers are built from the same database rows the live model would get.
- **EmailJS empty** (any of the three values): nothing is sent and every email is logged with status `demo`, never `sent`.
- **PDFShift empty**: reports are saved as print-ready HTML pages with a "Print / Save as PDF" button.

Filling a key switches the same code path to live, with no code changes.

### API 1: AI Assistant (Gemini)

- Server side only: `models/{GEMINI_MODEL}:generateContent` with the `x-goog-api-key` header and a JSON response schema (`answer` + optional `table`). Thinking depth comes from `GEMINI_THINKING_LEVEL` (default `low`, which keeps answers fast).
- The question picks the records sent as context: a student named in it (or passed from the student page), a section (10-A / 10-B), record types from keywords, and dates ("today", "this week", "last week", "this month", "last N days"). At most 400 rows go to the model, plus pre-computed per-student totals so counts are exact. The model is told to answer only from those records.
- Busy or rate-limited replies (429, 500, 503, 504) are retried up to 3 times with backoff (about 1 s, then 2 s) before the page says "AI is busy, try again in a minute."
- The page shows the last 6 questions of the logged-in staff member (tables are stored as plain lines in `ai_queries.answer`).

### API 2: Email Alerts (EmailJS)

- The server builds the subject and message (`app/Services/EmailTemplateService.php`); the browser sends with the EmailJS SDK (`@emailjs/browser@4`), one email per recipient about 1.1 s apart (EmailJS allows 1 request per second), then POSTs the result to `/api/emails/logs`.
- Auto emails fire when a record is created (not edited) and its trigger is on: any grade, attendance "Absent" or "Late", library "Overdue".
- Recipients are limited to the student's own email and the guardian email on file; the log endpoint rejects anything else.
- `email_logs.message` keeps the full text, so a failed email can be retried exactly (Retry updates the same row) and the portal inbox can show the body.
- "Email it to guardian" on a report opens Email Alerts with the "Report ready" template. EmailJS can't attach files on the free plan, so the email tells the guardian to log in and download it.

### API 3: PDF Reports (PDFShift)

- `app/Views/pages/reports/print.php` is a self-contained page (inline CSS, Google Fonts) because PDFShift renders it on its own servers and can't load files from localhost. The server POSTs it to `https://api.pdfshift.io/v3/convert/pdf` with the `X-API-Key` header, `format: A4`, `use_print: true` and `sandbox` from `PDFSHIFT_SANDBOX`.
- Every report keeps an `.html` snapshot next to the PDF in `storage/reports/` for the on-screen preview.
- Files are only served through `/reports/{id}/download` and `/reports/{id}/preview`, which check ownership (staff: any report; student / parent: only their linked student). There is no direct file URL.

### Accounts, portal and security

- Login accepts the ID number or the email. Student accounts use the student number; parent accounts get `P-<student number>` (e.g. `P-10-24031`). Staff accounts come only from the seed.
- Sign up links an account by student number + the email the school has on file (student email for students, guardian email for parents). One student account and one parent account per student. Password reset is not part of the system.
- Passwords use `password_hash` / `password_verify`; the session id is regenerated on login. "Remember me" keeps the session for 7 days.
- Five failed logins in a row lock the login form for 60 seconds (per session).
- Every POST form and fetch call carries a CSRF token. A failed check returns 403 "Your session expired" (Apache turns the non-standard 419 code into a 500).
- `Request::input()` / `query()` only return scalars and list fields use `Request::array()`, so array-shaped input (`?q[]=x`) can't break a page.
- Students and parents only ever see their linked student, enforced in every portal route and in report downloads. The inbox shows the `email_logs` rows sent to the user's own address (student or guardian) with status `sent` or `demo`; unread means it arrived after the last visit (`users.notifications_seen_at`).
- Students and parents can make one PDF every 20 seconds. "Download as PDF" in the portal always covers all dates, and the portal "Reports" page lists every report made from that record, by staff or by the family.
- `APP_ENV=local` shows error details and the demo login shortcuts; set it to `production` to hide both.
