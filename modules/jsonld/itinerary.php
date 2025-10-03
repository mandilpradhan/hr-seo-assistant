<?php
/**
 * Itinerary JSON-LD helpers driven by HRDF.
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
 * @param array<int, mixed> $steps
 * @return array{0: array<string, mixed>|null, 1: array<string, string>|null}
 */
function hr_sa_trip_itinerary_node_from_hrdf(array $steps, string $trip_url, string $product_id): array
{
    if (!$steps) {
        return [null, null];
    }

    $items    = [];
    $position = 1;

    foreach ($steps as $step) {
        if (count($items) >= 8) {
            break;
        }

        if (is_array($step)) {
            $label = hr_sa_hrdf_normalize_text($step['name'] ?? ($step['title'] ?? ''));
        } else {
            $label = hr_sa_hrdf_normalize_text($step);
        }

        if ($label === '') {
            continue;
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $label,
        ];
    }

    if (!$items) {
        return [null, null];
    }

    $list_id = '';
    if ($trip_url !== '') {
        $list_id = rtrim($trip_url, '/') . '#itinerary';
    } elseif ($product_id !== '') {
        $list_id = $product_id . '-itinerary';
    }

    $node = [
        '@type'           => 'ItemList',
        'name'            => __('Itinerary', HR_SA_TEXT_DOMAIN),
        'itemListElement' => $items,
    ];

    if ($list_id !== '') {
        $node['@id'] = $list_id;
    }

    return [$node, $list_id !== '' ? ['@id' => $list_id] : null];
}
