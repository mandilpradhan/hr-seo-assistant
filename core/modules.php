<?php
/**
 * Module registry and toggles.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

namespace HRSA;

use function __;
use function add_action;
use function add_filter;
use function call_user_func;
use function get_option;
use function hr_sa_get_ai_settings;
use function hr_sa_is_debug_enabled;
use function hr_sa_is_flag_enabled;
use function hr_sa_jsonld_boot;
use function hr_sa_bootstrap_og_module;
use function hr_sa_ai_boot_module;
use function is_array;
use function is_callable;
use function filter_var;
use function update_option;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Module registry for HR SEO Assistant.
 */
final class Modules
{
    private const OPTION = 'hrsa_modules_enabled';

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $registry = [];

    /**
     * @var array<string, bool>
     */
    private static array $booted = [];

    private static bool $filters_registered = false;

    /**
     * Initialize module handling hooks.
     */
    public static function init(): void
    {
        add_action('plugins_loaded', [self::class, 'bootstrap']);
        add_action('init', [self::class, 'register_filters']);
    }

    /**
     * Ensure defaults are installed during activation.
     */
    public static function install_defaults(): void
    {
        if (get_option(self::OPTION, null) !== null) {
            return;
        }

        update_option(self::OPTION, self::get_default_states(), false);
    }

    /**
     * Return module metadata.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function registry(): array
    {
        if (self::$registry) {
            return self::$registry;
        }

        self::$registry = [
            'json-ld' => [
                'slug'         => 'json-ld',
                'label'        => __('JSON-LD Emitters', 'hr-seo-assistant'),
                'description'  => __('Structured data emitters (unchanged).', 'hr-seo-assistant'),
                'version'      => '1.0.0',
                'has_settings' => true,
                'capability'   => 'manage_options',
                'boot'         => 'hr_sa_jsonld_boot',
                'render'       => 'hr_sa_render_module_jsonld_page',
                'requires'     => [],
            ],
            'open-graph' => [
                'slug'         => 'open-graph',
                'label'        => __('Open Graph & Twitter Cards', 'hr-seo-assistant'),
                'description'  => __('Social meta output (unchanged).', 'hr-seo-assistant'),
                'version'      => '1.0.0',
                'has_settings' => true,
                'capability'   => 'manage_options',
                'boot'         => 'hr_sa_bootstrap_og_module',
                'render'       => 'hr_sa_render_module_open_graph_page',
                'requires'     => [],
            ],
            'ai-assist' => [
                'slug'         => 'ai-assist',
                'label'        => __('AI Assist', 'hr-seo-assistant'),
                'description'  => __('Assistant UI and meta box helpers.', 'hr-seo-assistant'),
                'version'      => '1.0.0',
                'has_settings' => true,
                'capability'   => 'manage_options',
                'boot'         => 'hr_sa_ai_boot_module',
                'render'       => 'hr_sa_render_module_ai_page',
                'requires'     => [],
            ],
            'debug' => [
                'slug'         => 'debug',
                'label'        => __('Debug', 'hr-seo-assistant'),
                'description'  => __('Diagnostics and context inspection.', 'hr-seo-assistant'),
                'version'      => '1.0.0',
                'has_settings' => false,
                'capability'   => 'manage_options',
                'boot'         => null,
                'render'       => 'hr_sa_render_debug_page',
                'requires'     => [],
            ],
        ];

        return self::$registry;
    }

    /**
     * Retrieve all modules.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return array_values(self::registry());
    }

    /**
     * Retrieve a module definition.
     */
    public static function get(string $slug): ?array
    {
        $registry = self::registry();

        return $registry[$slug] ?? null;
    }

    /**
     * Bootstrap enabled modules.
     */
    public static function bootstrap(): void
    {
        self::synchronize_states();

        foreach (self::registry() as $slug => $module) {
            self::maybe_boot($slug);
        }
    }

