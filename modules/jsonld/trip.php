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
function hr_sa_trip_emit_nodes(int $post_id = 0): array
{
    if (!is_singular('trip')) {
        return [];
    }

    $trip_id = $post_id > 0 ? $post_id : get_queried_object_id();
    if (!$trip_id) {
        return [];
    }

    $site_profile  = hr_sa_resolve_site_profile();
    $meta_profile  = hr_sa_resolve_meta_profile($trip_id);
    $image_profile = hr_sa_resolve_image_profile($trip_id);
    $document      = hr_sa_hrdf_document($trip_id);

    $site_url = $site_profile['url'] ?? trailingslashit((string) home_url('/'));
    $org_id   = rtrim($site_url, '/') . '#org';

    $canonical   = $meta_profile['canonical_url'] ?? (get_permalink($trip_id) ?: $site_url);
    $trip_node   = hr_sa_trip_build_product_node($trip_id, $canonical, $meta_profile, $image_profile, $document, $org_id);
    if (!$trip_node) {
        return [];
    }

    $graph    = [$trip_node];
    $has_part = [];

    $itinerary_nodes = hr_sa_trip_build_itinerary_nodes($canonical, $document, $trip_id);
    if ($itinerary_nodes) {
        $graph[]   = $itinerary_nodes['node'];
        $has_part[] = ['@id' => $itinerary_nodes['node']['@id']];
    }

    $faq_nodes = hr_sa_trip_build_faq_nodes($canonical, $document, $trip_id);
    if ($faq_nodes) {
        $graph[]   = $faq_nodes['node'];
        $has_part[] = ['@id' => $faq_nodes['node']['@id']];
    }

    $review_nodes = hr_sa_trip_build_review_nodes($canonical, $document, $trip_id);
    if ($review_nodes) {
        foreach ($review_nodes['reviews'] ?? [] as $review_node) {
            $graph[] = $review_node;
        }
        if (isset($review_nodes['aggregateRating'])) {
            $trip_node['aggregateRating'] = $review_nodes['aggregateRating'];
        }
        if (!empty($review_nodes['reviews'])) {
            $trip_node['review'] = $review_nodes['reviews'];
        }
    }

    $bike_nodes = hr_sa_trip_build_bike_nodes($canonical, $document, $org_id, $trip_id);
    if ($bike_nodes) {
        foreach ($bike_nodes['vehicles'] as $vehicle_node) {
            $graph[] = $vehicle_node;
            $has_part[] = ['@id' => $vehicle_node['@id']];
        }
        if ($bike_nodes['offers']) {
            $existing_offers = $trip_node['offers'] ?? [];
            if (!is_array($existing_offers)) {
                $existing_offers = [$existing_offers];
            }
            $trip_node['offers'] = array_values(array_merge($existing_offers, $bike_nodes['offers']));
        }
    }

    $stopover_node = hr_sa_trip_build_stopovers_node($canonical, $document, $trip_id);
    if ($stopover_node) {
        $graph[]   = $stopover_node;
        $has_part[] = ['@id' => $stopover_node['@id']];
    }

    $guide_nodes = hr_sa_trip_build_guide_nodes($canonical, $document, $trip_id);
    if ($guide_nodes) {
        foreach ($guide_nodes as $guide_node) {
            $graph[] = $guide_node;
            $has_part[] = ['@id' => $guide_node['@id']];
        }
    }

    if ($has_part) {
        $trip_node['hasPart'] = $has_part;
    }

    $graph[0] = $trip_node;

    return $graph;
}

/**
 * Build the main Product/TouristTrip node.
 *
 * @return array<string, mixed>|null
 */
