<?php
/**
 * Trip/Product graph emitters.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);



if (!defined('ABSPATH')) {
    exit;
}

hr_sa_jsonld_register_emitter('trip', 'hr_sa_trip_emit_nodes');

/**
 * Emit nodes for trip views.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_emit_nodes(): array
{
    if (!is_singular('trip')) {
        return [];
    }

    $trip_id = get_queried_object_id();
    if (!$trip_id) {
        return [];
    }

    static $cache = [];
    if (!isset($cache[$trip_id])) {
        $cache[$trip_id] = hr_sa_trip_build_product_nodes((int) $trip_id);
    }

    return $cache[$trip_id];
}

/**
 * Trim and normalize text pulled from post content.
 */
function hr_sa_trip_clean_text($html, int $words = 0): string
{
    $text = wp_strip_all_tags((string) $html, true);
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    if ($words > 0) {
        $parts = preg_split('/\s+/u', $text);
        if ($parts && count($parts) > $words) {
            $text = implode(' ', array_slice($parts, 0, $words)) . '…';
        }
    }

    return $text;
}

/**
 * Collect unique image URLs for a trip.
 *
 * @return array<int, string>
 */
function hr_sa_trip_images(int $post_id, int $max = 8): array
{
    $urls = [];

    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) {
        $thumb_url = wp_get_attachment_image_url($thumb_id, 'full');
        if ($thumb_url) {
            $urls[] = $thumb_url;
        }
    }

    $attachments = get_attached_media('image', $post_id);
    foreach ($attachments as $attachment) {
        $url = wp_get_attachment_image_url($attachment->ID, 'full');
        if ($url) {
            $urls[] = $url;
        }
    }

    $urls = array_values(array_unique($urls));
    if ($max > 0) {
        $urls = array_slice($urls, 0, $max);
    }

    return $urls;
}

/**
 * Build Product.additionalProperty entries from ACF/meta fields.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_additional_properties(int $post_id): array
{
    $properties = [];
    $map = [
        'Duration'   => 'duration',
        'Distance'   => 'distance',
        'Seasons'    => 'seasons',
        'Group Size' => 'group_size',
    ];

    $getter = static function (string $key) use ($post_id) {
        if (function_exists('get_field')) {
            return get_field($key, $post_id);
        }

        return get_post_meta($post_id, $key, true);
    };

    foreach ($map as $label => $key) {
        $value = $getter($key);
        if ($value === null || $value === '' || $value === []) {
            continue;
        }

        if (is_array($value)) {
            $value = array_values(array_filter(array_map('wp_strip_all_tags', $value)));
            $value = implode(', ', $value);
        } else {
            $value = hr_sa_trip_clean_text($value);
        }

        if ($value !== '') {
            $properties[] = [
                '@type' => 'PropertyValue',
                'name'  => $label,
                'value' => $value,
            ];
        }
    }

    return $properties;
}

/**
 * Build additional PropertyValue/WebPage nodes.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, string>>}
 */
