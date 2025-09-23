<?php
/**
 * Itinerary JSON-LD helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build an ItemList node representing the itinerary.
 *
 * @param int    $trip_id
 * @param string $product_id
 * @param array<int> $country_term_ids
 *
 * @return array{0: array<string, mixed>|null, 1: array<string, string>|null}
 */
function hr_sa_trip_itinerary_node(int $trip_id, string $product_id, array $country_term_ids): array
{
    $posts = get_posts([
        'post_type'      => 'itinerary',
        'posts_per_page' => -1,
        'tax_query'      => [
            [
                'taxonomy' => 'country',
                'field'    => 'term_id',
                'terms'    => $country_term_ids,
            ],
        ],
        'post_status'    => 'publish',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    if (!$posts) {
        return [null, null];
    }

    $elements = [];
    $position = 1;

    foreach ($posts as $itinerary) {
        $title = hr_sa_trip_clean_text(get_the_title($itinerary->ID));
        if ($title === '') {
            continue;
        }

        $summary = '';
        $labels  = [];

        $steps = function_exists('get_field') ? get_field('itinerary_steps', $itinerary->ID) : get_post_meta($itinerary->ID, 'itinerary_steps', true);
        if (is_array($steps) && $steps) {
            foreach ($steps as $row) {
                $name = '';
                if (is_array($row)) {
                    $name = $row['title'] ?? ($row['name'] ?? '');
                } else {
                    $name = (string) $row;
                }

                $name = hr_sa_trip_clean_text($name);
                if ($name !== '') {
                    $labels[] = $name;
                }

                if (count($labels) >= 8) {
                    break;
                }
            }
            $summary = implode('; ', $labels);
        } else {
            $content = apply_filters('the_content', $itinerary->post_content);
            if ($content && preg_match_all('/<(h2|h3)[^>]*>(.*?)<\/\\1>/i', $content, $matches)) {
                foreach ($matches[2] as $heading) {
                    $heading = hr_sa_trip_clean_text($heading);
                    if ($heading) {
                        $labels[] = $heading;
                    }
                    if (count($labels) >= 8) {
                        break;
                    }
                }
                $summary = implode('; ', $labels);
            } else {
                $summary = hr_sa_trip_clean_text($itinerary->post_content, 50);
            }
        }

        $item = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $title,
        ];

        if ($summary !== '') {
            $item['description'] = $summary;
        }

        $elements[] = $item;
    }

    if (!$elements) {
        return [null, null];
    }

    $trip_url     = get_permalink($trip_id);
    $itinerary_id = $trip_url ? ($trip_url . '#itinerary') : ($product_id . '-itinerary');
    $node         = [
        '@type'           => 'ItemList',
        '@id'             => $itinerary_id,
        'name'            => 'Itinerary',
        'itemListElement' => $elements,
    ];

    return [$node, ['@id' => $itinerary_id]];
}
