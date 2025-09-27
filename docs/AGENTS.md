# HR SEO Assistant — Notes for Automation Agents

* Respect the existing module registry in `core/modules.php`. Register new modules via the metadata array and provide both `boot` and `render` callbacks.
* Persist new module flags inside the shared `hrsa_modules_enabled` option. Do not introduce new scattered options without filters.
* Reuse the HR UI tokens/classes defined in `assets/admin.css` when extending the overview grid.
* Always gate AJAX endpoints with `check_ajax_referer( 'hrsa_toggle_module' )` and `current_user_can( 'manage_options' )`.
* When adding UI, ensure badges include textual status and toggles remain keyboard accessible.
* Update version metadata (plugin header, constants, READMEs, changelog files, testing manifests) together in every release.
