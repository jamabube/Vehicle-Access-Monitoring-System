# PRD — Product Requirements Document

> **Purpose:** Define the product problem, target audience, scope, technical constraints, and success criteria so developers and AI agents keep implementation strictly within intended bounds.

_Last updated: 2026-09-08 (RFID ingestion API — HMAC/nonce/debounce — completed)_

---

## 1. Executive Summary

[PLACEHOLDER: A concise 2–3 paragraph high-level overview of the VAMS capstone project's vision and value proposition. Known fact: this is a Vehicle Access Monitoring System for `Forest Lawn Memorial Park` (per `APP_ORGANIZATION` in `.env`), using RFID hardware for automated gate access control.]

---

## 2. Problem Statement & Context

[PLACEHOLDER: Explain the specific access-control problem this project solves for the organization (e.g. manual gate logging, lack of vehicle/visitor audit trail) and why the current process is inadequate.]

---

## 3. Core Goals & Objectives

[PLACEHOLDER: Enumerate primary objectives. Known technical objectives so far: automate vehicle/visitor entry-exit logging via UHF RFID, enforce role-based admin access, provide auditable access/system logs.]

- **Goal 1:** [e.g., Automatically log every vehicle entry/exit event via RFID with no manual gate logbook.]
- **Goal 2:** [e.g., Provide administrators a real-time dashboard of who/what is currently inside the premises.]
- **Goal 3:** [e.g., Maintain a tamper-evident audit trail of all administrative actions.]

---

## 4. Target Users & Audience

[PLACEHOLDER: Confirm exact personas and access levels for this deployment.] Known structural fact: the `users` table supports a `role_id` → RBAC model, and the domain distinguishes `employees` (permanent vehicle owners) from `visitors` (temporary, per-visit access).

- **Primary Persona:** [e.g., Security Guard / Gate Officer — monitors real-time entry/exit, resolves denied-access alerts.]
- **Secondary Persona:** [e.g., System Administrator — manages employees, vehicles, RFID tags, and user accounts.]
- **User Roles & Access Levels:** Three roles seeded via `database/seeders/RoleSeeder.php`: **Administrator** (full access, including user accounts and system settings), **Security Officer** (read-only visibility into the dashboard, access logs, employees, visitors, vehicles, and RFID tags/readers — no create/update/delete), **Encoder/Registrar** (manages employees, visitors, vehicles, and RFID tags/assignments/readers day-to-day — view/create/update only, no delete on any resource; no user account or system settings access). See `context/SCHEMA.md` §4 for the concrete permission slugs per role.

---

## 5. MVP Features (Scope)

Known in-progress/planned MVP scope, confirmed against the existing schema and prior planning:

- [x] **RBAC data model:** `roles`, `permissions`, `role_permissions`, `users.role_id` — schema done; enforcement middleware and seeders (3 roles) done.
- [x] **Auth with lockout:** Login/logout using `users.failed_login_attempts` / `locked_until`, fully tested end-to-end.
- [x] **Employee & Vehicle management:** Admin CRUD for `employees` and `vehicles`.
- [x] **Visitor & Visit management:** Admin CRUD for `visitors` done; `visitor_visits` (check-in/check-out including temporary RFID card assignment/release) done.
- [x] **RFID Tag & Reader management:** Admin CRUD for `rfid_tags`, `rfid_readers`, `rfid_assignments`.
- [x] **RFID Ingestion API:** HMAC-signed, nonce-protected, debounced endpoint (`POST /api/rfid/detections`) consumed by the external Windows RFID Listener/Device Service.
- [ ] **Access/Audit/System log dashboards:** Reporting views over `access_logs`, `audit_logs`, `system_logs`.
- [x] **RFID Listener/Device Service:** `php artisan rfid:listen` reads the S4A UHF-202415 over its native MM binary protocol and posts HMAC-signed detections to the API.

---

## 6. Full Feature List & Prioritization

[PLACEHOLDER: Categorize remaining features using MoSCoW once MVP scope above is confirmed with the user/thesis adviser.]

### Must-Have (P0 — Launch Blockers)

- [x] **RBAC enforcement + auth lockout:** Done — required before any admin CRUD can be safely exposed.
- [x] **RFID ingestion API security (HMAC/nonce/debounce):** Done — required before the physical reader can be trusted as an input source.

### Should-Have (P1 — High Priority Post-MVP)

- [ ] **Reporting dashboards:** Access/audit/system log views with filtering.

### Could-Have (P2 — Nice to Have)

- [ ] **[PLACEHOLDER: e.g., Email/SMS alerting on repeated denied-access attempts.]**

### Won't-Have (Out of Scope for Current Milestone)

- [ ] **[PLACEHOLDER: e.g., Multi-site/multi-gate support beyond the current single-organization deployment.]**

---

## 7. Success Metrics & KPIs

[PLACEHOLDER: Define measurable signals — this is a capstone/thesis project, so metrics may be academic (e.g., defense evaluation criteria) rather than production KPIs.]

- **Functional Reliability:** [e.g., RFID detection-to-access-log resolution succeeds for 100% of valid credential reads during demo/testing.]
- **Security:** [e.g., Zero successful replay attacks against the RFID ingestion API in penetration testing.]
- **Academic/Capstone Criteria:** [PLACEHOLDER: reference the manuscript's stated success criteria, e.g. `capstone-2_manuscript_old-format_updated.docx`.]

---

## 8. Tech Stack & Technical Requirements

Confirmed from the actual codebase (`vams-webapp/composer.json`, `package.json`, `.env`):

- **Backend Framework:** Laravel `^12.0`, PHP `^8.2`.
- **Frontend:** Blade templates + Vite `7` + TailwindCSS `4` (no SPA framework wired in yet).
- **Database:** MySQL, database `vams_laravel`.
- **Testing:** PHPUnit `^11.5.50`.
- **Tooling:** Laravel Pint (style), Laravel Sail (optional containerized dev env), Laravel Pail (log tailing), Faker (test data).
- **Hardware:** S4A UHF-202415 UHF RFID reader.
- **Key Device Component:** The RFID Listener/Device Service (`php artisan rfid:listen`) bridging the physical reader to this Laravel app's API via HMAC-signed HTTP requests. Stack decided: PHP 8.2 Laravel console command — a separate process from the web server, in the same codebase (see `context/ARCHITECTURE.md` §8).

---

## 9. Deployment & Infrastructure Strategy

[PLACEHOLDER: Define hosting/deployment target — likely a local/on-premise deployment for a physical gate given the RFID hardware dependency, rather than cloud hosting. Confirm with the capstone requirements.]

- **Hosting Platforms:** [PLACEHOLDER: e.g., on-premise Windows/XAMPP server at the deployment site.]
- **Environments:** Development (local XAMPP + MySQL, confirmed in use). Staging/Production: [PLACEHOLDER].
- **CI/CD Pipeline:** [PLACEHOLDER: none configured yet.]

---

## 10. Phase Roadmap

Derived from the current, in-progress build plan (see `context/TASKS.md` for granular tracking):

- **Phase 1 (MVP):** RBAC middleware/policies + auth lockout logic; admin CRUD for Employees/Visitors/Vehicles/RFID Tags/Readers; RFID ingestion REST API with HMAC/nonce/debounce.
- **Phase 2 (Enhancement):** Dashboard/reporting views (access logs, audit logs, system logs); Windows RFID Listener/Device Service build-out.
- **Phase 3 (Scaling/Wrap-up):** Manuscript review/simplification and final capstone documentation alignment.
