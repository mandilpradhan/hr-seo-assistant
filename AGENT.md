# HR SEO Assistant — Agent Instructions

This file is for any code generation agent (Codex or similar) working on this repository.  
Follow these conventions and guardrails at all times.

---

## Plugin Identity
- **Name:** HR SEO Assistant
- **Slug/Folder:** `hr-seo-assistant`
- **Prefix:** `hr_sa_`
- **Purpose:** Provide SEO scaffolding for Himalayan Rides websites.
  - JSON-LD (Schema.org) emitters (Trips, FAQ, Itinerary, Organization, Vehicles).
  - OG/Twitter/social meta tags (future phase).
  - Optional AI helpers (title, description, keyword generation) (future phase).

---

## Naming Scheme
- **HR SEO Assistant:** `hr_sa_*`
- **HR Media Help (external plugin):** `hr_mh_*`
- **HR Toolkit (reserved):** `hr_tk_*`

Examples:
- `hr_sa_get_context` (filter) → main SEO context array
- `hr_mh_current_hero_url` (filter) → hero image URL provider

This scheme **must** be carried consistently across all modules.

---

## Repository Layout

hr-seo-assistant/
├─ hr-seo-assistant.php # bootstrap
├─ core/ # core helpers
├─ admin/ # admin UI
├─ modules/ # feature modules
│ ├─ jsonld/ # JSON-LD emitters
│ └─ og/ # OG/Twitter emitters (stub until Phase 1)
├─ integrations/ # safe connectors (e.g., Media Help)
├─ assets/ # admin CSS/JS
├─ legacy-mu/ # reference only, do not load
├─ README.md
├─ ROADMAP.md
├─ TESTING.md
├─ CHANGELOG.md
├─ CONTRIBUTING.md
├─ AGENT.md # this file
└─ ROBOTS.txt


---

## Development Workflow
- **Versioning:** bump plugin header + `CHANGELOG.md` on every release.
- **Legacy parity:** compare JSON-LD outputs against files in `legacy-mu/` before removing MUs.
- **Debug page:** always provide full context visibility, toggled by setting.
- **Phases:**
  - **Phase 0:** Scaffold + JSON-LD adoption, OG OFF.
  - **Phase 1:** Implement OG/Twitter using hero images or fallback.
  - **Phase 2:** AI-assisted SEO helpers (OpenAI API).

---

## Guardrails
- Never hardcode site-specific domains, IDs, or tokens. Use filters/options.
- Do not silently overwrite other SEO plugin output unless **Conflict Mode = Force**.
- Do not emit OG/Twitter tags until Phase 1.
- Do not fold unrelated custom plugins (ACF Autofiller, Trips Widget, etc.) into this project.
- Always sanitize & validate option values.

---

## Debug Page Expectations
When Debug mode is enabled:
- Show environment (post ID/type/url).
- Show flags (jsonld, og, debug, conflict).
- Show settings snapshot.
- Show connector state (hero image resolved or not).
- Show assembled SEO context (`hr_sa_get_context`).
- Show module status (active/inactive).
- Optional: Copy-as-JSON button.

---

## Contribution Style
- Always create/update files in correct directories.
- Keep admin UI minimal, consistent with WP core look.
- Use feature flags for all modules.
- Inline TODOs are allowed if spec is unclear — never invent features outside scope.

---

## Communication
If spec is incomplete or ambiguous:
1. Leave clear `// TODO:` or `@todo` comments.
2. Avoid assumptions that lock future development paths.
3. Defer OG/Twitter and AI until explicitly greenlit in roadmap.

---

