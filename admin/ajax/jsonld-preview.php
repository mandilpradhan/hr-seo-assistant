<?php
/**
 * AJAX handler for JSON-LD previews.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_hr_sa_jsonld_preview', 'hr_sa_handle_jsonld_preview');

/**
 * Handle the JSON-LD preview request.
 */
function hr_sa_handle_jsonld_preview(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(
            ['message' => __('You do not have permission to run this preview.', HR_SA_TEXT_DOMAIN)],
            403
        );
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'hr_sa_jsonld_preview')) {
        wp_send_json_error(
            ['message' => __('Your session has expired. Refresh the page and try again.', HR_SA_TEXT_DOMAIN)],
            400
        );
    }

    $target = isset($_POST['target']) ? absint((string) wp_unslash($_POST['target'])) : 0;

    $context = hr_sa_jsonld_preview_prepare_context($target);
    if (is_wp_error($context)) {
        wp_send_json_error(
            ['message' => $context->get_error_message()],
            400
        );
    }

    $restore = $context['restore'];

    $original_emitters = $GLOBALS['hr_sa_jsonld_last_active_emitters'] ?? [];
    $rows              = [];
    $json              = '';

    try {
        $graph = hr_sa_jsonld_collect_graph();
        $graph = hr_sa_jsonld_normalize_internal_urls($graph);
        $graph = hr_sa_jsonld_enforce_org_and_brand($graph);
        $graph = hr_sa_jsonld_dedupe_by_id($graph);

        $graph = apply_filters('hr_sa_jsonld_graph_nodes', $graph);
        $graph = hr_sa_jsonld_normalize_internal_urls($graph);
        $graph = hr_sa_jsonld_enforce_org_and_brand($graph);
        $graph = hr_sa_jsonld_dedupe_by_id($graph);

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values($graph),
        ];

        $rows = hr_sa_jsonld_preview_flatten($payload);
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }
    } finally {
        $GLOBALS['hr_sa_jsonld_last_active_emitters'] = $original_emitters;
        if (is_callable($restore)) {
            $restore();
        }
    }

    wp_send_json_success(
        [
            'rows' => $rows,
            'json' => $json,
        ]
    );
}

/**
 * Prepare a temporary WP_Query context for the requested preview target.
 *
 * @return array{
 *     query: WP_Query,
 *     restore: callable,
 * }|WP_Error
 */
function hr_sa_jsonld_preview_prepare_context(int $target)
{
    global $wp_query, $wp_the_query, $post;

    $original_wp_query     = $wp_query;
    $original_wp_the_query = $wp_the_query;
    $original_post         = $post ?? null;

    $query = null;

    if ($target === 0) {
        $show_on_front = get_option('show_on_front', 'posts');
        if ($show_on_front === 'page') {
            $front_id = (int) get_option('page_on_front');
            if ($front_id > 0) {
                $query = new WP_Query([
                    'page_id'        => $front_id,
                    'post_type'      => 'page',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                ]);
                $query->is_front_page = true;
                $query->is_home       = false;
            }
        }

        if (!$query instanceof WP_Query) {
            $query = new WP_Query([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => max(1, (int) get_option('posts_per_page', 5)),
            ]);
            $query->is_front_page = true;
            $query->is_home       = true;
        }
    } else {
        $post_object = get_post($target);
        if (!$post_object || $post_object->post_status !== 'publish') {
            return new WP_Error('hr_sa_jsonld_preview_missing', __('The selected item is not available.', HR_SA_TEXT_DOMAIN));
        }

        $query = new WP_Query([
            'p'              => $post_object->ID,
            'post_type'      => $post_object->post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ]);
        $query->is_singular = true;
        $query->is_page     = $post_object->post_type === 'page';
        $query->is_single   = $post_object->post_type === 'post';
    }

    if (!$query instanceof WP_Query) {
        return new WP_Error('hr_sa_jsonld_preview_failed', __('Unable to prepare the preview context.', HR_SA_TEXT_DOMAIN));
    }

    if ($query->have_posts()) {
        $query->the_post();
    }

    $wp_query     = $query;
    $wp_the_query = $query;
    $post         = $query->post ?? null;

    return [
        'query'   => $query,
        'restore' => static function () use ($original_wp_query, $original_wp_the_query, $original_post): void {
            global $wp_query, $wp_the_query, $post;
            $wp_query     = $original_wp_query;
            $wp_the_query = $original_wp_the_query;
            $post         = $original_post;
        },
    ];
}

/**
 * Flatten the JSON-LD payload into structured rows for display.
 *
 * @param mixed $data Data to flatten.
 *
 * @return array<int, array{type: string, property: string, value: string}>
 */
