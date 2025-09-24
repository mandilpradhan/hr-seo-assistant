# HR SEO Assistant — Phase 3 Testing Checklist
Version: 0.3.0

## Goal of Phase 3
- Modules page acts as the single source of truth for feature toggles.
- Conflict Mode governs Open Graph & Twitter Cards behavior and UI states.
- Social image/description overrides resolve in the correct priority order.
- AI Assist remains admin-only, honours instruction guidance, and records token usage.
- Admin bar badge, debug page, and emitters all consume the shared resolver contract.

---

## 0) Pre-checks
- ✅ Plugin “HR SEO Assistant” is **Activated**.
- ✅ Database upgrade has run (visit any admin page to trigger if unsure).
- ✅ Confirm **Version 0.3.0** is displayed in the plugin list or debug snapshot.
- ✅ You have access to `HR SEO → Modules`, `Settings`, `Debug`.
- ✅ At least one public post/page exists for front-end validation.

---

## 1) Modules page — baseline state
1. Navigate to **HR SEO → Modules**.
2. Verify default toggle states:
   - JSON-LD Emitters → **On**
   - Open Graph & Twitter Cards → **On** (unless locked by Conflict Mode)
   - AI Assist → **Off**
   - Debug Mode → **Off**
3. Click **Save Modules** without changing values — expect a success notice with no PHP warnings.
4. Toggle each module, save, and ensure state persists after reload.
5. Use **Reset to Defaults** and confirm all toggles return to their default positions and notice displays.

---

## 2) Conflict Mode interactions
1. Install/activate one of Rank Math, Yoast, or SEOPress (or simulate via filter `add_filter( 'hr_sa_other_seo_active', '__return_true' );`).
2. Set **Conflict Mode** (Settings → General) to **Respect** and save.
3. Return to **Modules** and confirm:
   - Open Graph & Twitter Cards toggle is **disabled**.
   - Status chip reads “Disabled by Conflict Mode”.
   - Tooltip copy matches spec (“Disabled by Conflict Mode (Respect)… Force…”).
4. Switch Conflict Mode to **Force** and reload Modules:
   - Toggle becomes interactive again.
   - Chip reflects Enabled/Disabled based on saved value.
5. Repeat with no SEO plugin active to ensure Respect mode leaves toggle enabled.

---

## 3) Settings page updates
1. Open **HR SEO → Settings**.
2. Locate the **AI Instruction / Style Guide** textarea.
3. Enter guidance text (e.g., “Adventure travel audience…”), save, and confirm success notice.
4. Reload the page — ensure textarea retains saved content.
5. Confirm existing settings (fallback image, locale, site name, Twitter handle, conflict mode, image preset) remain editable.

---

## 4) Per-page social overrides
1. Edit a supported post type (post, page, trip).
2. In the **HR SEO Assistant** meta box:
   - Confirm “Social Image Override” (URL) and “Social Description Override” (textarea) fields exist.
   - Helper text: “Leave blank to use site defaults.” is visible.
3. Enter values and save/update the post:
   - Valid HTTPS image URL should persist.
   - Description trims to the configured length (140–160 characters guidance).
4. Clear the fields and resave — meta keys should delete (check via `get_post_meta`).
5. Ensure nonce errors do not occur when saving posts (quick edit / autosave should not trigger updates).

---

## 5) Social resolver & admin bar badge
1. Visit the saved post on the front-end while logged in as an admin.
2. Inspect the admin bar — confirm a badge in the top bar displaying:
   - `OG: override` (green) when override URL present.
   - `OG: meta` (blue) when falling back to `_hrih_header_image_url`.
   - `OG: fallback` (orange) when using the global fallback image.
   - `OG: disabled` (gray) when module is off or Conflict Mode suppresses output.
3. When a URL exists, badge links to the resolved image in a new tab.
4. Validate `hr_sa_resolve_social_image_url()` returns the same source via the Debug page (see next section).

---

## 6) Debug page verification
1. Enable **Debug Mode** (via Modules or Settings) if not already on.
2. Navigate to **HR SEO → Debug**.
3. Confirm sections include:
   - Environment (post ID, type, URL).
   - Flags/module table showing JSON-LD, OG/Twitter, AI Assist, Debug Mode states.
   - Conflict Mode status and detected plugins.
   - Social resolver output (`url`, `source`).
   - Settings snapshot including AI instruction text.
   - AI usage table with last run timestamp/model/tokens (if available).
4. When toggles or overrides change, reload to ensure Debug reflects new values.

---

## 7) AI Assist workflow
1. Ensure **AI Assist module** is enabled.
2. On a post edit screen, confirm Generate buttons (Title, Description, Keywords) appear and are admin-only.
3. Trigger each button:
   - Requests should include the saved AI instruction (inspect via debug logs or instrumentation as available).
   - Title ≤ 65 chars, Description 140–160 chars, Keywords 3–8 comma separated.
   - `_hr_sa_title`, `_hr_sa_description`, `_hr_sa_keywords` meta fields store sanitized output.
4. Debug page should log last run timestamp, status, model, and token counts.
5. Disable the AI module and verify buttons disappear.

---

## 8) Front-end meta output
1. View source of a post/page while OG module is enabled:
   - `<meta property="og:title">`, `<meta property="og:description">`, and `<meta property="og:image">` exist.
   - Twitter tags mirror OG values when module is active.
2. Toggle OG module off (or set Conflict Mode Respect with other SEO active) and confirm HR SEO Assistant stops outputting social tags.
3. Validate description precedence:
   - Override description wins when provided.
   - Otherwise generated/AI/excerpt is used (check via Debug page context).

---

## 9) Regression & tooling checks
- ✅ Run PHP syntax linting:
  ```bash
  find . -type f -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
  ```
- ✅ Spot-check for PHP warnings/notices in debug.log when toggling modules, saving posts, and loading front-end.
- ✅ Confirm no mixed-content warnings when using overrides (HTTPS enforced).

---

✅ **Phase 3 is complete** when:
- Modules screen reflects conflict-aware toggles and reset behavior.
- Settings include AI instruction guidance that persists.
- Social overrides, resolver contract, debug page, and admin bar badge all agree on the same source.
- AI Assist respects admin-only scope and instruction text.
- Front-end emits OG/Twitter tags only when allowed, using the resolved image/description order.
- Linting passes and no PHP errors are observed.