function hr_sa_trip_build_product_node(
    int $post_id,
    string $canonical,
    array $meta_profile,
    array $image_profile,
    array $document,
    string $org_id
): ?array {
    $title       = $meta_profile['title'] ?? '';
    $description = $meta_profile['description'] ?? '';
    $images      = array_slice(array_filter($image_profile['all'] ?? []), 0, 8);

    $trip_node = [
        '@type' => ['Product', 'TouristTrip'],
        '@id'   => rtrim($canonical, '/') . '#trip',
        'url'   => $canonical,
        'name'  => $title,
        'brand' => [
            '@id' => $org_id,
        ],
    ];

    if ($description !== '') {
        $trip_node['description'] = $description;
    }

    if ($images) {
        $trip_node['image'] = $images;
    }

    $primary_offer = hr_sa_trip_prepare_primary_offer($document, $post_id);
    if ($primary_offer) {
        $trip_node['offers'] = [$primary_offer];
    }

    $properties = hr_sa_trip_prepare_property_values($document, $post_id);
    if ($properties) {
        $trip_node['additionalProperty'] = $properties;
    }

    return $trip_node;
}

/**
 * Prepare the primary offer from HRDF.
 *
 * @return array<string, mixed>|null
 */
function hr_sa_trip_prepare_primary_offer(array $document, int $post_id): ?array
{
    $offer = $document['offer']['primary'] ?? hr_sa_hrdf_get('hrdf.offer.primary', $post_id, []);
    if (!is_array($offer) || !$offer) {
        return null;
    }

    $schema_offer = ['@type' => 'Offer'];

    foreach (['price', 'priceCurrency', 'availability', 'url', 'priceValidUntil'] as $field) {
        if (!empty($offer[$field]) && is_string($offer[$field])) {
            $value = $field === 'url' ? hr_sa_normalize_url($offer[$field]) : $offer[$field];
            if ($value) {
                $schema_offer[$field] = $value;
            }
        }
    }

    if (!empty($offer['price']) && is_numeric($offer['price'])) {
        $schema_offer['price'] = (string) $offer['price'];
    }

    if (!empty($offer['availabilityStarts']) && is_string($offer['availabilityStarts'])) {
        $schema_offer['availabilityStarts'] = $offer['availabilityStarts'];
    }

    if (!empty($offer['availabilityEnds']) && is_string($offer['availabilityEnds'])) {
        $schema_offer['availabilityEnds'] = $offer['availabilityEnds'];
    }

    $availability_from_dates = hr_sa_trip_extract_availability($document, $post_id);
    $schema_offer = array_merge($availability_from_dates, $schema_offer);

    return count($schema_offer) > 1 ? $schema_offer : null;
}

/**
 * Extract availability timing from hrdf.trip.dates[] when possible.
 *
 * @return array<string, string>
 */
function hr_sa_trip_extract_availability(array $document, int $post_id): array
{
    $dates = $document['trip']['dates'] ?? hr_sa_hrdf_get('hrdf.trip.dates', $post_id, []);
    if (!is_array($dates) || !$dates) {
        return [];
    }

    $first = $dates[0];
    if (!is_array($first)) {
        return [];
    }

    $availability = [];
    if (!empty($first['start']) && is_string($first['start'])) {
        $availability['availabilityStarts'] = $first['start'];
    }
    if (!empty($first['end']) && is_string($first['end'])) {
        $availability['availabilityEnds'] = $first['end'];
    }

    return $availability;
}

/**
 * Prepare PropertyValue entries from hrdf.trip.properties[].
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_prepare_property_values(array $document, int $post_id): array
{
    $properties = $document['trip']['properties'] ?? hr_sa_hrdf_get('hrdf.trip.properties', $post_id, []);
    if (!is_array($properties)) {
        return [];
    }

    $items = [];
    foreach ($properties as $property) {
        if (!is_array($property)) {
            continue;
        }

        $name  = isset($property['name']) && is_string($property['name']) ? hr_sa_sanitize_text_value($property['name']) : '';
        $value = isset($property['value']) && is_string($property['value']) ? hr_sa_sanitize_text_value($property['value']) : '';

        if ($name === '' || $value === '') {
            continue;
        }

        $item = [
            '@type' => 'PropertyValue',
            'name'  => $name,
            'value' => $value,
        ];

        if (!empty($property['valueReferenceUrl']) && is_string($property['valueReferenceUrl'])) {
            $reference = hr_sa_normalize_url($property['valueReferenceUrl']);
            if ($reference) {
                $item['valueReference'] = [
                    '@type' => 'WebPage',
                    'url'   => $reference,
                ];
            }
        }

        $items[] = $item;
    }

    return $items;
}

/**
 * Build itinerary ItemList node when HRDF data is available.
 *
 * @return array{node: array<string, mixed>}|null
 */
