# RULES — Coding Standards & Architecture Rules

> **Purpose:** Specify project coding rules, language standards, architecture guidelines, and discovered environment gotchas so all generated code is clean, consistent, and safe to run in this workspace.

_Last updated: 2026-09-08_

---

## 1. Language & Framework Standards

- **Language:** PHP `^8.2`, Laravel `^12.0` (confirmed via `vams-webapp/composer.json`).
- **Style guide:** Laravel Pint (`vendor/bin/pint`), the framework-default preset. Run before committing.
- **Strict typing:** [PLACEHOLDER: decide whether `declare(strict_types=1)` is mandatory for new PHP files; not yet enforced in the current codebase.]
- **Eloquent models:** Live in `vams-webapp/app/Models/`. All 16 models (User + 15 domain models) already exist with relationships, casts, and helper methods — see `context/SCHEMA.md` for the columns they map to.

---

## 2. Naming Conventions

- **Migrations:** `YYYY_MM_DD_HHMMSS_description.php`, snake_case description (Laravel default).
- **Models:** PascalCase singular (`RfidTag`, `VisitorVisit`), matching Eloquent conventions for their plural snake_case tables (`rfid_tags`, `visitor_visits`).
- **Database columns:** snake_case, matching existing schema in `context/SCHEMA.md`.
- **Routes:** [PLACEHOLDER: define REST route naming/prefix convention once controllers are built, e.g. `admin.employees.index`.]

---

## 3. Environment & Local Dev Gotchas (Verified)

These were discovered the hard way in this exact workspace — treat them as hard constraints, not suggestions:

- **Stray environment variables override `.env`:** This Windows/PowerShell session (and possibly others spawned the same way) carries leftover process-level `APP_*`/`DB_*` environment variables (e.g. a stale `DB_DATABASE=vams`) that silently override `vams-webapp/.env` values for every `php artisan` invocation, because Laravel's Dotenv never overrides variables already set in the OS process environment. **Always** run this before any `php artisan` command if output looks like it's hitting the wrong database or app name:

  ```powershell
  Get-ChildItem Env: | Where-Object { $_.Name -like 'APP_*' -or $_.Name -like 'DB_*' } | ForEach-Object { Remove-Item "Env:$($_.Name)" -ErrorAction SilentlyContinue }
  ```

