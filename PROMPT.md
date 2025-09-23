# 🏁 Codex Kickoff Message

We are starting **Phase 0** of a new WordPress plugin project.

**Plugin name:** HR SEO Assistant  
**Goal for Phase 0:**  
- Scaffold the plugin with full file/folder structure, admin UI, settings, feature flags, debug page, and JSON-LD emitters (ported from our legacy MU plugins).  
- **Do not** implement OG/Twitter/social tags yet — that will be **Phase 1**.  
- **Do not** implement AI integration yet — that will be **Phase 2**.

You will receive a detailed spec next. Please follow it literally, keeping naming conventions, prefixes, and file layout exact. If anything is ambiguous, leave clear TODO comments instead of guessing. Focus only on Phase 0.

---

# 🔧 CODEX BUILD BRIEF — HR SEO Assistant (Phase 0: Scaffold & JSON-LD Adoption, OG OFF)

**Repo:** `git@github.com:mandilpradhan/hr-seo-assistant.git`  
**Target path on server:** `/home/customer/www/himalayanrides.com/public_html/wp-content/plugins/hr-seo-assistant`  
**Do not modify:** anything in `legacy-mu/` (reference only)  
**Production status:** site is NOT in production; safe to scaffold and activate

## 0) Phase 0 Objectives
1. Create a **modular plugin scaffold** with settings, feature flags, admin pages, and a **debug page**.
2. **Adopt/port** existing JSON-LD emitters from `legacy-mu/` into the new plugin **with output parity**.
3. Provide a shared **context** function that later OG and JSON-LD will consume (`hr_sa_get_context`).
4. **Do not** emit OG/Twitter tags in Phase 0 (keep OG module stub present but disabled).
5. Preserve versioning/docs files and hook naming scheme.

---

## 1) Naming & Hook Prefix Policy (MUST)
- **Plugin name:** HR SEO Assistant
- **Slug/Dir:** `hr-seo-assistant`
- **Hook prefixes:**
  - `hr_sa_*` → HR SEO Assistant (this plugin)
  - `hr_mh_*` → HR Media Help (external hero connector)
  - `hr_tk_*` → HR Toolkit (reserved; do not use now)

**Canonical hooks to register / respect**
- `hr_sa_get_context` (filter) → returns final SEO context array (assoc)
- `hr_sa_image_preset` (filter) → string preset, default `w=1200,fit=cover,gravity=auto,format=auto,quality=75`
- `hr_sa_conflict_mode` (filter) → `'respect' | 'force'` (string)
- `hr_sa_debug_enabled` (filter) → bool
- `hr_mh_current_hero_url` (filter) → base hero URL (string) or `null` (filter may not exist; handle safely)

> The hook names above and the `hr_sa_` / `hr_mh_` prefixes are **contractual**. Use them verbatim.

---

## 2) Directory & File Structure (CREATE EXACTLY)

```
hr-seo-assistant/
├─ hr-seo-assistant.php                # plugin bootstrap (exists; extend it)
├─ core/
│  ├─ settings.php                     # register options, defaults, sanitization
│  ├─ feature-flags.php                # jsonld_enabled, og_enabled, debug_enabled, respect_other_seo
│  ├─ context.php                      # hr_sa_get_context() implementation (Phase 0 stub)
│  └─ compat.php                       # detect other SEO plugins; expose helpers
├─ admin/
│  ├─ menu.php                         # adds admin menu + routes to pages
│  └─ pages/
│     ├─ overview.php                  # basic intro & status
│     ├─ settings.php                  # settings UI (see section 3)
│     ├─ modules.php                   # toggles / read-only for Phase 0
│     └─ debug.php                     # full debug surface (gated by Debug toggle)
├─ modules/
│  ├─ jsonld/                          # JSON-LD emitters (ported from legacy)
│  │  ├─ loader.php                    # orchestrates JSON-LD output
│  │  ├─ org.php                       # Organization graph (if applicable)
│  │  ├─ trip.php                      # Trip/Product graph (adopt)
│  │  ├─ itinerary.php                 # Itinerary / ItemList (adopt)
│  │  ├─ faq.php                       # FAQPage (adopt)
│  │  └─ vehicles.php                  # Vehicle offers (adopt)
│  └─ og/
│     └─ loader.php                    # present but **disabled** in Phase 0
├─ integrations/
│  └─ media-help.php                   # safe connector to hr_mh_current_hero_url
├─ assets/
│  ├─ admin.css                        # minimal admin styles
│  └─ admin.js                         # optional; keep tiny
├─ legacy-mu/                          # DO NOT MODIFY — reference only
│  ├─ hr-schema-core.php
│  ├─ hr-trip-schema-graph.php
│  └─ hr-wte-vehicle-offers.php
├─ README.md
├─ ROADMAP.md
├─ TESTING.md
├─ CHANGELOG.md
├─ CONTRIBUTING.md
├─ AGENT.md
└─ ROBOTS.txt
```

