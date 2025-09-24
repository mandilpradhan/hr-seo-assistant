# HR SEO Assistant

**Version:** 0.2.0
**Author:** Himalayan Rides  
**Description:** HR SEO Assistant unifies Open Graph, Twitter Cards, and JSON-LD schema under one modular framework. Built for portability and clarity, it integrates with the HR Media Help hero system and provides a debug page for validation. Future phases will add AI-assisted SEO enhancements.

---

## Features
- Shared context provider (`hrsa_get_context()`)
- Open Graph + Twitter card generator
- JSON-LD (@graph) module integration
- Sitewide fallback image with CDN presets
- Title templates for Trips & Pages
- Settings page with Media Picker, toggles, and preview
- Debug mode with dedicated debug page
- Respect/Force mode for SEO plugin conflicts

---

## Versioning

We follow **semantic versioning**:

- **0.x.x** → Pre-release / scaffold phase (unstable)  
- **1.0.0** → First production-ready release (OG + JSON-LD parity)  
- **1.x.x** → Stable series (incremental features & bugfixes)  
- **2.0.0** → Major upgrade (AI-assist, batch generation, breaking changes)

---

## Installation
1. Upload to `/wp-content/plugins/hr-seo-assistant/`
2. Activate via WP Admin → Plugins
3. Configure settings in **HR SEO → Settings**

---

## Development Notes
- Hero image integration is provided via `apply_filters('hr_mh_current_hero_url', null)`.
- Always provide a fallback image in settings.
- Debug mode can be enabled in settings to access the debug surface.
- All metadata is emitted early in `<head>` with no duplicates.

---

## Roadmap
See [ROADMAP.md](ROADMAP.md).