function hr_sa_trip_additional_nodes(int $trip_id, string $product_id, array $country_term_ids): array
{
    $posts = get_posts([
        'post_type'      => 'additional',
        'posts_per_page' => 10,
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
        return [[], [], []];
    }

    $property_values = [];
    $webpages        = [];
    $has_parts       = [];

    foreach ($posts as $post) {
        $url         = get_permalink($post->ID);
        $name        = get_the_title($post->ID) ?: 'Additional Information';
        $description = hr_sa_trip_clean_text(strip_shortcodes($post->post_content), 40);

        $property = [
            '@type' => 'PropertyValue',
            'name'  => $name,
            'value' => $description ?: ($url ?: ''),
        ];

        if ($url) {
            $property['valueReference'] = [
                '@type' => 'WebPage',
                'url'   => $url,
            ];
        }

        $property_values[] = $property;
    }

    $trip_url      = get_permalink($trip_id);
    $additional_id = $trip_url ? ($trip_url . '#additional-details') : ($product_id . '-details');

    $webpages[] = [
        '@type' => 'WebPage',
        '@id'   => $additional_id,
        'name'  => 'Additional Details',
    ];

    $has_parts[] = ['@id' => $additional_id];

    return [$property_values, $webpages, $has_parts];
}

/**
 * Build Offer nodes using WTE/ACF data helpers.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_build_offers(int $trip_id, string $trip_url): array
{
    if (!function_exists('_hr_acf_fetch_trip_data')) {
        return [];
    }

    $offers = [];
    $data   = _hr_acf_fetch_trip_data($trip_id);
    if (!$data || empty($data['dates'])) {
        return $offers;
    }

    $rider_price   = isset($data['rider']) ? (float) $data['rider'] : null;
    $threshold     = defined('HR_THRESHOLD_MIN_SPOTS') ? (int) HR_THRESHOLD_MIN_SPOTS : 4;
    $duration_days = null;

    if (function_exists('hr_trip_duration_days')) {
        $duration_days = (int) hr_trip_duration_days($trip_id);
        if ($duration_days <= 0) {
            $duration_days = null;
        }
    }

    $today = current_time('Y-m-d');

    foreach ($data['dates'] as $ymd) {
        if ($ymd < $today) {
            continue;
        }

        $remaining = isset($data['remaining'][$ymd]) ? (int) $data['remaining'][$ymd] : null;
        $seats     = isset($data['seats'][$ymd]) ? (int) $data['seats'][$ymd] : null;

        if ($rider_price === null) {
            continue;
        }

        if ($remaining !== null && $remaining < 0) {
            $remaining = 0;
        }

        $availability = 'https://schema.org/InStock';
        if ($remaining === 0) {
            $availability = 'https://schema.org/SoldOut';
        } elseif ($remaining !== null && $remaining <= $threshold) {
            $availability = 'https://schema.org/LimitedAvailability';
        }

        $offer = [
            '@type'              => 'Offer',
            'price'              => (string) $rider_price,
            'priceCurrency'      => 'USD',
            'availability'       => $availability,
            'availabilityStarts' => $ymd,
            'url'                => trailingslashit($trip_url) . '#intro',
            'inventoryLevel'     => $remaining !== null ? ['@type' => 'QuantitativeValue', 'value' => $remaining] : null,
        ];

        if ($seats !== null && $seats > 0) {
            $offer['eligibleQuantity'] = [
                '@type' => 'QuantitativeValue',
                'value' => $seats,
            ];
        }

        if ($duration_days !== null) {
            $date = DateTime::createFromFormat('Y-m-d', $ymd);
            if ($date instanceof DateTime) {
                $date->modify('+' . max(0, $duration_days - 1) . ' day');
                $offer['availabilityEnds'] = $date->format('Y-m-d');
            }
        }

        $offers[] = array_filter($offer, static fn($value) => $value !== null && $value !== '');
    }

    return $offers;
}

/**
 * Build Product, Vehicle, Itinerary, Review, and Additional nodes for a trip.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_build_product_nodes(int $trip_id): array
{
    if ($trip_id <= 0 || get_post_type($trip_id) !== 'trip') {
        return [];
    }

    $trip_url = get_permalink($trip_id);
    if (!$trip_url) {
        return [];
    }
    $trip_url   = trailingslashit($trip_url);
    $product_id = $trip_url . '#trip';

    $brand_id = function_exists('hr_sa_jsonld_org_id')
        ? hr_sa_jsonld_org_id()
        : trailingslashit(set_url_scheme(home_url('/'), 'https')) . '#org';

    $product = [
        '@type' => 'Product',
        '@id'   => $product_id,
        'name'  => get_the_title($trip_id),
        'image' => hr_sa_trip_images($trip_id),
        'brand' => [
            '@type' => 'Brand',
            '@id'   => $brand_id,
        ],
        'url'   => $trip_url,
    ];

    if (empty($product['image'])) {
        unset($product['image']);
    }

    $description = '';
    if (function_exists('get_field')) {
        $description = (string) get_field('description', $trip_id);
    }
    if ($description === '') {
        $description = (string) get_post_field('post_excerpt', $trip_id);
    }
    if ($description === '') {
        $description = (string) get_post_field('post_content', $trip_id);
    }
    $description = hr_sa_trip_clean_text($description, 60);
    if ($description !== '') {
        $product['description'] = $description;
    }

    $properties = hr_sa_trip_additional_properties($trip_id);
    if ($properties) {
        $product['additionalProperty'] = $properties;
    }

    $offers = hr_sa_trip_build_offers($trip_id, $trip_url);
    if ($offers) {
        $prices = array_filter(array_map(static fn($offer) => isset($offer['price']) ? (float) $offer['price'] : null, $offers), static fn($value) => $value !== null);
        if ($prices) {
            $product['offers'] = [
                '@type'         => 'AggregateOffer',
                'offerCount'    => count($offers),
                'priceCurrency' => 'USD',
                'lowPrice'      => (string) min($prices),
                'highPrice'     => (string) max($prices),
                'offers'        => $offers,
            ];
        }
    }

    $graph         = [$product];
    $product_index = 0;

    $country_terms = wp_get_post_terms($trip_id, 'country', ['fields' => 'ids']);
    if (!is_wp_error($country_terms) && $country_terms) {
        [$bike_nodes, $bike_names, $bike_about] = hr_sa_trip_bike_nodes($trip_id, $country_terms);

        if ($bike_nodes) {
            $offer_map = hr_sa_wte_build_vehicle_offer_map($trip_id, $trip_url);
            if ($offer_map) {
                foreach ($bike_nodes as &$bike_node) {
                    if (empty($bike_node['name'])) {
                        continue;
                    }
                    $key = hr_sa_norm_bike_name($bike_node['name']);
                    if (isset($offer_map[$key])) {
                        $bike_node['offers'] = $offer_map[$key];
                    }
                }
                unset($bike_node);
            }
        }

        foreach ($bike_nodes as $node) {
            $graph[] = $node;
        }

        if ($bike_about) {
            $graph[$product_index]['about'] = array_merge($graph[$product_index]['about'] ?? [], $bike_about);
        }

        if ($bike_names) {
            $graph[$product_index]['additionalProperty'] = array_merge(
                $graph[$product_index]['additionalProperty'] ?? [],
                [
                    [
                        '@type' => 'PropertyValue',
                        'name'  => 'Bikes Available',
                        'value' => implode('; ', $bike_names),
                    ],
                ]
            );
        }

        [$itinerary_node, $itinerary_about] = hr_sa_trip_itinerary_node($trip_id, $product_id, $country_terms);
        if ($itinerary_node) {
            $graph[] = $itinerary_node;
        }
        if ($itinerary_about) {
            $graph[$product_index]['hasPart'] = array_merge($graph[$product_index]['hasPart'] ?? [], [$itinerary_about]);
        }

        [$review_nodes, $aggregate_rating] = hr_sa_trip_testimonials_nodes($trip_id, $product_id, $country_terms);
        foreach ($review_nodes as $review) {
            $graph[] = $review;
        }
        if ($aggregate_rating) {
            $graph[$product_index]['aggregateRating'] = $aggregate_rating;
        }

        [$property_values, $detail_nodes, $has_parts] = hr_sa_trip_additional_nodes($trip_id, $product_id, $country_terms);
        foreach ($detail_nodes as $node) {
            $graph[] = $node;
        }
        if ($property_values) {
            $graph[$product_index]['additionalProperty'] = array_merge($graph[$product_index]['additionalProperty'] ?? [], $property_values);
        }
        if ($has_parts) {
            $graph[$product_index]['hasPart'] = array_merge($graph[$product_index]['hasPart'] ?? [], $has_parts);
        }

        $faq_nodes = hr_sa_trip_faq_nodes($trip_id);
        foreach ($faq_nodes as $node) {
            $graph[] = $node;
        }
    }

    return $graph;
}

/**
 * Build Review nodes (and optional AggregateRating) from testimonials.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>|null}
 */
function hr_sa_trip_testimonials_nodes(int $trip_id, string $product_id, array $country_term_ids): array
{
    $posts = get_posts([
        'post_type'      => 'testimonial',
        'posts_per_page' => 20,
        'tax_query'      => [
            [
                'taxonomy' => 'country',
                'field'    => 'term_id',
                'terms'    => $country_term_ids,
            ],
        ],
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if (!$posts) {
        return [[], null];
    }

    $reviews = [];
    $ratings = [];

    foreach ($posts as $testimonial) {
        $url       = get_permalink($testimonial->ID);
        $review_id = $url ?: (get_permalink($trip_id) . '#review-' . $testimonial->ID);

        $author = get_post_meta($testimonial->ID, 'author_name', true);
        if ($author === '') {
            $author = get_the_author_meta('display_name', $testimonial->post_author) ?: 'Verified Guest';
        }

        $rating = get_post_meta($testimonial->ID, 'rating', true);
        $date   = get_post_meta($testimonial->ID, 'review_date', true);
        if ($date === '') {
            $date = get_the_date('Y-m-d', $testimonial->ID);
        }

        $body = hr_sa_trip_clean_text(strip_shortcodes($testimonial->post_content), 120);
        if ($body === '') {
            $body = get_the_title($testimonial->ID);
        }

        $node = [
            '@type'         => 'Review',
            '@id'           => $review_id,
            'reviewBody'    => $body,
            'datePublished' => $date,
            'author'        => [
                '@type' => 'Person',
                'name'  => $author,
            ],
            'itemReviewed'  => ['@id' => $product_id],
        ];

        if ($rating !== '' && is_numeric($rating)) {
            $value = max(0, min(5, (float) $rating));
            if ($value > 0) {
                $node['reviewRating'] = [
                    '@type'       => 'Rating',
                    'ratingValue' => $value,
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ];
                $ratings[] = $value;
            }
        }

        $reviews[] = $node;
    }

    $aggregate = null;
    if (count($ratings) >= 3) {
        $average   = array_sum($ratings) / count($ratings);
        $aggregate = [
            '@type'       => 'AggregateRating',
            'ratingValue' => round($average, 2),
            'reviewCount' => count($ratings),
            'bestRating'  => 5,
            'worstRating' => 1,
        ];
    }

    return [$reviews, $aggregate];
}