    /**
     * Register filters to gate functionality by module state.
     */
    public static function register_filters(): void
    {
        if (self::$filters_registered) {
            return;
        }

        add_filter('hr_sa_jsonld_enabled', [self::class, 'filter_jsonld']);
        add_filter('hr_sa_og_enabled', [self::class, 'filter_open_graph']);
        add_filter('hr_sa_twitter_enabled', [self::class, 'filter_open_graph']);
        add_filter('hr_sa_debug_enabled', [self::class, 'filter_debug']);
        add_filter('option_hr_sa_ai_enabled', [self::class, 'filter_ai_option']);
        add_filter('option_hr_sa_debug_enabled', [self::class, 'filter_debug_option']);

        self::$filters_registered = true;
    }

    /**
     * Whether a module is enabled.
     */
    public static function is_enabled(string $slug): bool
    {
        $states = self::get_states();
        if (array_key_exists($slug, $states)) {
            return (bool) $states[$slug];
        }

        $defaults = self::get_default_states();

        return (bool) ($defaults[$slug] ?? false);
    }

    /**
     * Enable a module.
     */
    public static function enable(string $slug): bool
    {
        if (!self::get($slug)) {
            return false;
        }

        $states = self::get_states();
        $states[$slug] = true;

        $updated = update_option(self::OPTION, $states, false);

        if ($updated) {
            self::maybe_boot($slug, true);
        }

        return $updated;
    }

    /**
     * Disable a module.
     */
    public static function disable(string $slug): bool
    {
        if (!self::get($slug)) {
            return false;
        }

        $states = self::get_states();
        $states[$slug] = false;

        return update_option(self::OPTION, $states, false);
    }

    /**
     * Boot a module if enabled.
     */
    public static function maybe_boot(string $slug, bool $force = false): void
    {
        if (!$force && !self::is_enabled($slug)) {
            return;
        }

        if (isset(self::$booted[$slug])) {
            return;
        }

        $module = self::get($slug);
        if (!$module) {
            return;
        }

        $boot = $module['boot'] ?? null;
        if (is_callable($boot)) {
            try {
                call_user_func($boot);
            } catch (\Throwable $throwable) {
                // Fail softly without exposing the throwable.
            }
        }

        self::$booted[$slug] = true;
    }

    /**
     * Get the stored module state map.
     *
     * @return array<string, bool>
     */
    private static function get_states(): array
    {
        $raw = get_option(self::OPTION, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $states = [];
        foreach ($raw as $slug => $value) {
            $states[(string) $slug] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $states;
    }

    /**
     * Synchronize stored states with defaults when new modules appear.
     */
    private static function synchronize_states(): void
    {
        $states   = self::get_states();
        $defaults = self::get_default_states();
        $changed  = false;

        foreach ($defaults as $slug => $default) {
            if (!array_key_exists($slug, $states)) {
                $states[$slug] = (bool) $default;
                $changed       = true;
            }
        }

        if ($changed) {
            update_option(self::OPTION, $states, false);
        }
    }

    /**
     * Resolve default module states based on legacy options.
     *
     * @return array<string, bool>
     */
    private static function get_default_states(): array
    {
        return [
            'json-ld'   => hr_sa_is_flag_enabled('hr_sa_jsonld_enabled', true),
            'open-graph'=> hr_sa_is_flag_enabled('hr_sa_og_enabled', false) || hr_sa_is_flag_enabled('hr_sa_twitter_enabled', false),
            'ai-assist' => (bool) hr_sa_get_ai_settings()['hr_sa_ai_enabled'],
            'debug'     => hr_sa_is_debug_enabled(),
        ];
    }

    /**
     * Filter JSON-LD enablement.
     */
    public static function filter_jsonld(bool $enabled): bool
    {
        return $enabled && self::is_enabled('json-ld');
    }

    /**
     * Filter Open Graph/Twitter enablement.
     */
    public static function filter_open_graph(bool $enabled): bool
    {
        return $enabled && self::is_enabled('open-graph');
    }

    /**
     * Filter debug flag enablement.
     */
    public static function filter_debug(bool $enabled): bool
    {
        return $enabled && self::is_enabled('debug');
    }

    /**
     * Filter the stored debug option for UI consistency.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function filter_debug_option($value)
    {
        if (!self::is_enabled('debug')) {
            return '0';
        }

        return $value;
    }

    /**
     * Filter the stored AI option to respect module state.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function filter_ai_option($value)
    {
        if (!self::is_enabled('ai-assist')) {
            return '0';
        }

        return $value;
    }
}
