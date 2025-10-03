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
 * Build Product graph nodes for a trip.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_build_product_nodes(int $trip_id): array
{
    if ($trip_id <= 0 || get_post_type($trip_id) !== 'trip') {
        return [];
    }

    $product_url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('trip.product.url', $trip_id));
    if ($product_url === '') {
        return [];
    }

    $product_id = trailingslashit($product_url) . '#trip';

    $product = [
        '@type' => 'Product',
        '@id'   => $product_id,
        'url'   => $product_url,
        'brand' => [
            '@type' => 'Brand',
            '@id'   => hr_sa_jsonld_org_id(),
        ],
    ];

    $name = hr_sa_hrdf_get('trip.product.name', $trip_id);
    if (is_string($name) && $name !== '') {
        $product['name'] = $name;
    }

    $images = hr_sa_trip_collect_product_images($trip_id);
    if ($images) {
        $product['image'] = $images;
    }

    $description = hr_sa_hrdf_get('trip.product.description', $trip_id);
    if (is_string($description) && $description !== '') {
        $product['description'] = hr_sa_jsonld_clean_text($description, 60);
    } else {
        $fallback = hr_sa_jsonld_description_fallback($trip_id, 60);
        if ($fallback !== '') {
            $product['description'] = $fallback;
        }
    }

    $string_fields = [
        'sku'      => 'trip.product.sku',
        'mpn'      => 'trip.product.mpn',
        'color'    => 'trip.product.color',
        'category' => 'trip.product.category',
    ];

    foreach ($string_fields as $property => $path) {
        $value = hr_sa_hrdf_get($path, $trip_id);
        if (is_string($value) && $value !== '') {
            $product[$property] = $value;
        }
    }

    $additional_properties = hr_sa_trip_collect_additional_properties($trip_id);
    if ($additional_properties) {
        $product['additionalProperty'] = $additional_properties;
    }

    $offers = hr_sa_trip_collect_offers($trip_id, $product_url);
    if ($offers) {
        $aggregate_offer = hr_sa_trip_build_aggregate_offer($offers, $trip_id);
        if ($aggregate_offer) {
            $product['offers'] = $aggregate_offer;
        }
    }

    $about_refs = hr_sa_trip_collect_about_references($trip_id);
    if ($about_refs) {
        $product['about'] = $about_refs;
    }

    $has_part_refs = hr_sa_trip_collect_has_part_references($trip_id);
    if ($has_part_refs) {
        $product['hasPart'] = $has_part_refs;
    }

    $graph         = [$product];
    $product_index = 0;

    [$vehicle_nodes, $vehicle_about] = hr_sa_trip_bike_nodes($trip_id);
    foreach ($vehicle_nodes as $vehicle_node) {
        $graph[] = $vehicle_node;
    }
    if ($vehicle_about) {
        $product['about'] = array_merge($product['about'] ?? [], $vehicle_about);
    }

    [$itinerary_node, $itinerary_reference] = hr_sa_trip_itinerary_node($trip_id, $product_id);
    if ($itinerary_node) {
        $graph[] = $itinerary_node;
    }
    if ($itinerary_reference) {
        $product['hasPart'] = array_merge($product['hasPart'] ?? [], [$itinerary_reference]);
    }

    [$review_nodes, $aggregate_rating] = hr_sa_trip_testimonials_nodes($trip_id, $product_id);
    foreach ($review_nodes as $review_node) {
        $graph[] = $review_node;
    }
    if ($aggregate_rating) {
        $product['aggregateRating'] = $aggregate_rating;
    }

    $faq_nodes = hr_sa_trip_faq_nodes($trip_id);
    foreach ($faq_nodes as $faq_node) {
        $graph[] = $faq_node;
    }

    if (!empty($product['about'])) {
        $product['about'] = array_values($product['about']);
    }
    if (!empty($product['hasPart'])) {
        $product['hasPart'] = array_values($product['hasPart']);
    }

    $graph[$product_index] = $product;

    return $graph;
}

/**
 * Collect product images from HRDF.
 *
 * @return array<int, string>
 */
