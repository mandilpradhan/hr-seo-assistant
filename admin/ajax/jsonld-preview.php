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
 * Flatten the JSON-LD payload into label/value rows for display.
 *
 * @param mixed  $data   Data to flatten.
 * @param string $prefix Current property path.
 *
 * @return array<int, array{label: string, value: string}>
 */
function hr_sa_jsonld_preview_flatten($data, string $prefix = ''): array
{
    $rows = [];

    if (is_object($data)) {
        $data = (array) $data;
    }

    if (is_array($data)) {
        $is_assoc = array_keys($data) !== range(0, count($data) - 1);
        foreach ($data as $key => $value) {
            $segment = $is_assoc ? (string) $key : '[' . $key . ']';
            $label   = $prefix === '' ? $segment : $prefix . ' › ' . $segment;
            $rows    = array_merge($rows, hr_sa_jsonld_preview_flatten($value, $label));
        }
        return $rows;
    }

    if (is_bool($data)) {
        $value = $data ? __('true', HR_SA_TEXT_DOMAIN) : __('false', HR_SA_TEXT_DOMAIN);
    } elseif ($data === null) {
        $value = __('null', HR_SA_TEXT_DOMAIN);
    } else {
        $value = (string) $data;
    }

    $rows[] = [
        'label' => $prefix === '' ? __('Value', HR_SA_TEXT_DOMAIN) : $prefix,
        'value' => $value,
    ];

    return $rows;
}