function hr_sa_trip_build_itinerary_nodes(string $canonical, array $document, int $post_id): ?array
{
    $items = $document['itinerary']['items'] ?? hr_sa_hrdf_get('hrdf.itinerary.items', $post_id, []);
    if (!is_array($items) || !$items) {
        return null;
    }

    $list_id = rtrim($canonical, '/') . '#itinerary';

    $list_items = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = isset($item['name']) && is_string($item['name']) ? hr_sa_sanitize_text_value($item['name']) : '';
        $description = isset($item['description']) && is_string($item['description']) ? $item['description'] : '';
        $url = isset($item['url']) && is_string($item['url']) ? hr_sa_normalize_url($item['url']) : null;

        if ($name === '') {
            continue;
        }

        $entry = [
            '@type'    => 'ListItem',
            'position' => $index + 1,
            'name'     => $name,
        ];

        if ($description !== '') {
            $entry['description'] = $description;
        }

        if ($url) {
            $entry['url'] = $url;
        }

        $list_items[] = $entry;
    }

    if (!$list_items) {
        return null;
    }

    $node = [
        '@type'           => 'ItemList',
        '@id'             => $list_id,
        'itemListElement' => $list_items,
    ];

    $itinerary_url = $document['itinerary']['url'] ?? hr_sa_hrdf_get('hrdf.itinerary.url', $post_id, '');
    if (is_string($itinerary_url)) {
        $normalized = hr_sa_normalize_url($itinerary_url);
        if ($normalized) {
            $node['url'] = $normalized;
        }
    }

    return ['node' => $node];
}

/**
 * Build FAQPage node when hrdf.faq.items[] is present.
 *
 * @return array{node: array<string, mixed>}|null
 */
function hr_sa_trip_build_faq_nodes(string $canonical, array $document, int $post_id): ?array
{
    $items = $document['faq']['items'] ?? hr_sa_hrdf_get('hrdf.faq.items', $post_id, []);
    if (!is_array($items) || !$items) {
        return null;
    }

    $faq_id = rtrim($canonical, '/') . '#faqs';

    $questions = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $question = isset($item['question']) && is_string($item['question']) ? hr_sa_sanitize_text_value($item['question']) : '';
        $answer   = isset($item['answer']) && is_string($item['answer']) ? $item['answer'] : '';

        if ($question === '' || $answer === '') {
            continue;
        }

        $questions[] = [
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $answer,
            ],
        ];
    }

    if (!$questions) {
        return null;
    }

    $node = [
        '@type'      => 'FAQPage',
        '@id'        => $faq_id,
        'mainEntity' => $questions,
    ];

    return ['node' => $node];
}

/**
 * Build review nodes when hrdf.reviews[] is provided.
 *
 * @return array{reviews: array<int, array<string, mixed>>, aggregateRating?: array<string, mixed>}|null
 */
