# HR SEO Assistant — Phase 1 Testing Checklist
Version: 0.3.0

---

## 0) Pre-checks
- ✅ Plugin active, Phase 0 already tested OK.
- ✅ Legacy MU schema files remain disabled/ignored.
- ✅ HR SEO → Settings has new **OG/Twitter toggles**.

---

## 1) Settings Sanity
1. Go to **HR SEO → Settings**.
2. Enable **Open Graph** and **Twitter Cards**.
3. Set fallback image via Media Picker.
4. Add Twitter handle `@himalayanrides`.
5. Save.

---

## 2) Debug Page
Navigate to **HR SEO → Debug**.
- Confirm OG/Twitter flags: **enabled**.
- Section displays: title, description, url, image, locale, site_name, twitter_handle.

---

## 3) Source Check
Open a **Trip page** (with hero). View Source.
- Confirm `<meta property="og:title">` matches template.
- Confirm `<meta property="og:description">` matches trip description.
- Confirm `<meta property="og:image">` matches hero (w=1200,fit=cover,gravity=auto).
- Confirm `<meta property="og:site_name">` = “Himalayan Rides”.
- Confirm `<meta property="og:locale">` = `en_US`.
- Confirm `<meta property="og:type">` is `product` (Trip).
- Confirm `<meta name="twitter:card">` = `summary_large_image`.
- Confirm `<meta name="twitter:title">` & `<meta name="twitter:description">` correct.
- Confirm `<meta name="twitter:image">` = hero image.
- Confirm `<meta name="twitter:site">` = `@himalayanrides`.

---

## 4) Fallback Behavior
Open a **generic page** (with no hero).
- Confirm `og:image` and `twitter:image` use **fallback image** from settings.

---

## 5) Validation
- Run trip page URL in [Facebook Debugger](https://developers.facebook.com/tools/debug/).
- Run same URL in [Twitter Card Validator](https://cards-dev.twitter.com/validator).
- Confirm tags resolve correctly.

---

## 6) Disable Check
- Disable OG/Twitter in settings.
- Reload trip page, confirm **no OG/Twitter tags** in `<head>`.

---

## ✅ Completion Criteria
- OG/Twitter tags emit correctly when enabled.
- Hero → fallback works as expected.
- Debug page shows correct OG/Twitter data.
- Validation tools confirm correctness.
- Tags disappear when disabled.
