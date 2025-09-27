# HR SEO Assistant — Module Overview

This document describes the Phase A scaffolding introduced in version 0.3.0.

## Module Registry

* Modules are registered in `core/modules.php` with metadata including slug, label, render callback, and boot callback.
* Module enablement is persisted in the single option `hrsa_modules_enabled`.
* Boot callbacks only attach hooks when the module is enabled. Existing emitters and UI remain unchanged.
* The registry exposes:
  * `HRSA\Modules::all()` — retrieve metadata for the overview grid and menus.
  * `HRSA\Modules::is_enabled( $slug )` — resolve current state (filters apply to legacy options as well).
  * `HRSA\Modules::enable( $slug )` / `disable( $slug )` — persist state changes, invoked via AJAX.
  * `HRSA\Modules::maybe_boot( $slug )` — boot hooks when a module should run.

## Admin UI

* The HR SEO overview screen (`admin.php?page=hr-sa-overview`) renders a card per module with:
  * Module label and one-line description.
  * Status badge that reflects registry state and updates live when toggles change.
  * Accessible toggle (`input[type=checkbox]`) with nonce-protected AJAX calls (`hrsa_toggle_module`).
  * Settings shortcut linking to each module’s submenu page.
* Module submenus reuse existing settings UI sections:
  * JSON-LD: status summary and emitter list (read-only).
  * Open Graph & Twitter Cards: social metadata form, fallback image picker, and URL rewrite controls.
  * AI Assist: administrator-only settings for the meta-box helper.
  * Debug: unchanged; still respects the legacy debug flag.
* Legacy settings remain at `admin.php?page=hr-sa-settings` for reference and parity.

## Assets

* `assets/admin.css` now defines HR UI tokens (`--hrui-*`) and component classes (`.hrui-card`, `.hrui-badge`, etc.).
* `assets/admin.js` binds module toggles, sending POST requests to `admin-ajax.php` with nonce `hrsa_toggle_module`.
* Assets are only enqueued on HR SEO screens; AI localisation runs on both the legacy settings page and the AI module page.

## Security & A11y

* All toggles require `manage_options` and pass `check_ajax_referer( 'hrsa_toggle_module' )`.
* Status badges include text + screen-reader context to avoid colour-only indicators.
* Toggles are keyboard accessible and revert on failure.

## Release Policy

* Semantic versioning (MAJOR.MINOR.PATCH) remains in effect.
* Automation expects PR labels `#version: patch|minor|major`.
* Version metadata lives in:
  * `hr-seo-assistant.php`
  * `README.md`
  * `CHANGELOG.md`
  * `TESTING_PHASE1.md`
  * `docs/CHANGELOG.md`