function hr_sa_trip_collect_product_images(int $trip_id): array
{
    $images = hr_sa_hrdf_get_array('trip.product.images', $trip_id);
    $images = array_values(array_filter(array_map('hr_sa_jsonld_normalize_url', $images)));

    return $images;
}

/**
 * Collect additional properties for the product from HRDF.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_collect_additional_properties(int $trip_id): array
{
    $properties = [];
    $raw        = hr_sa_hrdf_get_array('trip.product.additional_properties', $trip_id);

    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }

        $name  = isset($row['name']) ? trim((string) $row['name']) : '';
        $value = isset($row['value']) ? hr_sa_jsonld_clean_text($row['value']) : '';

        if ($name === '' || $value === '') {
            continue;
        }

        $property = [
            '@type' => 'PropertyValue',
            'name'  => $name,
            'value' => $value,
        ];

        if (!empty($row['unitCode']) && is_string($row['unitCode'])) {
            $property['unitCode'] = strtoupper(trim($row['unitCode']));
        }

        if (!empty($row['valueReference']) && is_array($row['valueReference'])) {
            $property['valueReference'] = $row['valueReference'];
        }

        $properties[] = $property;
    }

    return $properties;
}

/**
 * Collect offer nodes from HRDF.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_collect_offers(int $trip_id, string $product_url): array
{
    $offers     = [];
    $raw_offers = hr_sa_hrdf_get_array('trip.offers', $trip_id);
    $default    = $product_url !== '' ? trailingslashit($product_url) . '#offer' : '';

    foreach ($raw_offers as $raw) {
        if (!is_array($raw)) {
            continue;
        }

        $offer = hr_sa_jsonld_prepare_offer($raw, $default);
        if ($offer !== null) {
            $offers[] = $offer;
        }
    }

    return $offers;
}

/**
 * Build an AggregateOffer wrapper for product offers.
 */
function hr_sa_trip_build_aggregate_offer(array $offers, int $trip_id): ?array
{
    if (!$offers) {
        return null;
    }

    $aggregate = [
        '@type'  => 'AggregateOffer',
        'offers' => $offers,
    ];

    $currency = hr_sa_hrdf_get('trip.aggregate_offer.currency', $trip_id);
    $currencies = array_values(array_unique(array_filter(array_map(
        static fn($offer) => $offer['priceCurrency'] ?? '',
        $offers
    ))));

    if (!is_string($currency) || $currency === '') {
        if (count($currencies) === 1) {
            $currency = $currencies[0];
        } else {
            $currency = '';
        }
    }

    if ($currency !== '') {
        $aggregate['priceCurrency'] = $currency;
    }

    $prices = array_values(array_filter(array_map(
        static fn($offer) => isset($offer['price']) && is_numeric($offer['price']) ? (float) $offer['price'] : null,
        $offers
    )));

    if ($prices) {
        $aggregate['lowPrice']   = number_format((float) min($prices), 2, '.', '');
        $aggregate['highPrice']  = number_format((float) max($prices), 2, '.', '');
        $aggregate['offerCount'] = count($offers);
    } else {
        $aggregate['offerCount'] = count($offers);
    }

    $aggregate = hr_sa_jsonld_array_filter($aggregate);

    return $aggregate ?: null;
}

/**
 * Collect about references for the Product node.
 *
 * @return array<int, array<string, string>>
 */
function hr_sa_trip_collect_about_references(int $trip_id): array
{
    $about = [];
    $raw   = hr_sa_hrdf_get_array('trip.product.about', $trip_id);

    foreach ($raw as $item) {
        if (is_array($item)) {
            $candidate = hr_sa_jsonld_array_filter($item);
            if ($candidate) {
                $about[] = $candidate;
            }
            continue;
        }

        $url = hr_sa_jsonld_normalize_url($item);
        if ($url !== '') {
            $about[] = ['@id' => $url];
        }
    }

    return $about;
}

/**
 * Collect hasPart references for the Product node.
 *
 * @return array<int, array<string, string>>
 */