function hr_sa_trip_build_review_nodes(string $canonical, array $document, int $post_id): ?array
{
    $reviews = $document['reviews'] ?? hr_sa_hrdf_get('hrdf.reviews', $post_id, []);
    $aggregate = $document['aggregate_rating'] ?? hr_sa_hrdf_get('hrdf.aggregate_rating', $post_id, []);

    $review_nodes = [];
    if (is_array($reviews)) {
        foreach ($reviews as $index => $review) {
            if (!is_array($review)) {
                continue;
            }

            $author = isset($review['author']) && is_string($review['author']) ? hr_sa_sanitize_text_value($review['author']) : '';
            $body   = isset($review['body']) && is_string($review['body']) ? $review['body'] : '';

            if ($author === '' && $body === '') {
                continue;
            }

            $node = [
                '@type' => 'Review',
                '@id'   => rtrim($canonical, '/') . '#review-' . ($index + 1),
            ];

            if ($author !== '') {
                $node['author'] = [
                    '@type' => 'Person',
                    'name'  => $author,
                ];
            }

            if (!empty($review['datePublished']) && is_string($review['datePublished'])) {
                $node['datePublished'] = $review['datePublished'];
            }

            if ($body !== '') {
                $node['reviewBody'] = $body;
            }

            if (!empty($review['rating']) && is_array($review['rating'])) {
                $rating = [];
                if (isset($review['rating']['ratingValue']) && $review['rating']['ratingValue'] !== '') {
                    $rating['ratingValue'] = $review['rating']['ratingValue'];
                }
                if (isset($review['rating']['bestRating']) && $review['rating']['bestRating'] !== '') {
                    $rating['bestRating'] = $review['rating']['bestRating'];
                }
                if (isset($review['rating']['worstRating']) && $review['rating']['worstRating'] !== '') {
                    $rating['worstRating'] = $review['rating']['worstRating'];
                }
                if ($rating) {
                    $rating['@type'] = 'Rating';
                    $node['reviewRating'] = $rating;
                }
            }

            if (count($node) > 1) {
                $review_nodes[] = $node;
            }
        }
    }

    $result = [];
    if ($review_nodes) {
        $result['reviews'] = $review_nodes;
    }

    if (is_array($aggregate) && $aggregate) {
        $rating = ['@type' => 'AggregateRating'];
        foreach (['ratingValue', 'reviewCount', 'bestRating', 'worstRating'] as $field) {
            if (isset($aggregate[$field]) && $aggregate[$field] !== '') {
                $rating[$field] = $aggregate[$field];
            }
        }
        if (count($rating) > 1) {
            $result['aggregateRating'] = $rating;
        }
    }

    return $result ? $result : null;
}

/**
 * Build Vehicle nodes and corresponding offers from hrdf.bikes.* blocks.
 *
 * @return array{vehicles: array<int, array<string, mixed>>, offers: array<int, array<string, mixed>>}|null
 */
function hr_sa_trip_build_bike_nodes(string $canonical, array $document, string $org_id, int $post_id): ?array
{
    $bike_list   = $document['bikes']['list'] ?? hr_sa_hrdf_get('hrdf.bikes.list', $post_id, []);
    $bike_offers = $document['bikes']['offers'] ?? hr_sa_hrdf_get('hrdf.bikes.offers', $post_id, []);

    if (!is_array($bike_list) || !is_array($bike_offers) || !$bike_list || !$bike_offers) {
        return null;
    }

    $vehicles = [];
    $vehicle_index = [];

    foreach ($bike_list as $index => $bike) {
        if (!is_array($bike)) {
            continue;
        }

        $bike_id = isset($bike['id']) ? (string) $bike['id'] : (string) ($index + 1);
        if ($bike_id === '') {
            continue;
        }

        $node_id = rtrim($canonical, '/') . '#bike-' . sanitize_title($bike_id);
        $vehicle = [
            '@type' => 'Vehicle',
            '@id'   => $node_id,
        ];

        if (!empty($bike['name']) && is_string($bike['name'])) {
            $vehicle['name'] = hr_sa_sanitize_text_value($bike['name']);
        }

        if (!empty($bike['description']) && is_string($bike['description'])) {
            $vehicle['description'] = $bike['description'];
        }

        if (!empty($bike['image']) && is_string($bike['image'])) {
            $image = hr_sa_normalize_url($bike['image']);
            if ($image) {
                $vehicle['image'] = $image;
            }
        }

        if (!empty($bike['url']) && is_string($bike['url'])) {
            $url = hr_sa_normalize_url($bike['url']);
            if ($url) {
                $vehicle['url'] = $url;
            }
        }

        $vehicles[] = $vehicle;
        $vehicle_index[$bike_id] = $node_id;
    }

    if (!$vehicles) {
        return null;
    }

    $offers = [];
    foreach ($bike_offers as $offer) {
        if (!is_array($offer)) {
            continue;
        }

        $vehicle_key = isset($offer['vehicle_id']) ? (string) $offer['vehicle_id'] : '';
        if ($vehicle_key === '' || !isset($vehicle_index[$vehicle_key])) {
            continue;
        }

        $schema_offer = ['@type' => 'Offer'];

        foreach (['price', 'priceCurrency', 'availability', 'url'] as $field) {
            if (!empty($offer[$field]) && is_string($offer[$field])) {
                $value = $field === 'url' ? hr_sa_normalize_url($offer[$field]) : $offer[$field];
                if ($value) {
                    $schema_offer[$field] = $value;
                }
            }
        }

        if (!empty($offer['price']) && is_numeric($offer['price'])) {
            $schema_offer['price'] = (string) $offer['price'];
        }

        $schema_offer['itemOffered'] = [
            '@id' => $vehicle_index[$vehicle_key],
        ];

        $schema_offer['seller'] = [
            '@type' => 'Organization',
            '@id'   => $org_id,
        ];

        if (count($schema_offer) > 2) {
            $offers[] = $schema_offer;
        }
    }

    return [
        'vehicles' => $vehicles,
        'offers'   => $offers,
    ];
}

