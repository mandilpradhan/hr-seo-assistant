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
 * Build an ItemList node representing the itinerary using HRDF data.
 *
 * @return array{0: array<string, mixed>|null, 1: array<string, string>|null}
 */
function hr_sa_trip_itinerary_node(int $trip_id, string $product_id): array
{
    $itinerary = hr_sa_hrdf_get('trip.itinerary', $trip_id);
    $raw_steps = [];

    if (is_array($itinerary) && isset($itinerary['steps']) && is_array($itinerary['steps'])) {
        $raw_steps = $itinerary['steps'];
    } else {
        $raw_steps = hr_sa_hrdf_get_array('trip.itinerary.steps', $trip_id);
    }

    $elements = [];
    $position = 1;

    foreach ($raw_steps as $step) {
        if (!is_array($step)) {
            continue;
        }

        $name = isset($step['name']) ? hr_sa_jsonld_clean_text($step['name']) : '';
        if ($name === '') {
            continue;
        }

        $item = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $name,
        ];

        if (!empty($step['description'])) {
            $description = hr_sa_jsonld_clean_text($step['description'], 80);
            if ($description !== '') {
                $item['description'] = $description;
            }
        }

        $start = hr_sa_jsonld_normalize_iso8601($step['startDate'] ?? '');
        if ($start !== '') {
            $item['startDate'] = $start;
        }

        $end = hr_sa_jsonld_normalize_iso8601($step['endDate'] ?? '');
        if ($end !== '') {
            $item['endDate'] = $end;
        }

        $elements[] = $item;
    }

    if (!$elements) {
        return [null, null];
    }

    $itinerary_url = hr_sa_jsonld_normalize_url(is_array($itinerary) ? ($itinerary['url'] ?? '') : '');
    if ($itinerary_url === '') {
        $fallback_url = hr_sa_hrdf_get('trip.itinerary.url', $trip_id);
        $itinerary_url = hr_sa_jsonld_normalize_url($fallback_url);
    }

    if ($itinerary_url === '') {
        $itinerary_url = trailingslashit($product_id) . 'itinerary';
    }

    $name = 'Itinerary';
    if (is_array($itinerary) && !empty($itinerary['name']) && is_string($itinerary['name'])) {
        $name = $itinerary['name'];
    }

    $node = [
        '@type'           => 'ItemList',
        '@id'             => $itinerary_url,
        'name'            => $name,
        'itemListElement' => $elements,
    ];

    if (is_array($itinerary) && !empty($itinerary['description']) && is_string($itinerary['description'])) {
        $node['description'] = hr_sa_jsonld_clean_text($itinerary['description'], 80);
    }

    if (is_array($itinerary) && !empty($itinerary['itemListOrder']) && is_string($itinerary['itemListOrder'])) {
        $node['itemListOrder'] = $itinerary['itemListOrder'];
    }

    return [$node, ['@id' => $itinerary_url]];
}
