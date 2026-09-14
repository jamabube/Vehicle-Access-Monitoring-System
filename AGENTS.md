# Vehicle Access Monitoring System (VAMS)

Capstone project workspace root. This file is the canonical entry point for any AI agent working in this repository, per the `agent-spec` standard imported at `agent-spec/`.

## Commands

- **Install:** `cd vams-webapp; composer install; npm install`
- **Serve (dev, all-in-one):** `cd vams-webapp; composer dev` (runs `php artisan serve`, queue listener, Vite, and `php artisan reverb:start` concurrently)
- **Migrate:** `cd vams-webapp; php artisan migrate` (clear stray `APP_*`/`DB_*` env vars first — see `context/RULES.md`)
- **Test:** `cd vams-webapp; composer test` or `php artisan test`
- **Lint/format:** `cd vams-webapp; vendor/bin/pint`
- **Start everything:** `start.bat` at the workspace root - launches the web server, queue, Vite, the Reverb WebSocket server, **and** the RFID listener (in its own window). This is the normal way to run the system.
- **RFID listener (device service):** `cd vams-webapp; php artisan rfid:listen` (add `--dry-run` to decode tag reads without posting them). Needs the web server already running; `start-listener.bat` wraps it for a standalone interactive session.
- **RFID listener (unattended):** `start-listener-auto.bat` keeps the listener alive (restart loop + `logs/rfid-listener.log`), and refuses to start a second one if one is already running. Launched both by `start.bat` and by the Windows Scheduled Task "VAMS RFID Listener" at logon.
- **RFID diagnostics:** `cd vams-webapp; php artisan rfid:doctor` — read-only check of the reader link, protocol, API, and credentials.

## Repository Structure

```
Vehicle Access Monitoring System/
├── agent-spec/                # Imported agent-spec governance standard (do not edit unless updating the standard itself)
├── context/                   # Filled-in project context: PRD, ARCHITECTURE, SCHEMA, RULES, TASKS
├── rfid reader sdk/           # S4A UHF-202415 vendor SDK (RFIDDemo.exe, protocol docs, test scripts)
├── tools/                     # Local composer bootstrap files
├── vams-webapp/                # The Laravel 12 application (all feature code lives here)
├── _extracted/, capstone-2_manuscript_*.docx   # Capstone manuscript/documentation assets
├── .clinerules/                # Cline runtime governance rules (points to agent-spec + context/)
├── AGENTS.md                  # Workspace entry point and command reference
├── start.bat / start-listener.bat / start-listener-auto.bat   # Launchers: web server, listener, unattended listener
├── logs/                      # Runtime logs from the unattended listener
├── NETWORK_CONFIG.md          # Physical network topology, IP assignments, RFID reader setup guide
└── TESTING_GUIDE.md           # Step-by-step guide for testing the RFID ingestion pipeline
```

## Architecture Constraints

- `vams-webapp/app/Models/` → Eloquent models (Role, Permission, Employee, Visitor, Vehicle, RfidTag, VisitorVisit, RfidAssignment, RfidReader, RfidDetection, AccessLog, AuditLog, SystemLog, SystemSetting, ApiRequestNonce, User).
- `vams-webapp/database/migrations/` → source of truth for the real schema; `context/SCHEMA.md` mirrors it and must be kept in sync when migrations change.
- `vams-webapp/routes/` → HTTP routes; `routes/api.php` carries the RFID device ingestion endpoint behind the `rfid.hmac` middleware.
- `vams-webapp/app/Services/Rfid/` → RFID domain + device services: `HmacSignatureVerifier`, `RfidAccessResolver`, and the listener's `MmProtocolCodec` / `ReaderTransport` / `DetectionForwarder`.
- `vams-webapp/app/Console/Commands/` → `rfid:listen` (the device service) and `rfid:doctor` (diagnostics).
- Full architecture detail: `context/ARCHITECTURE.md`.

## Boundaries

- **Forbidden:** Do not modify `agent-spec/spec/core/` — that is the normative standard, not application code.
- **Forbidden:** Do not run destructive migrations (`migrate:fresh`, `migrate:reset`) or `DROP`/`TRUNCATE` SQL without explicit, highlighted user approval.
- **Forbidden:** Do not print or commit real values from `vams-webapp/.env` (API keys, `APP_KEY`, DB credentials).
- **Scope:** New tables/columns must be added via new migrations, never by editing already-applied migration files, unless explicitly told the environment is pre-launch and resettable.

## Contribution Workflow

- **Commit style:** Conventional commits (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`).
- **Branch format:** `feature/short-description`, `fix/issue-description`.

## Context Index

- [`context/PRD.md`](context/PRD.md) — Product requirements.
- [`context/ARCHITECTURE.md`](context/ARCHITECTURE.md) — System architecture.
- [`context/SCHEMA.md`](context/SCHEMA.md) — Database schema and API contracts.
- [`context/RULES.md`](context/RULES.md) — Coding standards and discovered gotchas.
- [`context/TASKS.md`](context/TASKS.md) — Active backlog and task state.
- [`TESTING_GUIDE.md`](TESTING_GUIDE.md) — End-to-end RFID testing workflow.
- [`NETWORK_CONFIG.md`](NETWORK_CONFIG.md) — Network setup and reader connectivity.
