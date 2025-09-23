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
 * Collect Vehicle nodes for bikes associated with country terms.
 *
 * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<int, array<string, string>>}
 */
function hr_sa_trip_bike_nodes(int $trip_id, array $country_term_ids): array
{
    $nodes = [];
    $names = [];
    $about = [];

    $bikes = get_posts([
        'post_type'      => 'bike',
        'posts_per_page' => 12,
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

    $trip_url = get_permalink($trip_id);

    foreach ($bikes as $bike) {
        $url     = get_permalink($bike->ID);
        $bike_id = $url ? ($url . '#bike') : ($trip_url . '#bike-' . $bike->ID);
        $name    = get_the_title($bike->ID);

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
 * Return map of normalized bike name => Offer node array for schema usage.
 *
 * @param string $trip_url
 * @return array<string, array<string, string>>
 */
function hr_sa_wte_build_vehicle_offer_map(int $trip_id, string $trip_url): array
{
    $trip_id = (int) $trip_id;
    if ($trip_id <= 0) {
        return [];
    }

    $pairs = hr_sa_wte_get_rental_pairs($trip_id);
    if (empty($pairs)) {
        return [];
    }

    $currency  = 'USD';
    $base_url  = '';
    $candidate = is_string($trip_url) && $trip_url !== '' ? esc_url_raw($trip_url) : get_permalink($trip_id);
    if (is_string($candidate) && $candidate !== '') {
        $base_url = rtrim($candidate, '/');
    }

    $map = [];

    foreach ($pairs as $pair) {
        if (!is_array($pair)) {
            continue;
        }

        $name = isset($pair['name']) ? sanitize_text_field((string) $pair['name']) : '';
        if ($name === '') {
            continue;
        }

        $normalized  = hr_sa_norm_bike_name($name);
        $price       = isset($pair['price']) && is_numeric($pair['price']) ? (string) (0 + $pair['price']) : '0';
        $description = isset($pair['description']) && $pair['description'] !== ''
            ? sanitize_text_field((string) $pair['description'])
            : null;

        $map[$normalized] = array_filter(
            [
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => $currency,
                'availability'  => 'https://schema.org/InStock',
                'url'           => ($base_url !== '' ? $base_url : rtrim((string) get_permalink($trip_id), '/')) . '#intro',
                'description'   => $description,
            ],
            static fn($value) => $value !== null
        );
    }

    return $map;
}

/**
 * Normalize bike names for consistent keys.
 */
function hr_sa_norm_bike_name(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[–—−]+/u', '-', $value) ?? '';
    $value = preg_replace('/\b(\d+)\s*mt\b/u', '$1mt', $value) ?? '';
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

    return $value;
}

/**
 * Extract rental bike option pairs (name, price, description) from WTE meta.
 *
 * @return array<int, array{name: string, price: float, description: string}>
 */
function hr_sa_wte_get_rental_pairs(int $trip_id): array
{
    $pairs   = [];
    $setting = get_post_meta($trip_id, 'wp_travel_engine_setting', true);
    if (!is_array($setting)) {
        return $pairs;
    }

    $services = $setting['trip_extra_services'] ?? $setting['extra_services'] ?? [];
    if (!is_array($services)) {
        $services = [];
    }

    $chosen = get_post_meta($trip_id, 'wte_services_ids', true);
    if (is_array($chosen)) {
        $chosen = reset($chosen);
    }
    if (is_object($chosen) && isset($chosen->ID)) {
        $chosen = $chosen->ID;
    }

    $target = null;
    foreach ($services as $service) {
        if (!is_array($service)) {
            continue;
        }

        $id = $service['id'] ?? $service['service_id'] ?? null;
        if ($chosen && $id && (string) $id === (string) $chosen) {
            $target = $service;
            break;
        }
    }

    if (!$target) {
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $label = strtolower((string) ($service['label'] ?? $service['service_label'] ?? ''));
            if ($label !== '' && str_contains($label, 'rental bike')) {
                $target = $service;
                break;
            }
        }
    }

    if (!$target) {
        return $pairs;
    }

    $options = $target['options'] ?? $target['service_options'] ?? [];
    $prices  = $target['prices'] ?? $target['service_prices'] ?? [];
    $descs   = $target['descriptions'] ?? $target['service_descriptions'] ?? [];
    if (empty($options) && isset($target['data']) && is_array($target['data'])) {
        $data    = $target['data'];
        $options = $data['options'] ?? $options;
        $prices  = $data['prices'] ?? $prices;
        $descs   = $data['descriptions'] ?? $descs;
    }

    $count = max(count((array) $options), count((array) $prices), count((array) $descs));

    for ($i = 0; $i < $count; $i++) {
        $name  = is_array($options) ? sanitize_text_field((string) ($options[$i] ?? '')) : '';
        $price = is_array($prices) ? $prices[$i] ?? '' : '';
        $desc  = is_array($descs) ? sanitize_text_field((string) ($descs[$i] ?? '')) : '';

        if ($name === '') {
            continue;
        }

        $pairs[] = [
            'name'        => $name,
            'price'       => is_numeric($price) ? (float) $price : 0.0,
            'description' => $desc,
        ];
    }

    return $pairs;
}
