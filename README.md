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
