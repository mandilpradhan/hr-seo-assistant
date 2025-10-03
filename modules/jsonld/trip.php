<?php
/**
 * Trip/Product graph emitters sourced from HRDF.
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
 * Build Product, Offer, Vehicle, Itinerary, FAQ, and Review nodes using HRDF data.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_build_product_nodes(int $trip_id): array
{
    if ($trip_id <= 0 || get_post_type($trip_id) !== 'trip') {
        return [];
    }

    $payload    = hr_sa_hrdf_trip_payload($trip_id);
    $trip_url   = isset($payload['url']) ? (string) $payload['url'] : '';
    $product_id = $trip_url !== '' ? rtrim($trip_url, '/') . '#product' : '';

    $product = ['@type' => 'Product'];

    if ($product_id !== '') {
        $product['@id'] = $product_id;
        $product['url'] = $trip_url;
    }

    if ($payload['title'] !== '') {
        $product['name'] = $payload['title'];
    }

    if ($payload['description'] !== '') {
        $product['description'] = $payload['description'];
    }

    if (!empty($payload['images'])) {
        $product['image'] = $payload['images'];
    }

    $additional_properties = hr_sa_trip_build_additional_properties_from_hrdf($payload['additional_property']);
    if ($additional_properties) {
        $product['additionalProperty'] = $additional_properties;
    }

    $offers_data = hr_sa_trip_build_offers_from_hrdf($payload['offers'], $product_id, $trip_url);
    if (!empty($offers_data)) {
        $product['offers'] = $offers_data;
    }

    if (is_array($payload['aggregate_rating']) && $payload['aggregate_rating']) {
        $aggregate_rating = hr_sa_trip_filter_schema_array([
            '@type'       => 'AggregateRating',
            'ratingValue' => isset($payload['aggregate_rating']['ratingValue']) ? (float) $payload['aggregate_rating']['ratingValue'] : null,
            'reviewCount' => isset($payload['aggregate_rating']['reviewCount']) ? (int) $payload['aggregate_rating']['reviewCount'] : null,
        ]);
        if ($aggregate_rating) {
            $product['aggregateRating'] = $aggregate_rating;
        }
    }

    [$review_nodes, $review_refs] = hr_sa_trip_build_reviews_from_hrdf($payload['reviews'], $product_id);
    if ($review_refs) {
        $product['review'] = $review_refs;
    }

    $graph   = [$product];
    $product_index = 0;

    [$itinerary_node, $itinerary_ref] = hr_sa_trip_itinerary_node_from_hrdf($payload['itinerary'], $trip_url, $product_id);
    if ($itinerary_node) {
        $graph[] = $itinerary_node;
    }
    if ($itinerary_ref) {
        $graph[$product_index]['hasPart'] = array_merge($graph[$product_index]['hasPart'] ?? [], [$itinerary_ref]);
    }

    $faq_nodes = hr_sa_trip_faq_nodes_from_hrdf($payload['faq'], $trip_url, $product_id);
    foreach ($faq_nodes as $node) {
        $graph[] = $node;
    }

    [$vehicle_nodes, $vehicle_refs] = hr_sa_trip_vehicle_nodes_from_hrdf($payload['vehicles'], $trip_url, $product_id);
    foreach ($vehicle_nodes as $node) {
        $graph[] = $node;
    }
    if ($vehicle_refs) {
        $graph[$product_index]['isRelatedTo'] = array_merge($graph[$product_index]['isRelatedTo'] ?? [], $vehicle_refs);
    }

    foreach ($review_nodes as $node) {
        $graph[] = $node;
    }

    return $graph;
}

/**
 * Normalize additionalProperty entries from HRDF.
 *
 * @param array<int, mixed> $properties
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_build_additional_properties_from_hrdf(array $properties): array
{
    $normalized = [];

    foreach ($properties as $property) {
        if (!is_array($property)) {
            continue;
        }

        $name  = isset($property['name']) ? hr_sa_hrdf_normalize_text($property['name']) : '';
        $value = isset($property['value']) ? hr_sa_hrdf_normalize_text($property['value']) : '';

        if ($name === '' || $value === '') {
            continue;
        }

        $normalized[] = [
            '@type' => 'PropertyValue',
            'name'  => $name,
            'value' => $value,
        ];
    }

    return $normalized;
}

/**
 * Build an AggregateOffer node from HRDF offers.
 *
 * @param array<int, mixed> $offers
 * @return array<string, mixed>
 */
function hr_sa_trip_build_offers_from_hrdf(array $offers, string $product_id, string $trip_url): array
{
    $normalized = [];

    foreach ($offers as $index => $offer) {
        if (!is_array($offer)) {
            continue;
        }

        $prepared = hr_sa_trip_normalize_offer($offer, $product_id, $trip_url, (int) $index);
        if ($prepared === null) {
            continue;
        }

        $normalized[] = $prepared;
    }

    if (!$normalized) {
        return [];
    }

    $offer_nodes = array_column($normalized, 'node');
    $amounts     = array_column($normalized, 'amount');
    $currencies  = array_filter(array_column($normalized, 'currency'), 'strlen');

    $aggregate = [
        '@type'      => 'AggregateOffer',
        'offerCount' => count($offer_nodes),
        'offers'     => $offer_nodes,
    ];

    if ($currencies) {
        $aggregate['priceCurrency'] = $currencies[0];
    }

    if ($amounts) {
        $aggregate['lowPrice']  = hr_sa_hrdf_format_price((float) min($amounts));
        $aggregate['highPrice'] = hr_sa_hrdf_format_price((float) max($amounts));
    }

    return hr_sa_trip_filter_schema_array($aggregate);
}

