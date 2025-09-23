# HR SEO Assistant - Roadmap

**Plugin:** HR SEO Assistant  
**Version:** 0.1.0 (Scaffold phase)  
**Scope:** Consolidates SEO/social metadata into one basket plugin:
- Open Graph + Twitter Cards
- JSON-LD Schema
- Debug surface
- AI-assist (later phases)

---

## Vision
To provide a single, lightweight SEO assistant plugin that:
- Eliminates dependency on heavy SEO suites (Rank Math, Yoast, etc.)
- Ensures Open Graph, Twitter Cards, and JSON-LD are consistent
- Integrates with HR Media Help for hero-based social images
- Adds optional AI-powered SEO helpers for content creation
- Keeps full editorial control while reducing bloat

---

## Phase 0 — Scaffold & Adoption
**Goal:** Create a new plugin scaffold and adopt JSON-LD modules.

### Features
- Plugin skeleton (`hr-seo-assistant`)
- Settings page with:
  - Sitewide fallback image (Media Picker)
  - Title templates (Trips, Pages)
  - Locale, Site Name, Twitter handle
  - Image preset
  - Respect/Force toggle
  - Debug toggle
- Feature flags: `jsonld_enabled`, `og_enabled`, `debug_enabled`
- `hrsa_get_context()` stub (returns site basics + hero connector if present)
- Connector: safe filter for hero (`hr_current_hero_url`)
- Debug page (only visible if Debug = ON)
  - Environment info
  - Context values
  - Connector status
  - Settings snapshot
  - Module status

### Deliverables
- Identical JSON-LD output (moved into plugin, no changes yet)
- No OG/Twitter emission yet
- Debug page available

### Acceptance
- Plugin activates cleanly
- JSON-LD emits identically to current MUs
- Debug shows context, settings, and connectors
- No OG/Twitter tags yet

---

## Phase 1 — OG/Twitter Integration
**Goal:** Emit OG/Twitter tags using context.

### Features
- `og:title`, `og:description`, `og:image`, `og:url`, `og:type`, `og:site_name`, `og:locale`
- Twitter: `summary_large_image` + mirrored data
- Hero → fallback image logic
- Title templates applied
- Description fallback order
- Admin preview of what will emit
- Respect/Force mode active

### Acceptance
- Pages/Trips/Home emit exactly one OG/Twitter set
- Social images resolve to Hero or fallback
- Titles/descriptions align with templates
- Scrapers show correct previews

---

## Phase 2 — Overrides & Diagnostics
**Goal:** Add polish and per-content controls.

### Features
- Optional per-page Social Image & Social Description fields
- Admin bar badge showing OG source
- Schema & OG alignment (image parity)
- Expanded debug info (render decisions, warnings)

---

## Phase 3 — AI Assist
**Goal:** Add OpenAI-powered SEO helpers.

### Features
- On-demand generation of Title, Description, Keywords
- One-click “apply” to post meta or plugin meta
- Batch generation with preview + dry run
- Configurable provider/API key
- Logs & rate limits
- Strict separation: never runs at render time

---

## Out of Scope
- Sitemaps
- Redirects
- Robots.txt editing (manual file only)
- Breadcrumbs (handled elsewhere)

---

## Risks & Mitigations
- **Missing hero** → site fallback covers
- **SEO conflicts** → Respect/Force toggle
- **Cache & scraper delays** → clear cache, re-scrape manually
- **AI misuse** → on-demand only, with preview

---

## Versioning Plan
- **0.1.0** Scaffold + JSON-LD adoption
- **0.2.0** OG/Twitter emitter (basic)
- **0.3.0** Templates + fallback polish
- **1.0.0** Stable: JSON-LD + OG parity, debug, settings
- **1.x.x** Incremental refinements
- **2.0.0** Major: AI assist rollout

---

## Naming Scheme (filters/actions & modules)

**Prefixes**
- `hr_sa_*` → HR SEO Assistant (this plugin)
- `hr_mh_*` → HR Media Help (hero connector)
- `hr_tk_*` → HR Toolkit (reserved)

**Canonical hooks**
- `hr_sa_get_context` (filter) → final SEO context array
- `hr_sa_image_preset` (filter) → CDN preset string (default: w=1200,fit=cover,gravity=auto,format=auto,quality=75)
- `hr_sa_conflict_mode` (filter) → 'respect' | 'force'
- `hr_sa_debug_enabled` (filter) → bool
- `hr_mh_current_hero_url` (filter) → base hero URL (or null)
- `hr_mh_site_fallback_image` (filter) → fallback social image URL

Reason: unambiguous debugging across baskets; prefixes must be used in all new hooks.
