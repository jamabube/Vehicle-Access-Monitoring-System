# Project Facts & Coding Standards

- **Product & Architecture:** See `context/PRD.md` and `context/ARCHITECTURE.md`.
- **Coding Rules & Discovered Gotchas:** See `context/RULES.md`.
- **Database & API Contracts:** See `context/SCHEMA.md`.
- **Active Backlog:** See `context/TASKS.md`.

## Quick Stack Reference

- **App:** `vams-webapp/` — Laravel 12, PHP 8.2, MySQL (`vams_laravel` database).
- **Frontend:** Blade + Vite 7 + TailwindCSS 4 (no SPA framework wired yet).
- **Testing:** PHPUnit 11.5 via `php artisan test` / `composer test`.
- **Formatting:** Laravel Pint (`vendor/bin/pint`).
- **Hardware integration:** S4A UHF-202415 UHF RFID reader, read by a separate (not yet built) Windows RFID Listener/Device Service that posts HMAC-signed detections to this Laravel app's API.

## Session Gotcha (Windows/PowerShell)

Before running any `php artisan` or `php` command in a fresh PowerShell session in this workspace, check for leftover process-level `APP_*`/`DB_*` environment variables that silently override `vams-webapp/.env` (Dotenv does not override pre-set OS env vars). If `artisan` commands appear to target the wrong database, run:

```powershell
Get-ChildItem Env: | Where-Object { $_.Name -like 'APP_*' -or $_.Name -like 'DB_*' } | ForEach-Object { Remove-Item "Env:$($_.Name)" -ErrorAction SilentlyContinue }
```

before the `php artisan ...` invocation.
