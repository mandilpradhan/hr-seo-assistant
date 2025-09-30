# Changelog — HR SEO Assistant

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Planned
- Future enhancements TBD.

## [0.4.0] - 2025-09-30
### Added
- Added a resolved HRDF inspector on the debug screen for quick verification.

### Changed
- Replaced all JSON-LD output with HRDF-sourced nodes and removed legacy emitters.
- Updated Open Graph, Twitter Card, and canonical emitters to rely solely on HRDF data.

### Removed
- Removed internal fallback generators and schema helpers that duplicated HRDF content.
- **1.0.0**: Stable release – OG + JSON-LD parity, diagnostics.
- **2.0.0**: AI Assist module (OpenAI) for titles/descriptions/keywords.

## [0.3.0] - 2025-10-07
### Added
- Module registry with independent enable/disable states and nonce-protected AJAX toggles persisted in `hrsa_modules_enabled`.
- Dedicated submenu pages for JSON-LD, Open Graph & Twitter Cards, and AI Assist reusing the legacy settings UI.
- HR SEO Overview dashboard cards with accessible toggles, status badges, and settings shortcuts using the HR UI token set.
- Documentation scaffold under `docs/` describing module management and release policy.

### Changed
- Admin asset pipeline now loads module toggle script data and shared HR UI styling tokens only on HR SEO screens.
- JSON-LD, Open Graph, AI Assist, and Debug hooks boot through the new module registry for fail-soft behaviour.

## [0.2.0] - 2025-09-24
### Added
- Open Graph and Twitter Card emission driven by the shared context provider with hero/fallback image resolution.
- Admin settings toggles for Open Graph and Twitter metadata, including optional Twitter handle field output and social snapshot on the Debug page.
- New filters `hr_sa_enable_og`, `hr_sa_enable_twitter`, `hr_sa_og_tags`, and `hr_sa_twitter_tags` for extensibility.

### Changed
- Context provider now resolves titles, descriptions, countries, and hero images from post data, templates, and plugin settings.
- Debug interface now surfaces social metadata, resolved values, and emission status alongside existing diagnostics.

## [0.1.0] - 2025-09-23
### Added
- Initial scaffold for **HR SEO Assistant** plugin.
- Admin pages: Overview, Settings, Modules, Debug (Debug gated by toggle).
- Settings schema: sitewide fallback image (media picker), title templates (Trips/Pages), locale, site name, Twitter handle, image preset, Respect/Force toggle, Debug toggle.
- Feature flags: `jsonld_enabled`, `og_enabled`, `debug_enabled`, `respect_other_seo`.
- Context provider stub: `hrsa_get_context()` (site basics + hero connector placeholder).
- **Legacy MU intake plan** documented; repo includes `/legacy-mu/` for reference.

### Notes
- No OG/Twitter emission yet (planned for 0.2.0).
- JSON-LD emitters to be adopted from **legacy MUs** in Phase 0 while preserving output parity.