**Important:** Extend the existing `hr-seo-assistant.php` bootstrap to load the new modules.

---

## 3) Settings (Phase 0 UI & Storage)

Create a **Settings** page (WP Admin → left nav **HR SEO** → **Settings**) with these fields:

- **Fallback Image (Sitewide):** media picker (string URL)  
  - option: `hr_sa_fallback_image`
- **Title Templates:**
  - Trips: `{{trip_name}} | Motorcycle Tour in {{country}}` → `hr_sa_tpl_trip`
  - Pages: `{{page_title}}` (boolean “Append brand suffix” toggle)  
    - options: `hr_sa_tpl_page`, `hr_sa_tpl_page_brand_suffix` (bool)
- **Locale:** default `en_US` → `hr_sa_locale`
- **Site Name:** prefill from `get_bloginfo('name')` (editable) → `hr_sa_site_name`
- **Twitter Handle:** `@himalayanrides` (optional) → `hr_sa_twitter_handle`
- **Image Preset (CDN):** default `w=1200,fit=cover,gravity=auto,format=auto,quality=75` → `hr_sa_image_preset`
- **Conflict Mode:** radio: **Respect** (default) / **Force** → `hr_sa_conflict_mode`
- **Debug Mode:** toggle → `hr_sa_debug_enabled`

**Sanitization rules**
- URLs must be absolute HTTPS.
- Strings trimmed; normalize Twitter handle to include `@`.
- Locale must match `xx_XX` pattern or fallback to `en_US`.

**UI notes**
- Use core WP settings API.  
- Group fields in clear sections (General, Titles, Images, Advanced).  
- After save, show admin notice.

---

## 4) Feature Flags (Phase 0 states)

Store as options (bools), exposed via `core/feature-flags.php` helpers:

- `hr_sa_jsonld_enabled` (default **true**)
- `hr_sa_og_enabled` (default **false** in Phase 0)
- `hr_sa_debug_enabled` (default **false**)
- `hr_sa_respect_other_seo` (default **true**, derived from conflict mode)

Admin **Modules** page should show read-only status in Phase 0 (toggles can be added in Phase 1).

---

## 5) Admin Pages

Create menu **HR SEO** with subpages: **Overview**, **Settings**, **Modules**, **Debug**.  
`Debug` is **visible only** when `hr_sa_debug_enabled` is true.

**Overview page**
- Plugin description, current version, links to other pages.

**Settings page**
- Implements Section 3 options + Save button.

**Modules page**
- Show flags and whether other SEO plugin is detected.
- In Phase 0, display status only (no toggles required).

**Debug page** (read-only surface)
- **Environment:** Post ID, Post Type, Template (if any), Current URL, Conflict Mode, Flags (jsonld/og/debug).
- **Context (`hr_sa_get_context`):** `url, type, title (placeholder ok), description (placeholder ok), country (placeholder ok), site_name, locale, twitter_handle, hero_url`.
- **Connectors:** whether `hr_mh_current_hero_url` resolves (Yes/No) + its value.
- **Settings snapshot:** all settings from Section 3.
- **Modules:** which JSON-LD emitters are active (list submodules).
- Optional **Copy-as-JSON** button to copy context + settings blob.

---

## 6) Context Provider (Phase 0)

Create `core/context.php` with:

- Function `hr_sa_get_context()` building an **assoc array** and returning it through `apply_filters('hr_sa_get_context', $ctx)`.

For Phase 0, populate at minimum:
- `url` (canonical guess via `get_permalink()` for singular; `home_url()` otherwise)
- `type` (`trip` for the custom post type if present; else `page` or `home`)
- `site_name` (setting or `get_bloginfo('name')`)
- `locale` (setting or `get_locale()`)
- `twitter_handle` (setting)
- `hero_url` (via `apply_filters('hr_mh_current_hero_url', null)` — may be `null`)
- `title`, `description`, `country` → **placeholder values** OK in Phase 0 (will be wired in Phase 1)

Do **not** emit OG/Twitter here — context is for JSON-LD & later OG.

---

## 7) JSON-LD Adoption (Phase 0 Deliverable)

