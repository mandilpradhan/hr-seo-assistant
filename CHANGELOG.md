# Changelog — HR SEO Assistant

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

## [Unreleased]
### Planned
- **0.2.0**: OG/Twitter emitter (hero → fallback), minimal templates.
- **0.3.0**: Template polish, description fallback order, preview card.
- **1.0.0**: Stable release – OG + JSON-LD parity, diagnostics.
- **2.0.0**: AI Assist module (OpenAI) for titles/descriptions/keywords.
