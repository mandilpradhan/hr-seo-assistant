<?php
/**
 * Plugin Name: HR — Trip Schema Graph (Consolidated)
 * Description: Emits Product + Offers for trips, and appends Itinerary (ItemList), Testimonials (Review/AggregateRating), Additional Details, and Bike (Vehicle) nodes. Organization/WebSite/WebPage are handled by hr-schema-core.php.
 * Version: 2.2.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const HR_TRIP_POST_TYPE  = 'trip';
const HR_COUNTRY_TAX     = 'country';
const HR_CPT_ITINERARY   = 'itinerary';
const HR_CPT_TESTIMONIAL = 'testimonial';
const HR_CPT_ADDITIONAL  = 'additional';
const HR_CPT_BIKE        = 'bike';

const HR_ACF_PROP_DURATION  = 'duration';
const HR_ACF_PROP_DISTANCE  = 'distance';
const HR_ACF_PROP_SEASON    = 'seasons';
const HR_ACF_PROP_GROUPSIZE = 'group_size';

const HR_META_ITIN_STEPS  = 'itinerary_steps';
const HR_META_TEST_RATING = 'rating';
const HR_META_TEST_AUTHOR = 'author_name';
const HR_META_TEST_DATE   = 'review_date';

const HR_CURRENCY = 'USD';

/**
 * Trim and normalize text pulled from post content.
 */