Port logic from **legacy MUs** into `/modules/jsonld/`:

- `hr-schema-core.php` → base Organization/WebSite/WebPage as applicable → `org.php` (and/or similar)
- `hr-trip-schema-graph.php` → Trip/Product graph → `trip.php`
- `hr-wte-vehicle-offers.php` → Vehicle offers → `vehicles.php`
- If legacy includes FAQ/Itinerary within, split into `faq.php` / `itinerary.php` as needed.

Create `modules/jsonld/loader.php` that:
- Checks `hr_sa_jsonld_enabled` flag and **Respect/Force** logic (see Compat in Section 9).  
  - If *other SEO plugin active* and *Conflict Mode is Respect* → **skip emission**.
- Assembles **a single** JSON-LD `<script type="application/ld+json">` block per page using the adopted submodules.
- **Parity requirement:** Output must match the legacy MU output for the same pages (allowing whitespace/order differences). Where trivial, pull values from `hr_sa_get_context()` (e.g., site name, URL), but **do not change semantics** in Phase 0.

> Keep the legacy files under `legacy-mu/` **unchanged**. We’ll disable them in WP only after parity is confirmed.

---

## 8) Integrations

Add `integrations/media-help.php` to safely retrieve hero URL via `apply_filters('hr_mh_current_hero_url', null)`.  
- No fatal if the filter is not present.  
- Provide a tiny helper function if needed for reuse (`hr_sa_get_hero_url()`), but do not emit anything.

---

## 9) Compatibility (other SEO plugins)

Create `core/compat.php` with helpers to detect:
- Rank Math (`defined('RANK_MATH_VERSION')`)
- Yoast SEO (`defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend')`)
- SEOPress (`defined('SEOPRESS_VERSION')`)

Expose `hr_sa_other_seo_active(): bool`.  
JSON-LD loader must honor **Respect/Force** (from settings).  
OG module is present but **disabled** in Phase 0.

---

## 10) Assets

Add minimal `assets/admin.css` (even empty but present).  
`assets/admin.js` is optional; if created, keep tiny and only for admin UI niceties (e.g., copy-to-clipboard on Debug page).

---

## 11) Docs & Versioning

Ensure these files exist at repo root and are left intact:
- `README.md`
- `ROADMAP.md` (contains Naming Scheme with `hr_sa_` / `hr_mh_` prefixes)
- `TESTING.md` (QA checklist)
- `CHANGELOG.md` (init at `0.1.0`)
- `CONTRIBUTING.md`
- `AGENT.md`
- `ROBOTS.txt` (baseline robots)

Update plugin header version to `0.1.0` if not already.

---

## 12) QA / Acceptance Criteria (Phase 0)

1. Plugin activates with **HR SEO** menu showing **Overview**, **Settings**, **Modules**; **Debug** appears only when Debug toggle is ON.
2. Debug page shows:
   - Environment, flags (jsonld/og/debug), conflict mode
   - Context (with placeholders allowed for title/desc/country)
   - Connector state & settings snapshot
   - Modules list (JSON-LD enabled, OG disabled)
3. JSON-LD emitted by `/modules/jsonld/loader.php` **matches** legacy MU output on:
   - One **Trip** page (with FAQ + Itinerary if applicable)
   - One **Generic Page**
   - **Homepage**
4. OG/Twitter **not emitted** by this plugin (module disabled).
5. No fatals if `hr_mh_current_hero_url` filter is absent.
6. Respect/Force mode is stored and reflected on Debug page.

**QA Tip:** Keep legacy MUs active initially. Flip a setting/flag to try the new JSON-LD emitter, compare outputs, then disable the legacy MUs in WP once parity is confirmed.

---

## 13) Guardrails / Non-Goals (Phase 0)
- Do **not** attempt sitemaps, redirects, robots editor, or AI calls.
- Do **not** emit OG/Twitter tags.
- Do **not** parse CSS/HTML for hero; rely solely on `hr_mh_current_hero_url` (may be null).
- Do **not** fold ACF Autofiller or Trips Widget; they live in other plugins.
- If spec is unclear at any point, add `// TODO:` comments and proceed conservatively.

---

## 14) After Phase 0 (notes for later phases)
- **Phase 1:** Implement OG/Twitter emitter using `hr_mh_current_hero_url` + sitewide fallback image and title templates; align JSON-LD image where appropriate.
- **Phase 2:** Add on-demand AI helpers for Title/Description/Keywords with admin UI, provider key, and logs; never call AI on frontend render.
