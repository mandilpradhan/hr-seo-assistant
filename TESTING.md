# ✅ HR SEO Assistant — Testing Checklist (Phase 1 + Image Meta Change)

This checklist verifies that `og:image` and `twitter:image` correctly resolve from post meta `_hrih_header_image_url` with a fallback to the sitewide image.

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

## 2. Page without `_hrih_header_image_url`
- Open a Page with no `_hrih_header_image_url` saved.
- View Source:
  - `og:image` and `twitter:image` use sitewide fallback.
  - Parameters match normalization.
- Debug Page:
  - `social_image_source = fallback`
  - `social_image_url` matches fallback.

---

## 3. Invalid or Empty Meta
- Set `_hrih_header_image_url` to empty or invalid value.
- View Source:
  - `og:image` falls back to sitewide fallback.
- Debug Page:
  - `social_image_source = fallback`

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
