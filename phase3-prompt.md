# 🚀 Codex Prompt — HR SEO Assistant (Phase 3: OpenAI SEO Features)

We’ve completed Phase 1 (OG/Twitter) and Phase 2 (image resolution from `_hrih_header_image_url`).  
Now implement **Phase 3: OpenAI-powered SEO assistance**.

---

## 🎯 Goal
Integrate **OpenAI API** to assist with generating SEO metadata (title, description, keywords, focus keywords) for posts, pages, and custom post types (Trips).

---

## 🔑 Requirements

### 1. Admin-Only Feature
- Features must only run in **admin context** (never on front-end).
- No API calls during normal page render.

### 2. Settings Page
- Extend HR SEO Assistant settings with:
  - OpenAI API key (secure storage, masked).
  - Model selection (`gpt-4o-mini`, `gpt-4o`, etc.).
  - Max tokens / temperature (optional).
- Add a toggle to enable/disable AI SEO generation.

### 3. Meta Box (Post Edit Screen)
- For posts, pages, and Trips:
  - Show a **“SEO Assistant” meta box**.
  - Buttons:
    - **Generate Title**
    - **Generate Description**
    - **Generate Keywords**
  - Fields auto-populate with AI results but remain editable by user.
- Save into standard WordPress meta:
  - `_hr_sa_title`
  - `_hr_sa_description`
  - `_hr_sa_keywords`

### 4. Filters & Context
- Provide AI with context from:
  - Post title, excerpt, content.
  - For Trips: duration, destination, price range from JSON-LD context.
- Use internal helper: `hr_sa_get_context()`.

### 5. Debug Page
- Add AI section showing:
  - API key presence (masked).
  - Model in use.
  - Last prompt + last response status.

---

## ⚙️ Technical Details
- Use `wp_remote_post()` for API calls.
- Validate/sanitize AI responses (strip HTML, ensure length limits).
- Respect plugin conflict mode (`respect` | `force`).
- Guarded with `if (!defined('ABSPATH')) exit;`.

---

## 📌 Acceptance Criteria
1. Settings page allows OpenAI config and test.
2. Post edit screens show SEO Assistant meta box with working buttons.
3. Generated fields save correctly and emit in `<meta>` tags when active.
4. Debug page confirms AI context, prompt, and response.
5. No front-end API calls occur.

---

## 🚫 Out of Scope
- No automated bulk generation yet.
- No scheduled jobs or background queues.
- No nonces/roles beyond normal WP meta box security.

---

**End of Phase 3 Prompt**
