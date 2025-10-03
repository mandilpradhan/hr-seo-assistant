<?php
/**
 * Vehicle helper nodes for JSON-LD sourced from HRDF.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Collect Vehicle nodes for bikes associated with the trip HRDF payload.
 *
 * @param array<int, mixed> $vehicles
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>}
 */
function hr_sa_trip_vehicle_nodes_from_hrdf(array $vehicles, string $trip_url, string $product_id): array
{
    $nodes = [];
    $refs  = [];

    foreach ($vehicles as $index => $vehicle) {
        if (!is_array($vehicle)) {
            continue;
        }

        $name = isset($vehicle['name']) ? hr_sa_hrdf_normalize_text($vehicle['name']) : '';
        if ($name === '') {
            continue;
        }

        $vehicle_id = '';
        if ($trip_url !== '') {
            $vehicle_id = rtrim($trip_url, '/') . '#vehicle-' . ((int) $index + 1);
        } elseif ($product_id !== '') {
            $vehicle_id = $product_id . '-vehicle-' . ((int) $index + 1);
        }

        $node = [
            '@type' => 'Vehicle',
            'name'  => $name,
        ];

        if ($vehicle_id !== '') {
            $node['@id'] = $vehicle_id;
            $refs[]      = ['@id' => $vehicle_id];
        }

        $description = isset($vehicle['description']) ? hr_sa_hrdf_normalize_text($vehicle['description']) : '';
        if ($description !== '') {
            $node['description'] = $description;
        }

        $image = isset($vehicle['image']) ? hr_sa_hrdf_normalize_url($vehicle['image']) : '';
        if ($image !== '') {
            $node['image'] = $image;
        }

        $offer_nodes = [];
        if (!empty($vehicle['offers']) && is_array($vehicle['offers'])) {
            foreach ($vehicle['offers'] as $offer_index => $offer) {
                if (!is_array($offer)) {
                    continue;
                }

                $normalized = hr_sa_trip_normalize_offer($offer, $vehicle_id ?: $product_id, $trip_url, (int) $offer_index);
                if ($normalized === null) {
                    continue;
                }

                $offer_nodes[] = $normalized['node'];
            }
        }

        if ($offer_nodes) {
            $node['offers'] = $offer_nodes;
        }

        $nodes[] = hr_sa_trip_filter_schema_array($node);
    }

    return [$nodes, $refs];
}
