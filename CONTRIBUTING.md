# Contributing — HR SEO Assistant

## Hook Prefix Policy
- `hr_sa_*` — HR SEO Assistant
- `hr_mh_*` — HR Media Help (hero connector)
- `hr_tk_*` — HR Toolkit (reserved)

### Standard Hooks
- `hr_sa_get_context` (filter): array SEO context for page/trip
- `hr_sa_image_preset` (filter): CDN preset string
- `hr_sa_conflict_mode` (filter): 'respect' | 'force'
- `hr_sa_debug_enabled` (filter): bool
- `hr_mh_current_hero_url` (filter): base hero URL | null
- `hr_mh_site_fallback_image` (filter): fallback image URL

## Semver & Changelog
- Use Semantic Versioning.
- Update CHANGELOG.md for every release.
- Tag releases: v0.1.0, v0.2.0, …

## Coding Basics
- Guard direct access: `if (!defined('ABSPATH')) exit;`
- One emission source per concern (no duplicate OG/JSON-LD).
- No remote/AI calls during page render; admin-only for AI features.

## Commits
- Prefer Conventional Commits: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`.
