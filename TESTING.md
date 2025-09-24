# HR SEO Assistant — Phase 1 Testing Checklist
Version: 0.2.0

## Goal of Phase 1
- Verify Open Graph and Twitter Card meta tags emit when enabled in settings.
- Confirm header image meta → fallback image logic works for social previews.
- Ensure Debug tooling surfaces OG/Twitter status and resolved fields.
- Preserve JSON-LD parity from Phase 0.

---

## 0) Pre-checks
- ✅ Plugin “HR SEO Assistant” is **Activated**.
- ✅ Legacy schema MUs remain available in `/wp-content/mu-plugins/` for reference only.
- ✅ Admin menu shows **HR SEO → Overview**, **Settings**, **Modules**, **Debug** (Debug visible when enabled).

---

## 1) Settings sanity
1. Go to **HR SEO → Settings**.
2. Ensure **Enable Open Graph tags** and **Enable Twitter Card tags** are checked (enabled by default for 0.2.0).
3. Populate core fields:
   - **Fallback Image (sitewide):** choose an HTTPS image URL via the media picker.
   - **Title templates:**
     - Trips: `{{trip_name}} | Motorcycle Tour in {{country}}`
     - Pages: `{{page_title}}`
   - **Locale:** `en_US`
   - **Site name:** `Himalayan Rides`
   - **Twitter handle:** `@himalayanrides`
   - **Image preset:** `w=1200,fit=cover,gravity=auto,format=auto,quality=75`
   - **Conflict mode:** **Respect**
   - **Debug mode:** **ON**
4. Save and confirm the success notice appears.

---

## 2) Debug page validation
Navigate to **HR SEO → Debug** (Debug toggle must be ON).

Confirm sections:
- **Environment:** Post ID / Type / Template, Current URL, Conflict Mode, Detected SEO plugins.
- **Flags:** JSON-LD, Open Graph, Twitter Cards, Debug (all should read **On** except JSON-LD if manually disabled).
- **Social Meta:** displays OG/Twitter enabled states, resolved title/description/url/site name/image, `social_image_source`, `social_image_url`, and JSON previews of tag arrays.
- **Context:** `url, type, title, description, country, site_name, locale, twitter_handle, hero_url, fallback_image`.
- **Connectors:** hero URL value (or “Not provided”).
- **Settings snapshot:** reflects saved settings.

Use the “Copy Context & Settings JSON” button to ensure payload includes the new flags.

---

## 3) Front-end OG/Twitter emission
1. Load a **Trip** detail page on the front-end and view source.
   - Confirm a single block of `<meta property="og:*">` tags emitted by HR SEO Assistant.
   - Verify `og:type` is `trip`, title matches the template, and image points to the header image meta (or fallback when meta is missing).
   - Confirm matching `twitter:*` tags exist with `twitter:card` = `summary_large_image`.
2. Load a **Generic Page** (e.g., About).
   - `og:type` should be `article`.
   - Title/description pulled from page content/excerpt per template.
3. Load the **Homepage**.
   - `og:type` should be `website`.
   - Description should fall back to the site tagline when no explicit excerpt exists.

---

## 4) Image fallback logic
1. On a Trip with the `_hrih_header_image_url` meta populated, ensure `og:image` and `twitter:image` use that URL (with the CDN preset applied).
2. On a Page without the `_hrih_header_image_url` meta, confirm `og:image` and `twitter:image` fall back to the sitewide image.
3. If both the meta value and fallback image are missing, confirm no social image tag is output and the debug page reports “No social image resolved”.

---

## 5) Respect/Force mode regression
- With **Respect** mode active and another SEO plugin enabled, OG/Twitter tags should skip output.
- Switch to **Force** mode and verify HR SEO Assistant resumes emitting tags regardless of other plugins.

---

## 6) JSON-LD parity spot-check
- Repeat Phase 0 parity checks to ensure JSON-LD output remains unchanged (Trip, Generic Page, Homepage).
- Diff legacy vs plugin JSON to confirm no regressions introduced by Phase 1.

---

## 7) Final checks
- ✅ OG/Twitter tags validated via Facebook Sharing Debugger & Twitter Card Validator.
- ✅ Debug page reflects accurate social meta data.
- ✅ No PHP notices or warnings during page loads.

Phase 1 passes when all steps above succeed.
