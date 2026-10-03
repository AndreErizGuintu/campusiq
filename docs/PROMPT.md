Read CLAUDE.md fully first, then look through every file in design-ref/ so you know all 15 screens. Then build CampusIQ end to end, on your own, phase by phase. Do not stop to ask me questions; make reasonable calls and note them in README.md. The only thing I will do myself is fill in the API keys in .env.

Use Playwright MCP for all browser testing and MySQL MCP to check the database. Follow the "Definition of done" in CLAUDE.md at the end of EVERY phase and commit only when it passes.

Before writing the Gemini, EmailJS or PDFShift code, check their current official docs (ai.google.dev/gemini-api/docs, emailjs.com/docs, docs.pdfshift.io) for the exact endpoint, headers and request body. Do not guess API shapes.

## Phase 0: Setup
- `git init`, .gitignore (.env, node_modules, storage/reports/*, storage/logs/*, public/assets/css/app.css is fine to commit)
- Create the full folder tree from CLAUDE.md
- Create `.env` and `.env.example` with these keys (API values EMPTY, I fill them):
  APP_NAME=CampusIQ, APP_URL=http://localhost/campusiq, APP_ENV=local,
  DB_HOST=127.0.0.1, DB_PORT=3306, DB_NAME=campusiq, DB_USER=root, DB_PASS=,
  GEMINI_API_KEY=, GEMINI_MODEL=gemini-2.5-flash,
  EMAILJS_PUBLIC_KEY=, EMAILJS_SERVICE_ID=, EMAILJS_TEMPLATE_ID=,
  PDFSHIFT_API_KEY=, PDFSHIFT_SANDBOX=true,
  DEMO_EMAIL=
- Tiny .env loader in app/Core/Env.php (no libraries)
- package.json with tailwindcss + @tailwindcss/cli, scripts `build:css` and `watch:css` (input resources/css/app.css, output public/assets/css/app.css). Add `@source` lines so Tailwind scans app/Views and public/assets/js
- Root .htaccess + public/.htaccess so http://localhost/campusiq routes through public/index.php and app/, database/, storage/, design-ref/, .env return 403
- Add a PostToolUse hook in .claude/settings.json that runs `php -l` on any edited .php file
- Done when: http://localhost/campusiq shows a "CampusIQ works" page styled by Tailwind, and http://localhost/campusiq/.env is 403

## Phase 1: Database (we own the schema now)
- database/schema.sql with InnoDB, utf8mb4, foreign keys:
  users (role staff|student|parent, id_number unique, name, email, password_hash, student_id nullable for student/parent links, staff_title),
  students (student_no unique, first_name, last_name, section, email, guardian_name, guardian_email, status),
  records (student_id, type grade|attendance|library, title, value, note, recorded_by, recorded_on),
  email_logs (student_id, record_id nullable, trigger_key, recipients, subject, status sent|failed|demo, error, sent_by),
  reports (student_id, report_type, date_from, date_to, sections, file_path, size_bytes, mode live|demo, created_by),
  ai_queries (user_id, question, answer, records_used, mode live|demo),
  settings (key unique, value) for the email auto triggers
- database/seed.php: 2 staff (Ms. Santos, Class Adviser, id T-0012; Mr. Garcia, Teacher), 10 students in 10-A and 10-B (Juan Dela Cruz 10-24031, Maria Reyes, Paolo Cruz, Ana Lim, Kevin Tan, plus 5 more), about 3 weeks of attendance, a few grades and library records, 1 student login and 1 parent login for Juan. All demo passwords `password123`. If DEMO_EMAIL is set, every seeded student and guardian email is built from it with plus addressing (me@gmail.com becomes me+juan@gmail.com, me+juan.guardian@gmail.com), so live emails land in my inbox. If it is empty, use @example.com addresses. Default auto triggers all ON.
- database/reset.sh: drop, create, import schema, run seed
- Create the database yourself with mysql.exe and run reset. Then reconnect MySQL MCP (/mcp) and verify row counts.
- Put all demo logins in README.md

## Phase 2: Core + public pages
- Router, View with layouts/partials, Auth, Csrf, flash messages, base Model (whitelists columns via DESCRIBE so it stays schema agnostic), error page
- Landing page (match design-ref 01: hero, features, APIs with icons, how it works, footer with team placeholders)
- Login + Sign up (design-ref 02). Sign up: student or parent, linked by student number + matching email. Staff accounts come from the seed only
- Redirect after login by role: staff to /dashboard, student/parent to /my/records

## Phase 3: Staff dashboard + records
- Dashboard (design-ref 03): welcome + avatar, 4 stat cards from real DB numbers, attendance chart for the last 5 school days as server rendered SVG, quick access to the 3 API pages, recent activity feed from records/email_logs/reports/ai_queries
- Students list with search, student detail (design-ref 07) with records table and add / edit / delete record form

## Phase 4: API 1 AI Assistant (Gemini)
- Page per design-ref 04: chat UI, suggestion chips, answers with optional small tables
- app/Services/GeminiService.php: pull relevant records (by section / student / date guessed from the question, cap the rows), send question + records as context, ask for a short plain answer. Key only server side, called from a JSON endpoint POST /api/ai/ask (CSRF, staff only). Save to ai_queries. Show "Based on N records". "Make this a PDF" button links to the PDF Reports page for that student
- Handle errors (bad key, quota, timeout) with a friendly message

## Phase 5: API 2 Email Alerts (EmailJS)
- docs/emailjs-template.html: the template I paste into EmailJS, using variables like {{to_email}}, {{student_name}}, {{subject}}, {{message}}
- Email page per design-ref 05: student + template select, recipients chips, subject, live preview, Send button, auto trigger toggles (saved in settings), last delivery status, recent emails with Retry on failed
- Auto send: when staff saves a record whose trigger is ON, the next page load fires the email through the EmailJS browser SDK, then POSTs the result to /api/email/log. Show the "Record saved / Email sent to 2 people" toast like design-ref 08
- Demo mode when EmailJS keys are empty (log status demo, still show the toast)

## Phase 6: API 3 PDF Reports (PDFShift)
- Page per design-ref 06: student, report type (full record, grade slip, attendance summary, library receipt), date range, include checkboxes, Generate button, result card with preview, Download, "Email it to guardian"
- Build a clean print HTML report view, send it to PDFShift from app/Services/PdfShiftService.php (key server side, sandbox flag from .env), save to storage/reports/, record in reports table, download through /reports/{id}/download with ownership check
- Generating state like design-ref 09 while waiting
- Demo mode when key is empty

## Phase 7: Student / parent portal
- My records (design-ref 10) with stat cards and filters, "Download as PDF" (design-ref 11 and 12 states), Notifications inbox from email_logs (design-ref 13)
- Parents see their linked student only

## Phase 8: Polish + final check
- Mobile pass: everything usable at 390px, sidebar becomes bottom tab bar (design-ref 14, 15)
- Empty states, loading states, 404 page, form validation messages
- Run a fresh subagent to review the whole project against CLAUDE.md (security, scope, naming, tree) and fix what it finds
- Final full Playwright run of the whole flow: landing, sign up, login as staff, dashboard, add a record (email fires), ask AI, generate PDF, download, log out, log in as Juan's parent, see records, download PDF, see notification
- Update README.md: setup steps, demo logins, which .env keys do what, how to reset the DB
- Final commit

When everything is done, give me a short summary: what works, what is in demo mode until I add keys, and the exact .env keys I still need to fill.