function hr_trip_clean_text($html, int $words = 0): string
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
function hr_trip_images(int $post_id, int $max = 8): array
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
function hr_trip_additional_properties(int $post_id): array
{
    $properties = [];
    $map = [
        'Duration'   => HR_ACF_PROP_DURATION,
        'Distance'   => HR_ACF_PROP_DISTANCE,
        'Seasons'    => HR_ACF_PROP_SEASON,
        'Group Size' => HR_ACF_PROP_GROUPSIZE,
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
            $value = hr_trip_clean_text($value);
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
 * Build Vehicle nodes for bikes associated with country terms.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, array<string, string>>}
 */
function hr_trip_bike_nodes(int $trip_id, array $country_term_ids): array
{
    $nodes = [];
    $names = [];
    $about = [];

    $bikes = get_posts([
        'post_type'      => HR_CPT_BIKE,
        'posts_per_page' => 12,
        'tax_query'      => [
            [
                'taxonomy' => HR_COUNTRY_TAX,
                'field'    => 'term_id',
                'terms'    => $country_term_ids,
            ],
        ],
        'post_status'    => 'publish',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    $trip_url = get_permalink($trip_id);

    foreach ($bikes as $bike) {
        $url = get_permalink($bike->ID);
        $bike_id = $url ? ($url . '#bike') : ($trip_url . '#bike-' . $bike->ID);
        $name = get_the_title($bike->ID);

        $image = null;
        if (function_exists('get_field')) {
            $field = get_field('photo_bike', $bike->ID);
            if (is_array($field)) {
                if (!empty($field['url'])) {
                    $image = $field['url'];
                } elseif (!empty($field['ID'])) {
                    $image = wp_get_attachment_image_url((int) $field['ID'], 'full');
                }
            } elseif (is_numeric($field)) {
                $image = wp_get_attachment_image_url((int) $field, 'full');
            } elseif (is_string($field) && filter_var($field, FILTER_VALIDATE_URL)) {
                $image = $field;
            }
        }

        if (!$image) {
            $image = get_the_post_thumbnail_url($bike->ID, 'full');
        }

        $node = array_filter([
            '@type' => 'Vehicle',
            '@id'   => $bike_id,
            'name'  => $name ?: null,
            'image' => $image ?: null,
            'url'   => $url ?: null,
        ], static fn($value) => $value !== null && $value !== '');

        $nodes[] = $node;
        if ($name) {
            $names[] = $name;
        }
        $about[] = ['@id' => $bike_id];
    }

    return [$nodes, $names, $about];
}

/**
 * Build an ItemList node representing the itinerary.
 *
 * @return array{0: array<string, mixed>|null, 1: array<string, string>|null}
 */
function hr_trip_itinerary_node(int $trip_id, string $product_id, array $country_term_ids): array
{
    $posts = get_posts([
        'post_type'      => HR_CPT_ITINERARY,
        'posts_per_page' => -1,
        'tax_query'      => [
            [
                'taxonomy' => HR_COUNTRY_TAX,
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
        $title = hr_trip_clean_text(get_the_title($itinerary->ID));
        if ($title === '') {
            continue;
        }

        $summary = '';
        $labels = [];

        $steps = function_exists('get_field') ? get_field(HR_META_ITIN_STEPS, $itinerary->ID) : get_post_meta($itinerary->ID, HR_META_ITIN_STEPS, true);
        if (is_array($steps) && $steps) {
            foreach ($steps as $row) {
                $name = '';
                if (is_array($row)) {
                    $name = $row['title'] ?? ($row['name'] ?? '');
                } else {
                    $name = (string) $row;
                }

                $name = hr_trip_clean_text($name);
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
                    $heading = hr_trip_clean_text($heading);
                    if ($heading) {
                        $labels[] = $heading;
                    }
                    if (count($labels) >= 8) {
                        break;
                    }
                }
                $summary = implode('; ', $labels);
            } else {
                $summary = hr_trip_clean_text($itinerary->post_content, 50);
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

    $trip_url = get_permalink($trip_id);
    $itinerary_id = $trip_url . '#itinerary';
    $node = [
        '@type'           => 'ItemList',
        '@id'             => $itinerary_id,
        'name'            => 'Itinerary',
        'itemListElement' => $elements,
    ];

    return [$node, ['@id' => $itinerary_id]];
}

/**
 * Build Review nodes (and optional AggregateRating) from testimonials.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>|null}
 */
function hr_trip_testimonials_nodes(int $trip_id, string $product_id, array $country_term_ids): array
{
    $posts = get_posts([
        'post_type'      => HR_CPT_TESTIMONIAL,
        'posts_per_page' => 20,
        'tax_query'      => [
            [
                'taxonomy' => HR_COUNTRY_TAX,
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
        $url = get_permalink($testimonial->ID);
        $review_id = $url ?: (get_permalink($trip_id) . '#review-' . $testimonial->ID);

        $author = get_post_meta($testimonial->ID, HR_META_TEST_AUTHOR, true);
        if ($author === '') {
            $author = get_the_author_meta('display_name', $testimonial->post_author) ?: 'Verified Guest';
        }

        $rating = get_post_meta($testimonial->ID, HR_META_TEST_RATING, true);
        $date = get_post_meta($testimonial->ID, HR_META_TEST_DATE, true);
        if ($date === '') {
            $date = get_the_date('Y-m-d', $testimonial->ID);
        }

        $body = hr_trip_clean_text(strip_shortcodes($testimonial->post_content), 120);
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
        $average = array_sum($ratings) / count($ratings);
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

/**
 * Build additional PropertyValue/WebPage nodes.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, string>>}
 */
function hr_trip_additional_nodes(int $trip_id, string $product_id, array $country_term_ids): array
{
    $posts = get_posts([
        'post_type'      => HR_CPT_ADDITIONAL,
        'posts_per_page' => 10,
        'tax_query'      => [
            [
                'taxonomy' => HR_COUNTRY_TAX,
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
    $webpages = [];
    $has_parts = [];

    foreach ($posts as $post) {
        $url = get_permalink($post->ID);
        $name = get_the_title($post->ID) ?: 'Additional Information';
        $description = hr_trip_clean_text(strip_shortcodes($post->post_content), 40);

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

    $trip_url = get_permalink($trip_id);
    $additional_id = $trip_url . '#additional-details';

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
function hr_trip_build_offers(int $trip_id, string $trip_url): array
{
    if (!function_exists('_hr_acf_fetch_trip_data')) {
        return [];
    }

    $offers = [];
    $data = _hr_acf_fetch_trip_data($trip_id);
    if (!$data || empty($data['dates'])) {
        return $offers;
    }

    $rider_price = isset($data['rider']) ? (float) $data['rider'] : null;
    $threshold = defined('HR_THRESHOLD_MIN_SPOTS') ? (int) HR_THRESHOLD_MIN_SPOTS : 4;
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
        $seats = isset($data['seats'][$ymd]) ? (int) $data['seats'][$ymd] : null;

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
            '@type'             => 'Offer',
            'price'             => (string) $rider_price,
            'priceCurrency'     => HR_CURRENCY,
            'availability'      => $availability,
            'availabilityStarts'=> $ymd,
            'url'               => trailingslashit($trip_url) . '#intro',
            'inventoryLevel'    => $remaining !== null ? ['@type' => 'QuantitativeValue', 'value' => $remaining] : null,
        ];

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
function hr_trip_build_product_nodes(int $trip_id): array
{
    if ($trip_id <= 0 || get_post_type($trip_id) !== HR_TRIP_POST_TYPE) {
        return [];
    }

    $trip_url = get_permalink($trip_id);
    if (!$trip_url) {
        return [];
    }
    $trip_url = trailingslashit($trip_url);
    $product_id = $trip_url . '#trip';

    $brand_id = function_exists('hr_schema_core_org_id')
        ? hr_schema_core_org_id()
        : trailingslashit(set_url_scheme(home_url('/'), 'https')) . '#org';

    $product = [
        '@type' => 'Product',
        '@id'   => $product_id,
        'name'  => get_the_title($trip_id),
        'image' => hr_trip_images($trip_id),
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
    $description = hr_trip_clean_text($description, 60);
    if ($description !== '') {
        $product['description'] = $description;
    }

    $properties = hr_trip_additional_properties($trip_id);
    if ($properties) {
        $product['additionalProperty'] = $properties;
    }

    $offers = hr_trip_build_offers($trip_id, $trip_url);
    if ($offers) {
        $prices = array_filter(array_map(static fn($offer) => isset($offer['price']) ? (float) $offer['price'] : null, $offers), static fn($value) => $value !== null);
        if ($prices) {
            $product['offers'] = [
                '@type'         => 'AggregateOffer',
                'offerCount'    => count($offers),
                'priceCurrency' => HR_CURRENCY,
                'lowPrice'      => (string) min($prices),
                'highPrice'     => (string) max($prices),
                'offers'        => $offers,
            ];
        }
    }

    $graph = [$product];
    $product_index = 0;

    $country_terms = wp_get_post_terms($trip_id, HR_COUNTRY_TAX, ['fields' => 'ids']);
    if (!is_wp_error($country_terms) && $country_terms) {
        [$bike_nodes, $bike_names, $bike_about] = hr_trip_bike_nodes($trip_id, $country_terms);

        if ($bike_nodes && function_exists('hr_wte_build_vehicle_offer_map')) {
            $offer_map = hr_wte_build_vehicle_offer_map($trip_id, $trip_url);
            if (is_array($offer_map) && $offer_map) {
                foreach ($bike_nodes as &$bike_node) {
                    if (empty($bike_node['name'])) {
                        continue;
                    }
                    $key = function_exists('_hr_norm_bike_name')
                        ? _hr_norm_bike_name($bike_node['name'])
                        : strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $bike_node['name'])));
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

        [$itinerary_node, $itinerary_about] = hr_trip_itinerary_node($trip_id, $product_id, $country_terms);
        if ($itinerary_node) {
            $graph[] = $itinerary_node;
        }
        if ($itinerary_about) {
            $graph[$product_index]['hasPart'] = array_merge($graph[$product_index]['hasPart'] ?? [], [$itinerary_about]);
        }

        [$review_nodes, $aggregate_rating] = hr_trip_testimonials_nodes($trip_id, $product_id, $country_terms);
        foreach ($review_nodes as $review) {
            $graph[] = $review;
        }
        if ($aggregate_rating) {
            $graph[$product_index]['aggregateRating'] = $aggregate_rating;
        }

        [$property_values, $detail_nodes, $has_parts] = hr_trip_additional_nodes($trip_id, $product_id, $country_terms);
        foreach ($detail_nodes as $node) {
            $graph[] = $node;
        }
        if ($property_values) {
            $graph[$product_index]['additionalProperty'] = array_merge($graph[$product_index]['additionalProperty'] ?? [], $property_values);
        }
        if ($has_parts) {
            $graph[$product_index]['hasPart'] = array_merge($graph[$product_index]['hasPart'] ?? [], $has_parts);
        }
    }

    return $graph;
}

/**
 * Filter hook: append Trip schema nodes to the shared graph when viewing a trip.
 */
function hr_trip_schema_extend_graph(array $graph): array
{
    if (!is_singular(HR_TRIP_POST_TYPE)) {
        return $graph;
    }

    $trip_id = get_queried_object_id();
    if (!$trip_id) {
        return $graph;
    }

    static $cache = [];
    if (!isset($cache[$trip_id])) {
        $cache[$trip_id] = hr_trip_build_product_nodes((int) $trip_id);
    }

    foreach ($cache[$trip_id] as $node) {
        if (is_array($node)) {
            $graph[] = $node;
        }
    }

    return $graph;
}
add_filter('hr_schema_graph_nodes', 'hr_trip_schema_extend_graph', 10);

/**
 * Public helper retained for backwards compatibility.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_trip_schema_nodes(int $trip_id): array
{
    return hr_trip_build_product_nodes($trip_id);
}

/**
 * Append FAQPage nodes (if available) to the schema graph.
 */
function hr_trip_schema_append_faq(array $graph): array
{
    if (!is_singular(HR_TRIP_POST_TYPE)) {
        return $graph;
    }

    $trip_id = get_queried_object_id();
    if (!$trip_id) {
        return $graph;
    }

    $faq_nodes = hr_trip_schema_faq_nodes((int) $trip_id);
    if (!$faq_nodes) {
        return $graph;
    }

    $seen = [];
    foreach ($graph as $node) {
        if (is_array($node) && !empty($node['@id'])) {
            $seen[$node['@id']] = true;
        }
    }

    foreach ($faq_nodes as $node) {
        $id = is_array($node) && !empty($node['@id']) ? $node['@id'] : null;
        if ($id && isset($seen[$id])) {
            continue;
        }
        $graph[] = $node;
        if ($id) {
            $seen[$id] = true;
        }
    }

    return $graph;
}
add_filter('hr_schema_graph_nodes', 'hr_trip_schema_append_faq', 20);

/**
 * Build FAQPage nodes for a Trip by matching FAQ CPT via shared country terms.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_trip_schema_faq_nodes(int $trip_id): array
{
    if ($trip_id <= 0 || get_post_type($trip_id) !== HR_TRIP_POST_TYPE) {
        return [];
    }

    $cache_key = sprintf('hr_faq_nodes_trip_%d', $trip_id);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $terms = get_the_terms($trip_id, HR_COUNTRY_TAX);
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
                'taxonomy'         => HR_COUNTRY_TAX,
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
        if (function_exists('hr_schema_sanitize_answer_html')) {
            $clean = hr_schema_sanitize_answer_html($raw);
        } else {
            $clean = wp_kses_post(strip_shortcodes($raw));
        }

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

    $trip_url = set_url_scheme(get_permalink($trip_id), 'https');
    $node = [
        '@type'      => 'FAQPage',
        '@id'        => trailingslashit($trip_url) . '#faqs',
        'mainEntity' => array_values($qas),
    ];

    $result = [$node];
    set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);

    return $result;
}