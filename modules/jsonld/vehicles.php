<?php
/**
 * Vehicle helper nodes for JSON-LD.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Collect Vehicle nodes for a trip using HRDF data.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>}
 */
function hr_sa_trip_bike_nodes(int $trip_id): array
{
    $nodes   = [];
    $about   = [];
    $vehicles = hr_sa_hrdf_get_array('trip.vehicles', $trip_id);

    foreach ($vehicles as $vehicle) {
        if (!is_array($vehicle)) {
            continue;
        }

        $id = hr_sa_jsonld_normalize_url($vehicle['@id'] ?? ($vehicle['id'] ?? ($vehicle['url'] ?? '')));
        if ($id === '') {
            continue;
        }

        $node = [
            '@type' => 'Vehicle',
            '@id'   => $id,
        ];

        $name = $vehicle['name'] ?? '';
        if (is_string($name) && $name !== '') {
            $node['name'] = $name;
        }

        $url = hr_sa_jsonld_normalize_url($vehicle['url'] ?? '');
        if ($url !== '') {
            $node['url'] = $url;
        }

        $image = hr_sa_jsonld_normalize_url($vehicle['image'] ?? '');
        if ($image !== '') {
            $node['image'] = $image;
        } elseif (!empty($vehicle['images']) && is_array($vehicle['images'])) {
            $images = array_values(array_filter(array_map('hr_sa_jsonld_normalize_url', $vehicle['images'])));
            if ($images) {
                $node['image'] = $images;
            }
        }

        $description = $vehicle['description'] ?? '';
        if (is_string($description) && $description !== '') {
            $node['description'] = hr_sa_jsonld_clean_text($description, 80);
        }

        if (!empty($vehicle['brand']) && is_array($vehicle['brand'])) {
            $node['brand'] = $vehicle['brand'];
        }

        if (!empty($vehicle['offers']) && is_array($vehicle['offers'])) {
            $offers = [];
            foreach ((array) $vehicle['offers'] as $offer_raw) {
                if (!is_array($offer_raw)) {
                    continue;
                }

                $offers[] = hr_sa_jsonld_prepare_offer($offer_raw, $url !== '' ? $url : $id);
            }
            $offers = array_values(array_filter($offers));
            if ($offers) {
                $node['offers'] = $offers;
            }
        }

        if (!empty($vehicle['additionalProperty']) && is_array($vehicle['additionalProperty'])) {
            $node['additionalProperty'] = $vehicle['additionalProperty'];
        }

        $node = hr_sa_jsonld_array_filter($node);
        if ($node) {
            $nodes[] = $node;
            $about[] = ['@id' => $node['@id']];
        }
    }

    return [$nodes, $about];
}