function hr_sa_jsonld_preview_flatten($data): array
{
    if (is_object($data)) {
        $data = (array) $data;
    }

    $rows = [];

    $decode_value = static function (string $value): string {
        return wp_specialchars_decode($value, ENT_QUOTES);
    };

    $humanize_segment = static function (string $segment): string {
        $segment = trim($segment);
        if ($segment === '') {
            return '';
        }

        if ($segment[0] === '@') {
            $segment = substr($segment, 1);
        }

        if ($segment === '') {
            return '';
        }

        if (preg_match('/^\[(\d+)\]$/', $segment, $matches)) {
            return '#' . ((int) $matches[1] + 1);
        }

        $segment = (string) preg_replace('/[\\/_\-]+/u', ' ', $segment);
        $segment = (string) preg_replace('/(?<!^)(?=[A-Z])/u', ' $0', $segment);
        $segment = (string) preg_replace('/\s+/u', ' ', $segment);
        $segment = trim($segment);

        if ($segment === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $segment) ?: [];
        $parts = array_map(
            static function ($part) {
                if ($part === '') {
                    return '';
                }

                $upper = mb_strtoupper($part, 'UTF-8');
                if ($upper === $part) {
                    return $upper;
                }

                $lower = mb_strtolower($part, 'UTF-8');
                if ($lower === $part && mb_strlen($part, 'UTF-8') <= 3 && preg_match('/^[a-z]+$/u', $part)) {
                    return mb_strtoupper($part, 'UTF-8');
                }

                if ($lower === $part && strpos($part, '.') !== false) {
                    $first = mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8');
                    $rest  = mb_substr($lower, 1, null, 'UTF-8');

                    return $first . $rest;
                }

                return mb_convert_case($part, MB_CASE_TITLE, 'UTF-8');
            },
            $parts
        );

        return implode(' ', array_filter($parts, static fn ($part) => $part !== ''));
    };

    $format_property = static function (array $segments) use ($humanize_segment): string {
        $clean = [];
        $skip_index = false;

        foreach ($segments as $segment) {
            if ($segment === '@graph') {
                $skip_index = true;
                continue;
            }

            if ($skip_index && preg_match('/^\[(\d+)\]$/', (string) $segment)) {
                $skip_index = false;
                continue;
            }

            $skip_index = false;

            $formatted = $humanize_segment((string) $segment);
            if ($formatted !== '') {
                $clean[] = $formatted;
            }
        }

        if (empty($clean)) {
            return __('Value', HR_SA_TEXT_DOMAIN);
        }

        return implode(' ', $clean);
    };

    $normalize_type = static function ($type_value, string $fallback) use ($decode_value, $humanize_segment): string {
        $values = is_array($type_value) ? $type_value : [$type_value];
        $labels = [];

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            $value = $decode_value($value);
            $candidate = $value;
            $parsed_host = '';

            if (strpos($value, '://') !== false) {
                $parsed = wp_parse_url($value);
                if (is_array($parsed)) {
                    $parsed_host = isset($parsed['host']) ? (string) $parsed['host'] : '';
                    $path = isset($parsed['path']) ? trim((string) $parsed['path'], '/') : '';

                    if ($path !== '') {
                        $candidate = $path;
                    } elseif ($parsed_host !== '') {
                        $candidate = $parsed_host;
                    } elseif ($fallback !== '') {
                        $candidate = $fallback;
                    } else {
                        $candidate = '';
                    }
                }
            }

            if ($candidate === '' && $fallback !== '') {
                $candidate = $fallback;
            }

            if ($candidate === '') {
                continue;
            }

            $label = $humanize_segment($candidate);
            if ($label === '' && $parsed_host !== '') {
                $label = $humanize_segment($parsed_host);
                if ($label === '') {
                    $label = $parsed_host;
                }
            }

            if ($label === '' && $fallback !== '') {
                $fallback_label = $humanize_segment($fallback);
                $label = $fallback_label !== '' ? $fallback_label : $fallback;
            }

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        if (empty($labels) && $fallback !== '') {
            $fallback_label = $humanize_segment($fallback);

            return $fallback_label !== '' ? $fallback_label : $fallback;
        }

        if (empty($labels)) {
            return '';
        }

        $labels = array_values(array_unique($labels));

        return implode(', ', $labels);
    };

    $context_type = '';
    if (is_array($data) && array_key_exists('@context', $data)) {
        $context_type = $normalize_type($data['@context'], '');
    }

    $walker = static function ($item, array $path, string $current_type) use (
        &$walker,
        &$rows,
        $format_property,
        $normalize_type,
        $decode_value,
        $context_type
    ): void {
        if (is_object($item)) {
            $item = (array) $item;
        }

        if (is_array($item)) {
            $is_assoc = array_keys($item) !== range(0, count($item) - 1);

            if ($is_assoc) {
                $node_type = $current_type;
                if (array_key_exists('@type', $item)) {
                    $node_type = $normalize_type($item['@type'], $context_type);
                } elseif ($node_type === '') {
                    $node_type = $context_type;
                }

                foreach ($item as $key => $value) {
                    $walker($value, array_merge($path, [(string) $key]), $node_type);
                }

                return;
            }

            $next_type = $current_type !== '' ? $current_type : $context_type;

            foreach ($item as $index => $value) {
                $walker($value, array_merge($path, ['[' . $index . ']']), $next_type);
            }

            return;
        }

        $type_label = $current_type !== '' ? $current_type : $context_type;

        if (is_bool($item)) {
            $value = $item ? __('true', HR_SA_TEXT_DOMAIN) : __('false', HR_SA_TEXT_DOMAIN);
        } elseif ($item === null) {
            $value = __('null', HR_SA_TEXT_DOMAIN);
        } elseif (is_float($item)) {
            $value = (string) $item;
        } elseif (is_int($item)) {
            $value = (string) $item;
        } elseif (is_string($item)) {
            $value = $decode_value($item);
        } else {
            $value = $decode_value((string) $item);
        }

        $rows[] = [
            'type'     => $type_label,
            'property' => $format_property($path),
            'value'    => $value,
        ];
    };

    $walker($data, [], $context_type);

    return $rows;
}