function hr_sa_trip_collect_has_part_references(int $trip_id): array
{
    $has_part = [];
    $raw      = hr_sa_hrdf_get_array('trip.product.has_part', $trip_id);

    foreach ($raw as $item) {
        if (is_array($item)) {
            $candidate = hr_sa_jsonld_array_filter($item);
            if ($candidate) {
                $has_part[] = $candidate;
            }
            continue;
        }

        $url = hr_sa_jsonld_normalize_url($item);
        if ($url !== '') {
            $has_part[] = ['@id' => $url];
        }
    }

    return $has_part;
}

/**
 * Build Review nodes (and optional AggregateRating) from HRDF reviews.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>|null}
 */
function hr_sa_trip_testimonials_nodes(int $trip_id, string $product_id): array
{
    $reviews     = [];
    $raw_reviews = hr_sa_hrdf_get_array('trip.reviews.items', $trip_id);

    foreach ($raw_reviews as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $review_id = hr_sa_jsonld_normalize_url($item['id'] ?? ($item['url'] ?? ''));
        if ($review_id === '') {
            $slug = isset($item['slug']) ? sanitize_title((string) $item['slug']) : 'review-' . ($index + 1);
            $review_id = trailingslashit($product_id) . $slug;
        }

        $review = [
            '@type'        => 'Review',
            '@id'          => $review_id,
            'itemReviewed' => ['@id' => $product_id],
        ];

        $body = $item['reviewBody'] ?? $item['body'] ?? '';
        if (is_string($body) && $body !== '') {
            $review['reviewBody'] = hr_sa_jsonld_clean_text($body, 120);
        }

        $date = hr_sa_jsonld_normalize_iso8601($item['datePublished'] ?? $item['date'] ?? '');
        if ($date !== '') {
            $review['datePublished'] = $date;
        }

        $author_name = $item['author']['name'] ?? $item['author_name'] ?? '';
        if (is_string($author_name) && $author_name !== '') {
            $review['author'] = [
                '@type' => isset($item['author']['@type']) && is_string($item['author']['@type'])
                    ? $item['author']['@type']
                    : 'Person',
                'name'  => $author_name,
            ];
        }

        $rating_value = $item['reviewRating']['ratingValue'] ?? $item['rating'] ?? null;
        if ($rating_value !== null && $rating_value !== '' && is_numeric($rating_value)) {
            $rating = [
                '@type'       => 'Rating',
                'ratingValue' => round((float) $rating_value, 2),
            ];

            $best = $item['reviewRating']['bestRating'] ?? $item['bestRating'] ?? null;
            if ($best !== null && is_numeric($best)) {
                $rating['bestRating'] = (float) $best;
            }

            $worst = $item['reviewRating']['worstRating'] ?? $item['worstRating'] ?? null;
            if ($worst !== null && is_numeric($worst)) {
                $rating['worstRating'] = (float) $worst;
            }

            $review['reviewRating'] = $rating;
        }

        if (!empty($item['publisher']) && is_array($item['publisher'])) {
            $review['publisher'] = $item['publisher'];
        }

        $review = hr_sa_jsonld_array_filter($review);
        if ($review) {
            $reviews[] = $review;
        }
    }

    $aggregate = null;
    $raw_aggregate = hr_sa_hrdf_get('trip.reviews.aggregate', $trip_id);
    if (is_array($raw_aggregate)) {
        $aggregate = hr_sa_jsonld_array_filter([
            '@type'       => 'AggregateRating',
            'ratingValue' => isset($raw_aggregate['ratingValue']) && is_numeric($raw_aggregate['ratingValue'])
                ? (float) $raw_aggregate['ratingValue']
                : null,
            'reviewCount' => isset($raw_aggregate['reviewCount']) && is_numeric($raw_aggregate['reviewCount'])
                ? (int) $raw_aggregate['reviewCount']
                : null,
            'bestRating'  => isset($raw_aggregate['bestRating']) && is_numeric($raw_aggregate['bestRating'])
                ? (float) $raw_aggregate['bestRating']
                : null,
            'worstRating' => isset($raw_aggregate['worstRating']) && is_numeric($raw_aggregate['worstRating'])
                ? (float) $raw_aggregate['worstRating']
                : null,
        ]);
    }

    return [$reviews, $aggregate];
}
