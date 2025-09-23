<?php
/**
 * FAQ helpers for JSON-LD.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build FAQPage nodes for a Trip by matching FAQ CPT via shared country terms.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_faq_nodes(int $trip_id): array
{
    if ($trip_id <= 0 || get_post_type($trip_id) !== 'trip') {
        return [];
    }

    $cache_key = sprintf('hr_sa_faq_nodes_trip_%d', $trip_id);
    $cached    = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $terms = get_the_terms($trip_id, 'country');
    if (empty($terms) || is_wp_error($terms)) {
        set_transient($cache_key, [], 12 * HOUR_IN_SECONDS);
        return [];
    }

    $country_ids = array_map(static fn($term) => (int) $term->term_id, $terms);

    $query = new WP_Query([
        'post_type'           => 'faq',
        'post_status'         => 'publish',
        'posts_per_page'      => 50,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'fields'              => 'ids',
        'tax_query'           => [
            [
                'taxonomy'         => 'country',
                'field'            => 'term_id',
                'terms'            => $country_ids,
                'operator'         => 'IN',
                'include_children' => false,
            ],
        ],
    ]);

    if (empty($query->posts)) {
        set_transient($cache_key, [], 12 * HOUR_IN_SECONDS);
        return [];
    }

    $qas = [];
    foreach ($query->posts as $faq_id) {
        $title = get_the_title($faq_id);
        if (trim((string) $title) === '') {
            continue;
        }

        $raw = (string) get_post_field('post_content', $faq_id);
        $clean = hr_sa_jsonld_sanitize_answer_html($raw);
        if ($clean === '') {
            continue;
        }

        $qas[] = [
            '@type' => 'Question',
            'name'  => $title,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $clean,
            ],
        ];
    }

    if (!$qas) {
        set_transient($cache_key, [], 12 * HOUR_IN_SECONDS);
        return [];
    }

    $trip_url = set_url_scheme((string) get_permalink($trip_id), 'https');
    $node     = [
        '@type'      => 'FAQPage',
        '@id'        => trailingslashit($trip_url) . '#faqs',
        'mainEntity' => array_values($qas),
    ];

    $result = [$node];
    set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);

    return $result;
}
