# Contributing — HR SEO Assistant

Thank you for contributing!  
This document outlines workflow, coding conventions, hook prefixes, and release process for this plugin.

---

## Hook Prefix Policy
- `hr_sa_*` — HR SEO Assistant
- `hr_mh_*` — HR Media Help (hero connector)
- `hr_tk_*` — HR Toolkit (reserved)

### Standard Hooks
- `hr_sa_get_context` (filter): array SEO context for page/trip
- `hr_sa_image_preset` (filter): CDN preset string
- `hr_sa_conflict_mode` (filter): `'respect' | 'force'`
- `hr_sa_debug_enabled` (filter): bool
- `hr_sa_social_image_url` (filter): resolved OG image array
- `hr_sa_social_description` (filter): resolved social description string
- `hr_mh_current_hero_url` (filter): base hero URL | null
- `hr_mh_site_fallback_image` (filter): fallback image URL

---

## Repository Layout
```
core/        → settings, feature flags, context, compat
admin/       → menus, pages, debug, settings UI
modules/     → feature modules (jsonld, og, etc.)
integrations/→ safe connectors to other HR plugins
assets/      → admin CSS/JS
legacy-mu/   → reference only (do not load)
```

---

## Coding Basics
- Guard direct access: `if ( ! defined( 'ABSPATH' ) ) exit;`
- Prefix all functions, hooks, and options with `hr_sa_` (SEO Assistant).
- One emission source per concern (e.g., only one JSON-LD emitter active).
- No remote/AI calls during page render — AI features must be admin-only.
- Sanitize and validate all settings.
- Keep admin UI minimal and consistent with WP core look.

---

## Workflow & Branching
- **Default branch:** `main`
- **Feature branches:** `feature/<short-name>`
- **Fix branches:** `fix/<short-name>`
- All changes go through Pull Requests — no direct commits to `main`.
- Leave inline `// TODO:` or `@todo` comments if spec is unclear.

---

## Commits
- Prefer **Conventional Commits**:
  - `feat:` — new feature
  - `fix:` — bug fix
  - `chore:` — tooling/infra changes
  - `docs:` — documentation updates
  - `refactor:` — non-functional changes

Example:
```
feat: add Debug page for SEO context
fix: sanitize fallback image URL
```

---

## Versioning & Changelog
- Follow **Semantic Versioning (SemVer)**:
  - **MAJOR** → incompatible changes
  - **MINOR** → backwards-compatible features
  - **PATCH** → bug fixes / small changes
- Update both:
  - Plugin header in `hr-seo-assistant.php`
  - `CHANGELOG.md` with version + date
- Tag releases in Git:
  ```bash
  git tag -a v0.1.0 -m "Phase 0 scaffold + JSON-LD adoption"
  git push origin v0.1.0
  ```

---

## Testing
- Follow the manual QA steps in `TESTING.md`.
- Always compare JSON-LD output against `legacy-mu/` until MUs are retired.
- Debug page must remain functional at all times.

---

## Non-Goals
- Do not fold unrelated plugins (e.g., ACF Autofiller, Trips Widget).
- Do not bypass Conflict Mode or emit social tags outside the resolver/module flow.
- Do not expose AI integrations outside admin contexts or without explicit toggles.
- Do not implement sitemaps, redirects, or robots.txt editing unless added to roadmap.

---
