# 🏁 Codex Prompt — HR SEO Assistant (Phase 1: OG/Twitter Integration)

We are now entering **Phase 1** of the HR SEO Assistant plugin.

---

## 🔑 Goal of Phase 1
- Add **Open Graph (OG)** and **Twitter Card** support.
- Titles, Descriptions, Images must come from our plugin’s context (via `hr_sa_get_context()`).
- Pull Hero image via HR Media Help connector (`hr_mh_current_hero_url` filter).  
- If hero not present, fallback to sitewide image from plugin settings.  
- Ensure correct filters/hooks are in place for future overrides.
- Keep JSON-LD unchanged (already implemented in Phase 0).

---

## 🏗️ Deliverables
1. **og: meta tags** in `<head>`
   - `og:title`
   - `og:description`
   - `og:type` (website | article | product | trip)
   - `og:url`
   - `og:image` (from hero → fallback)
   - `og:site_name`
   - `og:locale`

2. **twitter: meta tags**
   - `twitter:card` → `summary_large_image`
   - `twitter:title`
   - `twitter:description`
   - `twitter:image`
   - `twitter:site` → from plugin setting (Twitter handle, optional)

3. **Context Source**
   - Titles: use template system (trip/page).
   - Description: use context description (trip/product/page).
   - Image: hero via HR Media Help filter → fallback image if null.
   - Locale: setting (`en_US` default).
   - URL: canonical/current permalink.

4. **Settings UI (extend existing)**
   - Toggle: Enable/Disable OG/Twitter output (feature flag).
   - Fallback image picker (already present in Phase 0).
   - Twitter handle field (optional).

5. **Debug Page (extend existing)**
   - Show: OG Enabled? Twitter Enabled?
   - Display resolved OG/Twitter fields (title, description, image, url, site_name).

---

## 🔧 Technical Notes
- All hooks & filters **must use prefix `hr_sa_*`**.
- Example new filters:
  - `hr_sa_enable_og` (bool)
  - `hr_sa_enable_twitter` (bool)
  - `hr_sa_og_tags` (filter final OG array before emit)
  - `hr_sa_twitter_tags` (filter final Twitter array before emit)
- Output must be escaped properly (`esc_attr` / `esc_url`).
- Emit OG/Twitter tags **only if feature flag is enabled**.
- No duplicate emission: plugin is source of truth.

---

## 🛑 Non-Goals (Phase 1)
- Do not implement AI integration yet.
- Do not implement advanced tag customization (per-post overrides) yet.
- Do not attempt structured `og:video` / `og:audio` support.

---

## ✅ Completion Criteria
- OG/Twitter tags appear in `<head>` when enabled in settings.
- Hero → fallback image logic works.
- Debug page shows OG/Twitter data clearly.
- All tags validate with Facebook Debugger / Twitter Card Validator.
