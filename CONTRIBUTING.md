# Contributing to AGILE OUS

## Principles
- The deliverable is a **fully functional PHP/MySQL application**, not a prototype.
- Preserve all approved features; proposed changes need requirements traceability.
- Use **synthetic** example students/cases only. Never commit actual grades, CORs, student identifiers, welfare narratives, Gmail credentials or API tokens.
- No deployment, live email sends or real-data imports without authorization.
- Changes affecting grades, eligibility, access to welfare cases and role assignments must include authorized human review.

## Branching and review
- `main`: release-quality stable line only.
- `feat/<ticket>-<name>`: new features.
- `fix/<ticket>-<name>`: defects.
- `docs/<name>`: documentation.
- `chore/<name>`: maintenance.
- `hotfix/<name>`: approved urgent production fixes.

Create a branch from the latest protected base. Make focused commits (e.g. `feat(membership): validate application submissions`). Submit a PR with requirement IDs, test evidence, privacy impact, screenshots using dummy data, and rollback notes.

Recommended GitHub settings for `main`: require PR reviews and CODEOWNERS, block direct pushes and force pushes/deletion, require passing `Quality Gate` checks, require resolved conversations, and restrict administrators from bypassing where feasible. **These settings must be enabled manually by the repository owner or an authorized admin.**

## Local quality gate
1. Check for accidental secrets and student data.
2. PHP syntax: `find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;`
3. Run automated tests and database migration/rollback checks for changed modules.
4. Confirm negative access tests (attempt forbidden actions), not only happy paths.
5. State clearly which features are implemented, simulated, or blocked on integration.

## Change management
Use a migration for schema changes, never alter production data directly in code review. New permissions must be explicitly granted. When an AI/OCR feature is added, show extracted values to an authorized human; never automatically remove someone from an office.
