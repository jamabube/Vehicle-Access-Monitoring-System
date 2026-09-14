# Agent Behavioral Standard (Cline Adapter)

This workspace enforces the `agent-spec` behavioral standard (imported as a sibling reference at `agent-spec/`).

- **Normative Base:** Follow `AGENTS.md` and `agent-spec/spec/core/`.
- **Precedence & Discovery:** Follow `agent-spec/spec/core/instruction-hierarchy.md`.
- **Decision Framework:** Follow `agent-spec/spec/core/decision-framework.md`.
- **Output Policy:** Follow `agent-spec/spec/core/output-policy.md`.
- **Safety Boundary:** Follow `agent-spec/spec/core/safety.md`.

## Workspace Layout

- `vams-webapp/` — the actual Laravel 12 application (target of all code changes).
- `agent-spec/` — the imported governance standard itself. Do not modify unless the user explicitly asks to update the standard.
- `context/` — this project's filled-in PRD/ARCHITECTURE/SCHEMA/RULES/TASKS templates. Read these before making architecture or schema assumptions.
- `_extracted/`, `capstone-2_manuscript_old-format_updated.docx` — capstone manuscript/documentation assets, unrelated to the running application code.

## Non-Negotiables

- Never invent database column names or table structures. Verify against `context/SCHEMA.md` or the real migrations in `vams-webapp/database/migrations/`.
- Never run destructive database commands (`migrate:fresh`, `DROP`, `TRUNCATE`) without explicit, highlighted user approval.
- Never commit or print real secrets from `vams-webapp/.env`.