/**
 * Normalize a single Offer entry.
 *
 * @param array<string, mixed> $offer
 *
 * @return array{node: array<string, mixed>, amount: float, currency: string}|null
 */
function hr_sa_trip_normalize_offer(array $offer, string $product_id, string $trip_url, int $index): ?array
{
    $price = $offer['price'] ?? [];
    if (!is_array($price)) {
        return null;
    }

    $amount   = $price['amount'] ?? null;
    $currency = isset($price['currency']) ? strtoupper(hr_sa_hrdf_normalize_text($price['currency'])) : '';

    if (!is_numeric($amount) || $currency === '') {
        return null;
    }

    $amount_float = (float) $amount;
    $node         = [
        '@type'         => 'Offer',
        'price'         => hr_sa_hrdf_format_price($amount_float),
        'priceCurrency' => $currency,
    ];

    $name = isset($offer['name']) ? hr_sa_hrdf_normalize_text($offer['name']) : '';
    if ($name !== '') {
        $node['name'] = $name;
    }

    $availability = isset($offer['availability']) ? (string) $offer['availability'] : '';
    if ($availability !== '') {
        $node['availability'] = $availability;
    }

    $inventory = $offer['inventory_remaining'] ?? null;
    if (is_numeric($inventory)) {
        $node['inventoryLevel'] = [
            '@type' => 'QuantitativeValue',
            'value' => (int) $inventory,
        ];
    }

    $eligible = $offer['eligible_quantity'] ?? null;
    if (is_numeric($eligible)) {
        $node['eligibleQuantity'] = [
            '@type' => 'QuantitativeValue',
            'value' => (int) $eligible,
        ];
    }

    $valid_from = isset($offer['valid_from']) ? (string) $offer['valid_from'] : '';
    if ($valid_from !== '') {
        $node['validFrom'] = $valid_from;
    }

    $availability_starts = isset($offer['date']) ? (string) $offer['date'] : '';
    if ($availability_starts !== '') {
        $node['availabilityStarts'] = $availability_starts;
    }

    $availability_ends = isset($offer['availability_ends']) ? (string) $offer['availability_ends'] : '';
    if ($availability_ends !== '') {
        $node['availabilityEnds'] = $availability_ends;
    }

    $url = isset($offer['url']) ? hr_sa_hrdf_normalize_url($offer['url']) : '';
    if ($url !== '') {
        $node['url'] = $url;
    }

    $offer_id = '';
    if ($url !== '') {
        $offer_id = rtrim($url, '#') . '#offer';
    } elseif ($product_id !== '') {
        $offer_id = $product_id . '-offer-' . ($index + 1);
    }

    if ($offer_id !== '') {
        $node['@id'] = $offer_id;
    }

    $node = hr_sa_trip_filter_schema_array($node);
    if (!$node) {
        return null;
    }

    return [
        'node'     => $node,
        'amount'   => $amount_float,
        'currency' => $currency,
    ];
}

/**
 * Build Review nodes from HRDF data.
 *
 * @param array<int, mixed> $reviews
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>}
 */
function hr_sa_trip_build_reviews_from_hrdf(array $reviews, string $product_id): array
{
    $nodes = [];
    $refs  = [];

    foreach ($reviews as $index => $review) {
        if (!is_array($review)) {
            continue;
        }

        $rating_value = isset($review['ratingValue']) && is_numeric($review['ratingValue'])
            ? (float) $review['ratingValue']
            : null;

        $node = [
            '@type' => 'Review',
        ];

        if ($product_id !== '') {
            $node['itemReviewed'] = ['@id' => $product_id];
            $node['@id']         = $product_id . '-review-' . ((int) $index + 1);
            $refs[]              = ['@id' => $node['@id']];
        }

        $author = isset($review['author']) ? hr_sa_hrdf_normalize_text($review['author']) : '';
        if ($author !== '') {
            $node['author'] = [
                '@type' => 'Person',
                'name'  => $author,
            ];
        }

        $date = isset($review['datePublished']) ? hr_sa_hrdf_normalize_text($review['datePublished']) : '';
        if ($date !== '') {
            $node['datePublished'] = $date;
        }

        $body = isset($review['reviewBody']) ? hr_sa_hrdf_normalize_text($review['reviewBody']) : '';
        if ($body !== '') {
            $node['reviewBody'] = $body;
        }

        if ($rating_value !== null) {
            $best = isset($review['bestRating']) && is_numeric($review['bestRating']) ? (float) $review['bestRating'] : 5.0;
            $node['reviewRating'] = hr_sa_trip_filter_schema_array([
                '@type'       => 'Rating',
                'ratingValue' => $rating_value,
                'bestRating'  => $best,
            ]);
        }

        $node = hr_sa_trip_filter_schema_array($node);
        if (!$node) {
            array_pop($refs);
            continue;
        }

        $nodes[] = $node;
    }

    return [$nodes, $refs];
}

/**
 * Remove empty values from a schema array while preserving zero-like values.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function hr_sa_trip_filter_schema_array(array $data): array
{
    foreach ($data as $key => $value) {
        if ($value === null) {
            unset($data[$key]);
            continue;
        }

        if (is_array($value)) {
            $value = hr_sa_trip_filter_schema_array($value);
            if ($value === []) {
                unset($data[$key]);
                continue;
            }
            $data[$key] = $value;
            continue;
        }

        if (is_string($value) && $value === '') {
            unset($data[$key]);
        }
    }

    return $data;
}
