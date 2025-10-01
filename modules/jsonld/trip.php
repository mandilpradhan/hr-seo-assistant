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

add_filter('hr_sa_get_context', 'hr_sa_trip_enrich_context', 10, 2);

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

    $document      = hr_sa_hrdf_document($trip_id);
    $image_profile = hr_sa_resolve_image_profile($trip_id);
    $site_profile  = hr_sa_resolve_site_profile();

    $trip_url_raw = hr_sa_hrdf_get_first([
        'hrdf.trip.url',
        'hrdf.webpage.url',
        'hrdf.meta.canonical_url',
    ], $trip_id, '');
    $trip_url = is_string($trip_url_raw) ? hr_sa_normalize_url($trip_url_raw) : null;
    if ($trip_url === null) {
        return [];
    }

    $title_raw = hr_sa_hrdf_get_first([
        'hrdf.trip.title',
        'hrdf.webpage.title',
        'hrdf.meta.title',
    ], $trip_id, '');
    $title = is_string($title_raw) ? hr_sa_sanitize_text_value($title_raw) : '';
    if ($title === '') {
        return [];
    }

    $description_raw = hr_sa_hrdf_get_first([
        'hrdf.trip.description',
        'hrdf.webpage.description',
        'hrdf.meta.description',
    ], $trip_id, '');
    $description = is_string($description_raw) ? hr_sa_sanitize_description($description_raw) : '';

    $org_url = isset($site_profile['url']) && is_string($site_profile['url'])
        ? (string) $site_profile['url']
        : '';
    $org_id = $org_url !== '' ? rtrim($org_url, '/') . '#org' : '';

    $trip_node = hr_sa_trip_build_product_node(
        $trip_id,
        $trip_url,
        $title,
        $description,
        $image_profile,
        $document,
        $org_id
    );

    if ($trip_node === null) {
        return [];
    }

    $graph    = [$trip_node];
    $has_part = [];

    $itinerary_nodes = hr_sa_trip_build_itinerary_nodes($trip_url, $document, $trip_id);
    if ($itinerary_nodes) {
        $graph[]    = $itinerary_nodes['node'];
        $has_part[] = ['@id' => $itinerary_nodes['node']['@id']];
    }

    $faq_nodes = hr_sa_trip_build_faq_nodes($trip_url, $document, $trip_id);
    if ($faq_nodes) {
        $graph[]    = $faq_nodes['node'];
        $has_part[] = ['@id' => $faq_nodes['node']['@id']];
    }

    $review_nodes = hr_sa_trip_build_review_nodes($trip_url, $document, $trip_id);
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

    $bike_nodes = hr_sa_trip_build_bike_nodes($trip_url, $document, $org_id, $trip_id);
    if ($bike_nodes) {
        foreach ($bike_nodes['vehicles'] as $vehicle_node) {
            $graph[]    = $vehicle_node;
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

    $stopover_node = hr_sa_trip_build_stopovers_node($trip_url, $document, $trip_id);
    if ($stopover_node) {
        $graph[]    = $stopover_node;
        $has_part[] = ['@id' => $stopover_node['@id']];
    }

    $guide_nodes = hr_sa_trip_build_guide_nodes($trip_url, $document, $trip_id);
    if ($guide_nodes) {
        foreach ($guide_nodes as $guide_node) {
            $graph[]    = $guide_node;
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
    string $trip_url,
    string $title,
    string $description,
    array $image_profile,
    array $document,
    string $org_id
): ?array {
    if ($trip_url === '' || $title === '') {
        return null;
    }

    $images = array_filter($image_profile['all'] ?? [], 'is_string');
    $images = array_slice(array_values($images), 0, 8);

    $trip_node = [
        '@type' => ['Product', 'TouristTrip'],
        '@id'   => rtrim($trip_url, '/') . '#trip',
        'url'   => $trip_url,
        'name'  => $title,
    ];

    if ($org_id !== '') {
        $trip_node['brand'] = [
            '@id' => $org_id,
        ];
    }

    if ($description !== '') {
        $trip_node['description'] = $description;
    }

    if ($images) {
        $trip_node['image'] = $images;
    }

    $offers = hr_sa_trip_collect_offers($document, $post_id);
    if ($offers) {
        $trip_node['offers'] = $offers;
    }

    $aggregate_offer = hr_sa_trip_collect_aggregate_offer($document, $post_id);
    if ($aggregate_offer) {
        $trip_node['aggregateOffer'] = $aggregate_offer;
    }

    $properties = hr_sa_trip_prepare_property_values($document, $post_id);
    if ($properties) {
        $trip_node['additionalProperty'] = $properties;
    }

    return $trip_node;
}

/**
 * Collect Offer nodes from HRDF data.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_collect_offers(array $document, int $post_id): array
{
    $offers = [];

    $document_offers = hr_sa_trip_document_path($document, ['trip', 'offers']);
    if (is_array($document_offers)) {
        foreach ($document_offers as $raw_offer) {
            if (!is_array($raw_offer)) {
                continue;
            }

            $normalized = hr_sa_trip_normalize_offer($raw_offer);
            if ($normalized) {
                $offers[] = $normalized;
            }
        }
    }

    $hrdf_offers = hr_sa_hrdf_get_first([
        'hrdf.trip.offers',
    ], $post_id, []);
    if (is_array($hrdf_offers)) {
        foreach ($hrdf_offers as $raw_offer) {
            if (!is_array($raw_offer)) {
                continue;
            }

            $normalized = hr_sa_trip_normalize_offer($raw_offer);
            if ($normalized) {
                $offers[] = $normalized;
            }
        }
    }

    $primary_offer = hr_sa_hrdf_get_first([
        'hrdf.offer.primary',
    ], $post_id, []);
    if (is_array($primary_offer) && $primary_offer) {
        $normalized = hr_sa_trip_normalize_offer($primary_offer);
        if ($normalized) {
            $offers[] = $normalized;
        }
    }

    return array_values($offers);
}

/**
 * Normalize a single HRDF offer payload.
 */
function hr_sa_trip_normalize_offer(array $raw): ?array
{
    $offer = ['@type' => 'Offer'];

    if (!empty($raw['name']) && is_string($raw['name'])) {
        $name = hr_sa_sanitize_text_value($raw['name']);
        if ($name !== '') {
            $offer['name'] = $name;
        }
    }

    $price_amount = null;
    $price_currency = null;

    if (isset($raw['price']) && is_array($raw['price'])) {
        if (isset($raw['price']['amount']) && $raw['price']['amount'] !== '') {
            $price_amount = (string) $raw['price']['amount'];
        }
        if (isset($raw['price']['currency']) && $raw['price']['currency'] !== '') {
            $price_currency = (string) $raw['price']['currency'];
        }
    } elseif (isset($raw['price']) && $raw['price'] !== '') {
        $price_amount = (string) $raw['price'];
    }

    if (isset($raw['priceAmount']) && $raw['priceAmount'] !== '') {
        $price_amount = (string) $raw['priceAmount'];
    }

    if (isset($raw['priceCurrency']) && $raw['priceCurrency'] !== '') {
        $price_currency = (string) $raw['priceCurrency'];
    }

    if ($price_amount !== null) {
        $offer['price'] = $price_amount;
    }

    if ($price_currency !== null) {
        $offer['priceCurrency'] = $price_currency;
    }

    if (!empty($raw['availability']) && is_string($raw['availability'])) {
        $offer['availability'] = $raw['availability'];
    }

    if (!empty($raw['inventoryRemaining']) && is_numeric($raw['inventoryRemaining'])) {
        $offer['inventoryLevel'] = [
            '@type' => 'QuantitativeValue',
            'value' => (int) $raw['inventoryRemaining'],
        ];
    } elseif (!empty($raw['inventory_remaining']) && is_numeric($raw['inventory_remaining'])) {
        $offer['inventoryLevel'] = [
            '@type' => 'QuantitativeValue',
            'value' => (int) $raw['inventory_remaining'],
        ];
    }

    if (!empty($raw['eligibleQuantity']) && is_numeric($raw['eligibleQuantity'])) {
        $offer['eligibleQuantity'] = [
            '@type' => 'QuantitativeValue',
            'value' => (int) $raw['eligibleQuantity'],
        ];
    } elseif (!empty($raw['eligible_quantity']) && is_numeric($raw['eligible_quantity'])) {
        $offer['eligibleQuantity'] = [
            '@type' => 'QuantitativeValue',
            'value' => (int) $raw['eligible_quantity'],
        ];
    }

    if (!empty($raw['priceValidFrom']) && is_string($raw['priceValidFrom'])) {
        $offer['priceValidFrom'] = $raw['priceValidFrom'];
    } elseif (!empty($raw['valid_from']) && is_string($raw['valid_from'])) {
        $offer['priceValidFrom'] = $raw['valid_from'];
    }

    if (!empty($raw['priceValidUntil']) && is_string($raw['priceValidUntil'])) {
        $offer['priceValidUntil'] = $raw['priceValidUntil'];
    } elseif (!empty($raw['valid_until']) && is_string($raw['valid_until'])) {
        $offer['priceValidUntil'] = $raw['valid_until'];
    }

    if (!empty($raw['availabilityEnds']) && is_string($raw['availabilityEnds'])) {
        $offer['availabilityEnds'] = $raw['availabilityEnds'];
    }

    if (!empty($raw['validFrom']) && is_string($raw['validFrom'])) {
        $offer['validFrom'] = $raw['validFrom'];
    } elseif (!empty($raw['date']) && is_string($raw['date'])) {
        $offer['validFrom'] = $raw['date'];
    }

    if (!empty($raw['url']) && is_string($raw['url'])) {
        $url = hr_sa_normalize_url($raw['url']);
        if ($url) {
            $offer['url'] = $url;
        }
    }

    return count($offer) > 1 ? $offer : null;
}

/**
 * Normalize AggregateOffer data from HRDF when available.
 *
 * @return array<string, mixed>|null
 */
function hr_sa_trip_collect_aggregate_offer(array $document, int $post_id): ?array
{
    $aggregate = hr_sa_trip_document_path($document, ['trip', 'aggregateOffer']);
    if (!is_array($aggregate) || !$aggregate) {
        $aggregate = hr_sa_hrdf_get_first([
            'hrdf.trip.aggregateOffer',
        ], $post_id, []);
    }

    if (!is_array($aggregate) || !$aggregate) {
        return null;
    }

    $node = ['@type' => 'AggregateOffer'];

    foreach ([
        'lowPrice'      => ['lowPrice', 'low_price'],
        'highPrice'     => ['highPrice', 'high_price'],
        'priceCurrency' => ['priceCurrency', 'currency'],
        'offerCount'    => ['offerCount', 'offer_count'],
    ] as $target => $candidates) {
        foreach ($candidates as $candidate) {
            if (isset($aggregate[$candidate]) && $aggregate[$candidate] !== '') {
                $node[$target] = (string) $aggregate[$candidate];
                break;
            }
        }
    }

    return count($node) > 1 ? $node : null;
}

/**
 * Safely resolve nested document paths.
 *
 * @param array<int|string, mixed> $document
 * @param array<int, string>       $path
 * @return mixed|null
 */
function hr_sa_trip_document_path(array $document, array $path)
{
    $value = $document;
    foreach ($path as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * Enrich the shared SEO context with trip-specific HRDF data.
 *
 * @param array<string, mixed> $context
 * @param int                  $post_id
 * @return array<string, mixed>
 */
function hr_sa_trip_enrich_context(array $context, int $post_id): array
{
    if (($context['type'] ?? '') !== 'trip') {
        return $context;
    }

    $document = hr_sa_hrdf_document($post_id);

    $offers = hr_sa_trip_collect_offers($document, $post_id);
    if ($offers) {
        $context['offers'] = $offers;
    }

    $aggregate_offer = hr_sa_trip_collect_aggregate_offer($document, $post_id);
    if ($aggregate_offer) {
        $context['aggregate_offer'] = $aggregate_offer;
    }

    return $context;
}

/**
 * Prepare the primary offer from HRDF.
 *
 * @return array<string, mixed>|null
 */
/**
 * Prepare PropertyValue entries from hrdf.trip.properties[].
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_prepare_property_values(array $document, int $post_id): array
{
    $properties = hr_sa_trip_document_path($document, ['trip', 'additionalProperty']);
    if (!is_array($properties) || !$properties) {
        $properties = hr_sa_trip_document_path($document, ['trip', 'properties']);
    }
    if (!is_array($properties) || !$properties) {
        $properties = hr_sa_hrdf_get_first([
            'hrdf.trip.additionalProperty',
            'hrdf.trip.properties',
        ], $post_id, []);
    }
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
    $items = hr_sa_trip_document_path($document, ['trip', 'itinerary', 'steps']);
    if (!is_array($items) || !$items) {
        $items = hr_sa_trip_document_path($document, ['itinerary', 'items']);
    }
    if (!is_array($items) || !$items) {
        $items = hr_sa_hrdf_get_first([
            'hrdf.trip.itinerary.steps',
            'hrdf.itinerary.items',
        ], $post_id, []);
    }
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

    $itinerary_url = hr_sa_trip_document_path($document, ['trip', 'itinerary', 'url']);
    if (!is_string($itinerary_url) || $itinerary_url === '') {
        $itinerary_url = hr_sa_trip_document_path($document, ['itinerary', 'url']);
    }
    if (!is_string($itinerary_url) || $itinerary_url === '') {
        $itinerary_url = hr_sa_hrdf_get_first([
            'hrdf.trip.itinerary.url',
            'hrdf.itinerary.url',
        ], $post_id, '');
    }
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
    $items = hr_sa_trip_document_path($document, ['trip', 'faq', 'items']);
    if (!is_array($items) || !$items) {
        $items = hr_sa_trip_document_path($document, ['faq', 'items']);
    }
    if (!is_array($items) || !$items) {
        $items = hr_sa_hrdf_get_first([
            'hrdf.trip.faq.items',
            'hrdf.faq.items',
        ], $post_id, []);
    }
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
    $reviews = hr_sa_trip_document_path($document, ['trip', 'reviews']);
    if (!is_array($reviews) || !$reviews) {
        $reviews = hr_sa_trip_document_path($document, ['reviews']);
    }
    if (!is_array($reviews) || !$reviews) {
        $reviews = hr_sa_hrdf_get_first([
            'hrdf.trip.reviews',
            'hrdf.reviews',
        ], $post_id, []);
    }

    $aggregate = hr_sa_trip_document_path($document, ['trip', 'aggregateRating']);
    if (!is_array($aggregate) || !$aggregate) {
        $aggregate = hr_sa_trip_document_path($document, ['aggregate_rating']);
    }
    if (!is_array($aggregate) || !$aggregate) {
        $aggregate = hr_sa_hrdf_get_first([
            'hrdf.trip.aggregateRating',
            'hrdf.aggregate_rating',
        ], $post_id, []);
    }

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
    $vehicle_blocks = hr_sa_trip_document_path($document, ['trip', 'vehicles']);
    if (!is_array($vehicle_blocks) || !$vehicle_blocks) {
        $vehicle_blocks = hr_sa_trip_document_path($document, ['bikes', 'list']);
    }
    if (!is_array($vehicle_blocks) || !$vehicle_blocks) {
        $vehicle_blocks = hr_sa_hrdf_get_first([
            'hrdf.trip.vehicles',
            'hrdf.bikes.list',
        ], $post_id, []);
    }

    if (!is_array($vehicle_blocks) || !$vehicle_blocks) {
        return null;
    }

    $vehicles = [];
    $vehicle_index = [];
    $offers = [];

    foreach ($vehicle_blocks as $index => $bike) {
        if (!is_array($bike)) {
            continue;
        }

        $bike_id = isset($bike['id']) ? (string) $bike['id'] : (string) ($index + 1);
        if ($bike_id === '') {
            continue;
        }

        $node_id = rtrim($canonical, '/') . '#vehicle-' . sanitize_title($bike_id);
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

        if (!empty($bike['offers']) && is_array($bike['offers'])) {
            foreach ($bike['offers'] as $raw_offer) {
                if (!is_array($raw_offer)) {
                    continue;
                }

                $normalized = hr_sa_trip_normalize_offer($raw_offer);
                if ($normalized === null) {
                    continue;
                }

                $normalized['itemOffered'] = ['@id' => $node_id];
                if ($org_id !== '') {
                    $normalized['seller'] = [
                        '@type' => 'Organization',
                        '@id'   => $org_id,
                    ];
                }

                $offers[] = $normalized;
            }
        }
    }

    if (!$vehicles) {
        return null;
    }

    $bike_offers = hr_sa_trip_document_path($document, ['bikes', 'offers']);
    if (!is_array($bike_offers) || !$bike_offers) {
        $bike_offers = hr_sa_hrdf_get_first([
            'hrdf.bikes.offers',
        ], $post_id, []);
    }

    if (is_array($bike_offers)) {
        foreach ($bike_offers as $offer) {
            if (!is_array($offer)) {
                continue;
            }

            $vehicle_key = isset($offer['vehicle_id']) ? (string) $offer['vehicle_id'] : '';
            if ($vehicle_key === '' || !isset($vehicle_index[$vehicle_key])) {
                continue;
            }

            $normalized = hr_sa_trip_normalize_offer($offer);
            if ($normalized === null) {
                continue;
            }

            $normalized['itemOffered'] = [
                '@id' => $vehicle_index[$vehicle_key],
            ];

            if ($org_id !== '') {
                $normalized['seller'] = [
                    '@type' => 'Organization',
                    '@id'   => $org_id,
                ];
            }

            $offers[] = $normalized;
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
    $stopovers = hr_sa_trip_document_path($document, ['trip', 'stopovers']);
    if (!is_array($stopovers) || !$stopovers) {
        $stopovers = hr_sa_trip_document_path($document, ['stopovers', 'list']);
    }
    if (!is_array($stopovers) || !$stopovers) {
        $stopovers = hr_sa_hrdf_get_first([
            'hrdf.trip.stopovers',
            'hrdf.stopovers.list',
        ], $post_id, []);
    }
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
    $guides = hr_sa_trip_document_path($document, ['trip', 'guides']);
    if (!is_array($guides) || !$guides) {
        $guides = hr_sa_trip_document_path($document, ['guides', 'list']);
    }
    if (!is_array($guides) || !$guides) {
        $guides = hr_sa_hrdf_get_first([
            'hrdf.trip.guides',
            'hrdf.guides.list',
        ], $post_id, []);
    }
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