/**
 * Build ItemList for stopovers when provided by HRDF.
 *
 * @return array<string, mixed>|null
 */
function hr_sa_trip_build_stopovers_node(string $canonical, array $document, int $post_id): ?array
{
    $stopovers = $document['stopovers']['list'] ?? hr_sa_hrdf_get('hrdf.stopovers.list', $post_id, []);
    if (!is_array($stopovers) || !$stopovers) {
        return null;
    }

    $list = [];
    foreach ($stopovers as $index => $stopover) {
        if (!is_array($stopover)) {
            continue;
        }

        $name = isset($stopover['name']) && is_string($stopover['name']) ? hr_sa_sanitize_text_value($stopover['name']) : '';
        if ($name === '') {
            continue;
        }

        $entry = [
            '@type'    => 'ListItem',
            'position' => $index + 1,
            'name'     => $name,
        ];

        if (!empty($stopover['description']) && is_string($stopover['description'])) {
            $entry['description'] = $stopover['description'];
        }

        if (!empty($stopover['url']) && is_string($stopover['url'])) {
            $url = hr_sa_normalize_url($stopover['url']);
            if ($url) {
                $entry['url'] = $url;
            }
        }

        $list[] = $entry;
    }

    if (!$list) {
        return null;
    }

    return [
        '@type'           => 'ItemList',
        '@id'             => rtrim($canonical, '/') . '#stopovers',
        'itemListElement' => $list,
    ];
}

/**
 * Build Person nodes for trip guides when provided by HRDF.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_build_guide_nodes(string $canonical, array $document, int $post_id): array
{
    $guides = $document['guides']['list'] ?? hr_sa_hrdf_get('hrdf.guides.list', $post_id, []);
    if (!is_array($guides) || !$guides) {
        return [];
    }

    $nodes = [];
    foreach ($guides as $index => $guide) {
        if (!is_array($guide)) {
            continue;
        }

        $name = isset($guide['name']) && is_string($guide['name']) ? hr_sa_sanitize_text_value($guide['name']) : '';
        if ($name === '') {
            continue;
        }

        $node = [
            '@type' => 'Person',
            '@id'   => rtrim($canonical, '/') . '#guide-' . ($index + 1),
            'name'  => $name,
        ];

        if (!empty($guide['description']) && is_string($guide['description'])) {
            $node['description'] = $guide['description'];
        }

        if (!empty($guide['image']) && is_string($guide['image'])) {
            $image = hr_sa_normalize_url($guide['image']);
            if ($image) {
                $node['image'] = $image;
            }
        }

        if (!empty($guide['url']) && is_string($guide['url'])) {
            $url = hr_sa_normalize_url($guide['url']);
            if ($url) {
                $node['url'] = $url;
            }
        }

        if (!empty($guide['sameAs']) && is_array($guide['sameAs'])) {
            $node['sameAs'] = hr_sa_jsonld_collect_urls($guide['sameAs']);
        }

        $nodes[] = $node;
    }

    return $nodes;
}
