# CampusIQ: setup before you paste the prompt

## 1. Have these installed
- XAMPP, with Apache and MySQL both started in the XAMPP Control Panel
- Node.js LTS (needed for Tailwind and the MCP servers)
- Git for Windows (Claude Code on Windows uses its Git Bash)
- Claude Code (`claude --version` should work in a terminal)

## 2. Make the project folder
```
C:\xampp\htdocs\campusiq\
├── CLAUDE.md          <- the file I sent
└── design-ref\        <- unzip CampusIQ_Figma_Import.zip here and rename the folder to design-ref
    ├── 01_1_Index_landing_page.html
    ├── ...
    └── 15_Mobile_Dashboard.html
```
Nothing else. Claude Code builds the rest.

## 3. Add the MCP servers
Open a terminal in `C:\xampp\htdocs\campusiq` and run these one at a time.

**Playwright MCP** (needed): lets Claude open the site, click, read console errors and screenshot. Uses Edge, since it's already on every Windows PC.
```
claude mcp add playwright -- cmd /c npx -y @playwright/mcp@latest --browser msedge
```

**MySQL MCP** (needed): lets Claude check the database rows after each step. Read only by default; Claude creates and fills the DB with mysql.exe itself.
```
claude mcp add mysql --env MYSQL_HOST=127.0.0.1 --env MYSQL_PORT=3306 --env MYSQL_USER=root --env MYSQL_PASS= --env MYSQL_DB=campusiq -- cmd /c npx -y @benborla29/mcp-server-mysql
```
The `campusiq` database doesn't exist yet, so this one shows as failing until Phase 1 creates it. That's expected; Claude reconnects it with /mcp.

**Context7 MCP** (optional, recommended): live docs for Tailwind v4 and PHP, so Claude doesn't use outdated syntax.
```
claude mcp add context7 -- cmd /c npx -y @upstash/context7-mcp
```

Check they're there:
```
claude mcp list
```

## 4. Let it run without asking you every step
Create `C:\xampp\htdocs\campusiq\.claude\settings.json` with this:
```json
{
  "permissions": {
    "allow": [
      "Bash(php:*)",
      "Bash(/c/xampp/php/php.exe:*)",
      "Bash(/c/xampp/mysql/bin/mysql.exe:*)",
      "Bash(bash database/reset.sh)",
      "Bash(npm:*)",
      "Bash(npx:*)",
      "Bash(git:*)",
      "Bash(mkdir:*)",
      "Bash(ls:*)",
      "Bash(curl:*)",
      "mcp__playwright",
      "mcp__mysql",
      "mcp__context7",
      "WebFetch"
    ],
    "deny": [
      "Read(./.env)",
      "Bash(cat .env)",
      "Bash(type .env)"
    ]
  }
}
```
The deny rules mean Claude can never read your keys, even after you fill them.

## 5. Start the build
```
cd C:\xampp\htdocs\campusiq
claude
```
Press `Shift+Tab` until it says **accept edits on**, then paste the whole prompt from PROMPT.md and let it go. Keep the laptop plugged in and awake; this is a long run.

## 6. After it finishes: the only things you do
Fill these in `.env`:
- `GEMINI_API_KEY`: from aistudio.google.com, "Get API key"
- `PDFSHIFT_API_KEY`: from your pdfshift.io dashboard
- `EMAILJS_PUBLIC_KEY`, `EMAILJS_SERVICE_ID`, `EMAILJS_TEMPLATE_ID`: from emailjs.com
- `DEMO_EMAIL` (optional): your Gmail, so test emails come to you. Then run `bash database/reset.sh` once so the seed uses it.

One step can't live in .env: in the EmailJS dashboard you have to create an email service (connect your Gmail) and a template once. Claude puts the template in `docs/emailjs-template.html`, so you just paste it in and copy the template ID into .env.

After saving .env, refresh the site. The "Demo mode" badges disappear and the 3 APIs go live.
