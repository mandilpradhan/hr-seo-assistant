# ✅ HR SEO Assistant — Testing Checklist (Phase 1 + Image Meta Change)

## Goal of Phase 1
- Verify Open Graph and Twitter Card meta tags emit when enabled in settings.
- Confirm header image meta → fallback image logic works for social previews.
- Ensure Debug tooling surfaces OG/Twitter status and resolved fields.
- Preserve JSON-LD parity from Phase 0.

---

## 🔍 Setup
- Confirm plugin HR SEO Assistant is active.
- Ensure sitewide fallback image is configured in plugin settings.
- Enable WP_DEBUG in wp-config.php to surface log entries if needed.

---

## 1. Trip with `_hrih_header_image_url` set
- Edit a Trip post.
- Add a valid Cloudflare Images URL to `_hrih_header_image_url`.
- View Source:
  - `og:image` and `twitter:image` use this meta URL.
  - Confirm parameters: `w=1200,fit=cover,gravity=auto,format=auto,quality=75`.
- Debug Page:
  - `social_image_source = meta`
  - `social_image_url` matches normalized URL.

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

## 4. Validator Tests
- Paste a Trip URL into:
  - [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/)
  - [Twitter Card Validator](https://cards-dev.twitter.com/validator)
- Confirm images and descriptions render correctly.

---

## 5. Regression Checks
- Ensure other OG/Twitter fields (`og:title`, `og:description`, `og:url`, `og:type`, etc.) still render.
- Ensure JSON-LD output unchanged.
- Ensure no duplication of tags.

---

**✅ Pass Criteria**: All pages emit one correct image URL (`meta` or `fallback`), debug page shows correct source, validators confirm rendering.