- **MySQL strict mode + `timestamp()`:** Do not use `$table->timestamp('col')` for a **non-nullable column with no default value** — MySQL strict mode rejects it with `Invalid default value for 'col'`. Use `$table->dateTime('col')` instead for required datetime columns without a default (already applied to `visitor_visits.valid_from/valid_until`, `rfid_assignments.assigned_at`, `rfid_detections.detected_at/received_at`, `access_logs.occurred_at`, `api_request_nonces.expires_at`).
- **Two *copies of the whole project* exist on this machine.** `C:\Users\jamabube\Downloads\Vehicle Access Monitoring System\` is the live one; `C:\Users\jamabube\Desktop\Vehicle Access Monitoring System\` is a stale earlier copy. On 2026-09-09 the Desktop copy was found still serving `127.0.0.1:8000` from ~10 orphaned processes (four `composer dev` stacks, plus a server bound to the PC's old `192.168.1.21` address). Because both copies point at the same `vams_laravel` database, everything *appeared* to work — but the API answering requests was the Desktop copy, using its own older `.env`, so config changes made in `Downloads\` silently had no effect. **If an `.env`/config change seems not to apply, check who owns port 8000 before debugging anything else:** `Get-NetTCPConnection -LocalPort 8000 -State Listen`, then look up the owning PID's command line.
- **`config/app.php` hardcoded the timezone.** It shipped with `'timezone' => 'UTC'` rather than Laravel's usual `env('APP_TIMEZONE', 'UTC')`, so setting `APP_TIMEZONE` in `.env` did nothing at all. Fixed 2026-09-09 to read the env var; `.env` now sets `Asia/Manila`. Worth remembering as a class of bug: **a `config/*.php` key that ignores `env()` makes the corresponding `.env` line silently inert.**
- **Two databases on one MySQL instance:** `vams_laravel` is this project's dedicated database. A separate, unrelated `vams` database exists from an older ESP32/fingerprint-based project — **never** point this app's `.env` at `vams`, and never run migrations/seeders against it.
- **Testing without a full test suite yet:** Prefer writing real PHPUnit tests (`vams-webapp/tests/`) over ad-hoc throwaway scripts. Any throwaway verification script created for manual checking (e.g. a `tinker_test.php` at the project root) must be deleted immediately after use — it is not part of the codebase.

---

## 4. SOLID & Laravel Conventions

- **Single Responsibility:** Keep validation in Form Request classes, business logic in the model or a dedicated service/action class, and controllers thin (orchestration only).
- **Fat model, thin controller (Laravel default):** Domain helper methods (e.g. `isActive()`, `isOnline()`, `fullName()`, `hasPermission()`) belong on the model, not duplicated in controllers.
- **Dependency Inversion:** Prefer constructor-injected services/repositories for anything that talks to external systems (e.g. the future RFID ingestion signature verifier), so it can be swapped/mocked in tests.

---

## 5. Validation & Security Rules

- **Boundary validation:** All incoming HTTP request data — admin CRUD forms and the RFID device ingestion API alike — must be validated via Laravel Form Request classes. Never trust raw `$request->input()` without validation rules.
- **RFID device API security (non-negotiable, per `.env` config already present):**
  - Every device-service request must be **HMAC-signed** using the reader's `api_secret_hash`-backed secret.
  - **`rfid_readers.api_secret_hash` stores an *encrypted* value (`Crypt::encryptString()`, AES-256 keyed off `APP_KEY`), not a one-way hash**, despite its column name. This is deliberate: true HMAC verification requires the server to recompute `HMAC(secret, payload)` and compare it to the signature the device sent, which is impossible if only a bcrypt hash is on file (bcrypt cannot be reversed or recomputed deterministically). `RfidReaderController` decrypts it via `Crypt::decryptString()` at verification time. **Do not "fix" this back to `Hash::make()`/`Hash::check()`** — that would silently break HMAC verification. See `RfidReaderController::store()`/`regenerateCredentials()` and the RFID ingestion API's signature verifier for the concrete usage.
  - Signature timestamp must be checked against `RFID_HMAC_TOLERANCE_SECONDS` (currently `120`) to reject stale/future-dated requests.
  - Every request must carry a unique nonce, checked/stored in `api_request_nonces` with TTL `RFID_NONCE_TTL_SECONDS` (currently `600`) to reject replays.
  - Duplicate detections within `RFID_DEBOUNCE_SECONDS` (currently `5`) of a prior detection for the same EPC must be flagged via `rfid_detections.is_duplicate` rather than double-processed into `access_logs`.
- **Rate limiting (manuscript objective 8):** Sensitive endpoints carry named limiters defined in `AppServiceProvider::configureRateLimiting()` — `throttle:rfid-ingestion` on the device API (keyed on `X-Rfid-Api-Key`, so one bad reader cannot starve the others) and `throttle:login`. **Keep the throttle middleware ahead of `rfid.hmac`** so a flood is shed before signature verification and a DB lookup. Both limiters record rejections to `system_logs`; that write is wrapped in try/catch on purpose — shedding load must not create work.
- **Auth lockout:** Use `users.failed_login_attempts` and `users.locked_until` for brute-force lockout logic; never roll a custom auth flow that bypasses these columns. Note this is *separate from* and complementary to the `login` rate limiter: lockout protects one account, the limiter protects the endpoint.
- **No secrets in code:** DB credentials and `APP_KEY` must only ever live in `.env`, never hardcoded or logged in plaintext. Per-reader `api_key`/`api_secret` credentials are generated server-side per `rfid_readers` row (see `RfidReaderController`) — there is no single global `RFID_DEVICE_API_KEY`/`RFID_DEVICE_API_SECRET` pair in `.env`; only the shared tuning knobs (`RFID_HMAC_TOLERANCE_SECONDS`, `RFID_NONCE_TTL_SECONDS`, `RFID_DEBOUNCE_SECONDS`, surfaced via `config/rfid.php`) live there.

---

## 6. Error Handling & Logging

- Use `system_logs` (with `level`, `source`, optional `rfid_reader_id`, `context` JSON) for operational/device-service logging, and `audit_logs` (with `user_id`, `action`, polymorphic `subject_type`/`subject_id`, `details` JSON) for human-driven administrative actions (e.g. `employee.created`, `rfid_tag.deactivated`). Do not conflate the two.
- Never leak raw database errors, stack traces, or internal exception messages in HTTP responses — especially on the RFID ingestion API, which is a trust boundary open to networked hardware.

---

## 7. Testing & Verification Conventions

- **Framework:** PHPUnit `^11.5.50` (already installed, `phpunit.xml` present). Run via `php artisan test` or `composer test`.
- **Test placement:** `vams-webapp/tests/Unit/` for isolated model/service logic, `vams-webapp/tests/Feature/` for HTTP-level and full request/response flows (admin CRUD, RFID ingestion API).
- **Coverage priority:** Auth/lockout logic, RFID HMAC/nonce/debounce logic, and RBAC permission checks are the highest-value areas to test first given the security stakes.

---

## 8. Commit & PR Conventions

- **Commit messages:** Conventional Commits (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`).
- **Branch naming:** `feature/short-description`, `fix/issue-description`.
- [PLACEHOLDER: confirm actual git remote/branching workflow once the repo is pushed to a hosting provider — not yet configured as of this writing.]
