# Changelog — HR SEO Assistant

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Planned
- **1.0.0**: Stable release – OG + JSON-LD parity, diagnostics.
- **2.0.0**: AI Assist module (OpenAI) for titles/descriptions/keywords.

## [0.3.0] - 2025-09-30
### Added
- Modules page controls with live toggles, Conflict Mode awareness, and a reset-to-defaults action for JSON-LD, Open Graph & Twitter, AI Assist, and Debug.
- Social image resolver shared across emitters, debug tooling, and a new admin bar badge that reports the current OG image source.
- Per-page social overrides for image and description, plus an AI instruction/style guide setting that guides admin-only generation workflows.

### Changed
- Bundled Open Graph and Twitter Card output behind a single module that honours Respect/Force Conflict Modes and other SEO plugins.
- Debug diagnostics now surface AI instruction text, token usage, module statuses, and social image source details.
- AI generation enforces tighter title/description limits, supports 3–8 keyword guidance, and records token usage for transparency.

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
