# CampusIQ

AI-Augmented Academic Records and Integration System. Systems Integration and Architecture 2 project, CommIT Team, BSIT 4A, Holy Cross College.

## Stack (fixed, do not change)
- Plain PHP 8 (no framework, no Composer) on XAMPP (Apache + MySQL/MariaDB), Windows
- Tailwind CSS v4 via the CLI (`@tailwindcss/cli`), plus raw CSS for anything Tailwind can't do cleanly
- Vanilla JS only (no React, no jQuery). EmailJS browser SDK from its CDN
- MySQL through PDO with prepared statements only

## Scope lock
Build ONLY this:
1. Landing page (Index), Login, Sign up
2. Staff dashboard (stats, attendance chart, quick access to the 3 APIs)
3. Students & records: list, search, student detail, add / edit / delete a record
4. API 1, AI Assistant: Gemini API, server side
5. API 2, Email Alerts: EmailJS, client side, auto email on record save + manual send + log
6. API 3, PDF Reports: PDFShift API, server side, generate + preview + download
7. Student / parent portal: my records, download my record as PDF, my notifications
No extra features (no chat with other users, no file uploads, no admin panels beyond the above).

## Paths
- Project root: `C:\xampp\htdocs\campusiq` (site: http://localhost/campusiq)
- PHP: `C:\xampp\php\php.exe` (Git Bash: `/c/xampp/php/php.exe`)
- MySQL CLI: `C:\xampp\mysql\bin\mysql.exe -u root` (Git Bash: `/c/xampp/mysql/bin/mysql.exe -u root`)
- Design reference: `design-ref/` (15 static HTML screens). Use them for LAYOUT, COLORS, TYPE and CONTENT only. Their code is messy inline styles; never copy it. Rebuild every screen cleanly with Tailwind classes and shared partials.

## Folder tree (keep to this)
```
campusiq/
├── .env                      # secrets, filled by the user ONLY, gitignored
├── .env.example              # same keys, empty values, committed
├── .gitignore
├── .htaccess                 # sends everything to public/, blocks the rest
├── CLAUDE.md
├── README.md
├── package.json              # tailwind scripts only
├── app/
│   ├── bootstrap.php         # env, session, error handling, autoload
│   ├── routes.php
│   ├── helpers.php           # e(), url(), asset(), csrf_field(), old(), flash()
│   ├── Core/                 # Env, Database, Router, Request, Response, View, Auth, Csrf, Model
│   ├── Models/               # User, Student, Record, EmailLog, Report, AiQuery, Setting
│   ├── Services/             # GeminiService, PdfShiftService, EmailTemplateService, StatsService
│   ├── Controllers/          # Home, Auth, Dashboard, Student, Record, Ai, Email, Report, Portal
│   └── Views/
│       ├── layouts/          # public.php, app.php, portal.php
│       ├── partials/         # sidebar.php, topbar.php, flash.php, stat-card.php, record-pill.php, ...
│       └── pages/            # one folder per area: home/, auth/, dashboard/, students/, ai/, email/, reports/, portal/
├── public/
│   ├── index.php             # front controller
│   ├── .htaccess
│   └── assets/
│       ├── css/app.css       # BUILT by tailwind, never hand edit
│       ├── js/               # app.js, ai.js, email.js, reports.js
│       └── img/
├── resources/css/app.css     # tailwind input + @theme tokens + raw css
├── database/
│   ├── schema.sql
│   ├── seed.php              # reads .env, hashes passwords, inserts demo data
│   └── reset.sh              # drop + create + schema + seed in one go
├── storage/                  # NOT web reachable
│   ├── reports/              # generated PDFs
│   └── logs/app.log
├── tests/
│   └── smoke.php             # hits every route, fails on non 200/302 or PHP errors
├── docs/
│   └── emailjs-template.html # template the user pastes into the EmailJS dashboard
└── design-ref/               # read only reference, blocked from the web
```

## Naming
- Classes: PascalCase, one class per file, file name = class name (`GeminiService.php`)
- Views, partials, JS, CSS: kebab-case (`student-detail.php`, `email.js`)
- DB tables: snake_case plural (`email_logs`), columns snake_case, every table has `id`, `created_at`
- Routes: lowercase, plural nouns (`/students/12`, `/reports/5/download`)

## Design tokens (from design-ref, put in resources/css/app.css `@theme`)
- Indigo primary `#4338ca`, deep indigo hero `#2f2a8f`, sidebar `#15172a`, page bg `#f5f6fa`, border `#e3e6ee`, text `#1b1e27`, muted `#6b7083`
- API accents: AI indigo `#4338ca`, Email teal `#0f766e`, PDF red `#b3123c`, Library amber `#9a4a08`
- Fonts: IBM Plex Mono (headings, numbers), IBM Plex Sans (body), loaded from Google Fonts
- Cards: white, 1px border, 12px radius. Buttons 40 to 46px tall, 8px radius
- Every staff page shares the same sidebar: Home, Dashboard, Students & records, then APIs: AI Assistant (API 1), Email Alerts (API 2), PDF Reports (API 3), Log out
- Must be responsive: sidebar collapses to a bottom tab bar under 768px (see design-ref mobile screens)

## Secrets and .env
- NEVER read, print, cat, grep or echo `.env`. Never put a key value in code, logs, commits or chat.
- You create `.env` and `.env.example` with the keys and empty values (DB lines may use XAMPP defaults). The user fills the API keys. After Phase 0 never write to `.env` again; add new keys to `.env.example` and tell the user.
- Server side keys (Gemini, PDFShift) never reach the browser. Only the EmailJS public key, service id and template id may be rendered into the page.

## Demo mode (so everything works before keys exist)
- If a key is empty, its service runs in demo mode with realistic fake output, and the UI shows a small "Demo mode: add KEY_NAME to .env" badge on that page.
- Gemini empty: canned answer built from the real DB rows. EmailJS empty: skip the real send, log status `demo`. PDFShift empty: store the HTML report and serve a print-friendly HTML view instead of a PDF.
- When the user fills the key, the same code path goes live with no code changes.

## Security rules
- PDO prepared statements only. `password_hash` / `password_verify`. `session_regenerate_id` on login.
- CSRF token on every POST form and fetch call. Escape all output with `e()`.
- Role checks on every route: `staff`, `student`, `parent`. Students and parents can only see their own linked student.
- `app/`, `database/`, `storage/`, `design-ref/`, `.env` must return 403 from the browser.
- Report downloads go through a PHP route that checks ownership, never a direct file URL.

## Definition of done (every phase)
1. `php -l` passes on every PHP file you touched
2. `npm run build:css` succeeds
3. `php tests/smoke.php` passes
4. Playwright MCP: open each page you touched at http://localhost/campusiq, click through the flow, check the browser console has no errors, screenshot desktop (1440) and mobile (390)
5. MySQL MCP: confirm the rows you expected actually exist
6. Only then `git add` + `git commit -m "phase N: ..."`. If anything fails, fix it first. Never commit a failing state.

## Commands
- Build CSS: `npm run build:css` (watch: `npm run watch:css`)
- Reset DB: `bash database/reset.sh`
- Smoke test: `php tests/smoke.php`
- Lint: `php -l <file>`
