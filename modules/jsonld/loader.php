<?php
/**
 * JSON-LD loader and shared helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, callable> $hr_sa_jsonld_emitters */
$GLOBALS['hr_sa_jsonld_emitters'] = [];
/** @var array<int, string> $hr_sa_jsonld_last_active_emitters */
$GLOBALS['hr_sa_jsonld_last_active_emitters'] = [];

/**
 * Register hooks for the JSON-LD module.
 */
function hr_sa_jsonld_boot(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    add_action('wp', 'hr_sa_jsonld_maybe_schedule');
    $booted = true;
}

/**
 * Conditionally schedule JSON-LD output.
 */
function hr_sa_jsonld_maybe_schedule(): void
{
    if (is_admin()) {
        return;
    }

    if (!hr_sa_is_jsonld_enabled()) {
        return;
    }

    if (hr_sa_should_respect_other_seo() && hr_sa_other_seo_active()) {
        return;
    }

    add_action('wp_head', 'hr_sa_jsonld_print_graph', 98);
}

/**
 * Register an emitter callback.
 */
function hr_sa_jsonld_register_emitter(string $id, callable $callback): void
{
    $GLOBALS['hr_sa_jsonld_emitters'][$id] = $callback;
}

/**
 * Output the consolidated JSON-LD graph.
 */
function hr_sa_jsonld_print_graph(): void
{
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;

    $graph = hr_sa_jsonld_collect_graph();
    if (!$graph) {
        return;
    }

    $graph = hr_sa_jsonld_normalize_internal_urls($graph);
    $graph = hr_sa_jsonld_enforce_org_and_brand($graph);
    $graph = hr_sa_jsonld_dedupe_by_id($graph);

    $graph = apply_filters('hr_sa_jsonld_graph_nodes', $graph);
    $graph = hr_sa_jsonld_normalize_internal_urls($graph);
    $graph = hr_sa_jsonld_enforce_org_and_brand($graph);
    $graph = hr_sa_jsonld_dedupe_by_id($graph);

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => array_values($graph),
    ];

    echo '<script type="application/ld+json">' . hr_sa_jsonld_encode($payload) . '</script>' . PHP_EOL;
}

/**
 * Collect nodes from all registered emitters.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_collect_graph(): array
{
    $graph = [];
    $active = [];
    foreach ($GLOBALS['hr_sa_jsonld_emitters'] as $id => $callback) {
        if (!is_callable($callback)) {
            continue;
        }

        $nodes = (array) call_user_func($callback);
        foreach ($nodes as $node) {
            if (is_array($node) && $node) {
                $graph[] = $node;
            }
        }

        if ($nodes) {
            $active[] = (string) $id;
        }
    }

    $GLOBALS['hr_sa_jsonld_last_active_emitters'] = $active;

    return $graph;
}

/**
 * Return a list of emitter identifiers that produced nodes on the last run.
 *
 * @return array<int, string>
 */
function hr_sa_jsonld_get_active_emitters(): array
{
    return $GLOBALS['hr_sa_jsonld_last_active_emitters'] ?? [];
}

/**
 * Encode data for JSON-LD output.
 *
 * @param mixed $data
 */
function hr_sa_jsonld_encode($data): string
{
    return (string) wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Normalize multi-line or HTML content to plain text.
 *
 * @param mixed $content
 */
function hr_sa_jsonld_clean_text($content, int $words = 0): string
{
    $text = wp_strip_all_tags((string) $content, true);
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    if ($words > 0 && $text !== '') {
        $parts = preg_split('/\s+/u', $text) ?: [];
        if (count($parts) > $words) {
            $text = implode(' ', array_slice($parts, 0, $words)) . '…';
        }
    }

    return $text;
}

/**
 * Remove empty values from a node array while preserving nested arrays.
 *
 * @param array<string, mixed> $data
 *
 * @return array<string, mixed>
 */
function hr_sa_jsonld_array_filter(array $data): array
{
    return array_filter(
        $data,
        static function ($value) {
            if ($value === null) {
                return false;
            }

            if ($value === '') {
                return false;
            }

            if (is_array($value)) {
                return !empty($value);
            }

            return true;
        }
    );
}

/**
 * Normalize URL values and discard invalid ones.
 */
function hr_sa_jsonld_normalize_url($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $url = esc_url_raw($value);

    return is_string($url) ? $url : '';
}

/**
 * Normalize ISO-8601 date values.
 */
function hr_sa_jsonld_normalize_iso8601($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $pattern = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(?::\d{2})?(Z|[+\-]\d{2}:?\d{2})?)?$/';

    return preg_match($pattern, $value) === 1 ? $value : '';
}

/**
 * Normalize availability enumerations to schema.org URLs.
 */
function hr_sa_jsonld_normalize_availability($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $url = hr_sa_jsonld_normalize_url($value);
    if ($url !== '') {
        return $url;
    }

    $map = [
        'instock'             => 'https://schema.org/InStock',
        'outofstock'          => 'https://schema.org/OutOfStock',
        'soldout'             => 'https://schema.org/SoldOut',
        'limitedavailability' => 'https://schema.org/LimitedAvailability',
        'preorder'            => 'https://schema.org/PreOrder',
        'presale'             => 'https://schema.org/PreOrder',
        'preorderavailable'   => 'https://schema.org/PreOrder',
    ];

    $key = strtolower(preg_replace('/[^a-z]/u', '', $value));

    return $map[$key] ?? '';
}

/**
 * Normalize QuantitativeValue data structures.
 *
 * @param mixed $value
 */
function hr_sa_jsonld_prepare_quantitative_value($value): ?array
{
    if (is_array($value)) {
        $numeric = $value['value'] ?? null;
        if ($numeric === null || $numeric === '') {
            return null;
        }

        if (!is_numeric($numeric)) {
            return null;
        }

        $result = [
            '@type' => 'QuantitativeValue',
            'value' => 0 + $numeric,
        ];

        if (!empty($value['unitCode']) && is_string($value['unitCode'])) {
            $result['unitCode'] = strtoupper(trim((string) $value['unitCode']));
        }

        return hr_sa_jsonld_array_filter($result);
    }

    if (is_numeric($value)) {
        return [
            '@type' => 'QuantitativeValue',
            'value' => 0 + $value,
        ];
    }

    if (is_string($value) && $value !== '' && is_numeric($value)) {
        return [
            '@type' => 'QuantitativeValue',
            'value' => 0 + $value,
        ];
    }

    return null;
}

/**
 * Build an Offer node from HRDF data.
 *
 * @param array<string, mixed> $raw
 */
function hr_sa_jsonld_prepare_offer(array $raw, string $default_url = ''): ?array
{
    $price = $raw['price'] ?? null;
    if ($price === null || $price === '') {
        return null;
    }

    if (is_numeric($price)) {
        $price = number_format((float) $price, 2, '.', '');
    } else {
        $price = trim((string) $price);
    }

    if ($price === '') {
        return null;
    }

    $currency = $raw['currency'] ?? '';
    $currency = is_string($currency) ? strtoupper(trim($currency)) : '';
    if ($currency === '' || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        return null;
    }

    $offer = [
        '@type'         => 'Offer',
        'price'         => $price,
        'priceCurrency' => $currency,
    ];

    $availability = hr_sa_jsonld_normalize_availability($raw['availability'] ?? '');
    if ($availability !== '') {
        $offer['availability'] = $availability;
    }

    $mapping = [
        'availabilityStarts' => 'availability_starts',
        'availabilityEnds'   => 'availability_ends',
        'validFrom'          => 'valid_from',
        'validThrough'       => 'valid_through',
        'priceValidUntil'    => 'price_valid_until',
    ];

    foreach ($mapping as $property => $source) {
        if (!isset($raw[$source])) {
            continue;
        }

        $value = hr_sa_jsonld_normalize_iso8601($raw[$source]);
        if ($value !== '') {
            $offer[$property] = $value;
        }
    }

    $url = hr_sa_jsonld_normalize_url($raw['url'] ?? '');
    if ($url === '' && $default_url !== '') {
        $url = $default_url;
    }

    if ($url !== '') {
        $offer['url'] = $url;
    }

    $quantitative = [
        'inventoryLevel'   => $raw['inventory_level'] ?? null,
        'eligibleQuantity' => $raw['eligible_quantity'] ?? null,
    ];

    foreach ($quantitative as $property => $source_value) {
        $value = hr_sa_jsonld_prepare_quantitative_value($source_value);
        if ($value !== null) {
            $offer[$property] = $value;
        }
    }

    if (isset($raw['description'])) {
        $description = hr_sa_jsonld_clean_text($raw['description'], 60);
        if ($description !== '') {
            $offer['description'] = $description;
        }
    }

    $string_fields = [
        'name'         => 'name',
        'sku'          => 'sku',
        'category'     => 'category',
        'itemCondition'=> 'item_condition',
    ];

    foreach ($string_fields as $property => $source) {
        if (!isset($raw[$source])) {
            continue;
        }

        $value = is_string($raw[$source]) ? trim($raw[$source]) : '';
        if ($value !== '') {
            $offer[$property] = $value;
        }
    }

    $offer = hr_sa_jsonld_array_filter($offer);

    return $offer ?: null;
}

/**
 * Build a sanitized description fallback from post content.
 */
function hr_sa_jsonld_description_fallback(int $post_id, int $words = 0): string
{
    $excerpt = get_post_field('post_excerpt', $post_id);
    if (is_string($excerpt) && $excerpt !== '') {
        $text = hr_sa_jsonld_clean_text($excerpt, $words);
        if ($text !== '') {
            return $text;
        }
    }

    $content = get_post_field('post_content', $post_id);
    if (is_string($content) && $content !== '') {
        $text = hr_sa_jsonld_clean_text($content, $words);
        if ($text !== '') {
            return $text;
        }
    }

    $title = get_the_title($post_id);
    if (is_string($title) && $title !== '') {
        return hr_sa_jsonld_clean_text($title, $words);
    }

    return '';
}

/**
 * Normalize @type values to arrays of unique strings.
 */
function hr_sa_jsonld_normalize_type($type): array
{
    if (is_array($type)) {
        $types = array_filter(array_map('strval', $type));
        return array_values(array_unique($types));
    }

    if ($type === null || $type === '') {
        return [];
    }

    return [(string) $type];
}

/**
 * Remove duplicate nodes by @id.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_dedupe_by_id(array $graph): array
{
    $result = [];
    $seen   = [];

    foreach ($graph as $node) {
        if (!is_array($node)) {
            continue;
        }

        $id = isset($node['@id']) ? (string) $node['@id'] : '';
        if ($id !== '' && isset($seen[$id])) {
            continue;
        }

        if ($id !== '') {
            $seen[$id] = true;
        }

        $result[] = $node;
    }

    return $result;
}

/**
 * Sanitize answer markup for FAQ nodes.
 */
function hr_sa_jsonld_sanitize_answer_html(string $html): string
{
    $allowed = [
        'p'      => [],
        'br'     => [],
        'ul'     => [],
        'ol'     => [],
        'li'     => [],
        'strong' => [],
        'em'     => [],
        'b'      => [],
        'i'      => [],
        'a'      => [
            'href'  => [],
            'title' => [],
            'rel'   => [],
        ],
    ];

    $clean = strip_shortcodes($html);
    $clean = wp_kses($clean, $allowed);
    $clean = (string) preg_replace("/\r\n?/", "\n", $clean);
    $clean = (string) preg_replace('/[ \t]+/u', ' ', $clean);
    $clean = (string) preg_replace("/\n{3,}/u", "\n\n", $clean);

    return trim($clean);
}

/**
 * Resolve the Organization logo URL.
 */
function hr_sa_jsonld_get_logo_url(): string
{
    $logo = hr_sa_hrdf_get('organization.logo', null, []);
    if (is_array($logo)) {
        if (!empty($logo['url'])) {
            $url = hr_sa_jsonld_normalize_url($logo['url']);
            if ($url !== '') {
                return $url;
            }
        }

        if (!empty($logo['attachment_id']) && is_numeric($logo['attachment_id'])) {
            $attachment = wp_get_attachment_image_url((int) $logo['attachment_id'], 'full');
            if ($attachment) {
                return esc_url_raw($attachment);
            }
        }
    }

    $direct = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('organization.logo.url'));
    if ($direct !== '') {
        return $direct;
    }

    $attachment_id = hr_sa_hrdf_get('organization.logo.attachment_id');
    if (is_numeric($attachment_id)) {
        $attachment = wp_get_attachment_image_url((int) $attachment_id, 'full');
        if ($attachment) {
            return esc_url_raw($attachment);
        }
    }

    $theme_logo_id = (int) get_theme_mod('custom_logo');
    if ($theme_logo_id > 0) {
        $url = wp_get_attachment_image_url($theme_logo_id, 'full');
        if ($url) {
            return esc_url_raw($url);
        }
    }

    return '';
}

/**
 * Build the ImageObject structure for the organization logo.
 */
function hr_sa_jsonld_build_logo_object(): ?array
{
    $logo_data = hr_sa_hrdf_get('organization.logo', null, []);
    if (!is_array($logo_data)) {
        $logo_data = [];
    }

    $url = hr_sa_jsonld_get_logo_url();
    if ($url === '') {
        return null;
    }

    $logo = [
        '@type' => 'ImageObject',
        'url'   => $url,
    ];

    if (isset($logo_data['width']) && is_numeric($logo_data['width'])) {
        $logo['width'] = (int) $logo_data['width'];
    }

    if (isset($logo_data['height']) && is_numeric($logo_data['height'])) {
        $logo['height'] = (int) $logo_data['height'];
    }

    if (isset($logo_data['caption']) && is_string($logo_data['caption']) && $logo_data['caption'] !== '') {
        $logo['caption'] = $logo_data['caption'];
    }

    if (isset($logo_data['representativeOfPage']) && is_bool($logo_data['representativeOfPage'])) {
        $logo['representativeOfPage'] = $logo_data['representativeOfPage'];
    }

    return hr_sa_jsonld_array_filter($logo) ?: null;
}

/**
 * Canonical HTTPS site URL with trailing slash.
 */
function hr_sa_jsonld_site_url(): string
{
    static $site = '';
    if ($site !== '') {
        return $site;
    }

    $site = trailingslashit(set_url_scheme(home_url('/'), 'https'));
    return $site;
}

function hr_sa_jsonld_org_id(): string
{
    return hr_sa_jsonld_site_url() . '#org';
}

function hr_sa_jsonld_website_id(): string
{
    return hr_sa_jsonld_site_url() . '#website';
}

/**
 * Determine current URL similar to legacy behavior.
 */
/**
 * Force all internal URLs to HTTPS.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_normalize_internal_urls(array $graph): array
{
    $host = wp_parse_url(hr_sa_jsonld_site_url(), PHP_URL_HOST);
    if (!$host) {
        return $graph;
    }

    $pattern     = '#^https?://' . preg_quote((string) $host, '#') . '#i';
    $replacement = 'https://' . $host;

    $normalize = static function ($value) use (&$normalize, $pattern, $replacement) {
        if (is_array($value)) {
            foreach ($value as $key => $sub) {
                $value[$key] = $normalize($sub);
            }
            return $value;
        }

        if (is_string($value)) {
            return preg_replace($pattern, $replacement, $value);
        }

        return $value;
    };

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }
        $graph[$index] = $normalize($node);
    }

    return $graph;
}

/**
 * Ensure Organization node exists and referenced by Product.brand.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_enforce_org_and_brand(array $graph): array
{
    $org_id  = hr_sa_jsonld_org_id();
    $site    = hr_sa_jsonld_site_url();
    $has_org = false;

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }

        $types = hr_sa_jsonld_normalize_type($node['@type'] ?? null);
        if (in_array('Organization', $types, true)) {
            $graph[$index]['@id'] = $org_id;
            $graph[$index]['url'] = $site;
            $has_org = true;
        }
    }

    if (!$has_org) {
        $graph[] = hr_sa_jsonld_build_organization_node();
    }

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }

        $types = hr_sa_jsonld_normalize_type($node['@type'] ?? null);
        if (in_array('Product', $types, true)) {
            $graph[$index]['brand'] = [
                '@type' => 'Brand',
                '@id'   => $org_id,
            ];
        }
    }

    return $graph;
}

/**
 * Build the Organization node using HRDF data.
 */
function hr_sa_jsonld_build_organization_node(): array
{
    $site_url = hr_sa_jsonld_site_url();

    $name = hr_sa_hrdf_get('organization.name');
    if (!is_string($name) || $name === '') {
        $name = get_bloginfo('name');
    }

    $url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('organization.url'));
    if ($url === '') {
        $url = $site_url;
    }

    $organization = [
        '@type' => 'Organization',
        '@id'   => hr_sa_jsonld_org_id(),
        'name'  => $name,
        'url'   => $url,
    ];

    $logo = hr_sa_jsonld_build_logo_object();
    if ($logo) {
        $organization['logo'] = $logo;
    }

    $string_fields = [
        'legalName'   => 'organization.legal_name',
        'slogan'      => 'organization.slogan',
        'description' => 'organization.description',
        'email'       => 'organization.email',
        'taxID'       => 'organization.tax_id',
        'vatID'       => 'organization.vat_id',
        'duns'        => 'organization.duns',
    ];

    foreach ($string_fields as $property => $path) {
        $value = hr_sa_hrdf_get($path);
        if (!is_string($value) || $value === '') {
            continue;
        }

        if ($property === 'description') {
            $value = hr_sa_jsonld_clean_text($value, 80);
        }

        if ($value !== '') {
            $organization[$property] = $value;
        }
    }

    $founding_date = hr_sa_jsonld_normalize_iso8601(hr_sa_hrdf_get('organization.founding_date'));
    if ($founding_date !== '') {
        $organization['foundingDate'] = $founding_date;
    }

    $telephone = hr_sa_hrdf_get('organization.telephone');
    if (is_string($telephone) && $telephone !== '') {
        $sanitized = preg_replace('/\s+/u', '', $telephone);
        $organization['telephone'] = $sanitized ?: $telephone;
    }

    $address_data = hr_sa_hrdf_get('organization.address');
    if (!is_array($address_data)) {
        $address_data = [];
    }

    $address_fields = [
        'streetAddress',
        'addressLocality',
        'addressRegion',
        'postalCode',
        'addressCountry',
    ];

    $address = [];
    foreach ($address_fields as $field) {
        $value = '';
        if (isset($address_data[$field])) {
            $value = is_string($address_data[$field]) ? trim($address_data[$field]) : '';
        } else {
            $candidate = hr_sa_hrdf_get('organization.address.' . $field);
            $value     = is_string($candidate) ? trim($candidate) : '';
        }

        if ($value !== '') {
            $address[$field] = $value;
        }
    }

    if ($address) {
        $organization['address'] = array_merge(['@type' => 'PostalAddress'], $address);
    }

    $same_as = hr_sa_hrdf_get_array('organization.same_as');
    if ($same_as) {
        $normalized = array_values(array_filter(array_map('hr_sa_jsonld_normalize_url', $same_as)));
        if ($normalized) {
            $organization['sameAs'] = $normalized;
        }
    }

    $contact_points = hr_sa_hrdf_get_array('organization.contact_points');
    if ($contact_points) {
        $normalized_points = [];
        foreach ($contact_points as $row) {
            if (!is_array($row)) {
                continue;
            }

            $point = ['@type' => 'ContactPoint'];

            $type = $row['contactType'] ?? $row['contact_type'] ?? '';
            if (is_string($type) && $type !== '') {
                $point['contactType'] = $type;
            }

            $phone = $row['telephone'] ?? $row['phone'] ?? '';
            if (is_string($phone) && $phone !== '') {
                $point['telephone'] = preg_replace('/\s+/u', '', $phone) ?: $phone;
            }

            $email = $row['email'] ?? '';
            if (is_string($email) && $email !== '') {
                $point['email'] = $email;
            }

            $area = $row['areaServed'] ?? $row['area_served'] ?? '';
            if (is_string($area) && $area !== '') {
                $point['areaServed'] = $area;
            }

            $languages = $row['availableLanguage'] ?? $row['available_language'] ?? '';
            if (is_array($languages)) {
                $languages = array_values(array_filter(array_map('strval', $languages)));
                if ($languages) {
                    $point['availableLanguage'] = $languages;
                }
            } elseif (is_string($languages) && $languages !== '') {
                $point['availableLanguage'] = $languages;
            }

            $options = $row['contactOption'] ?? $row['contact_option'] ?? '';
            if (is_array($options)) {
                $options = array_values(array_filter(array_map('strval', $options)));
                if ($options) {
                    $point['contactOption'] = $options;
                }
            } elseif (is_string($options) && $options !== '') {
                $point['contactOption'] = $options;
            }

            $point = hr_sa_jsonld_array_filter($point);
            if (!empty($point['telephone']) || !empty($point['email'])) {
                $normalized_points[] = $point;
            }
        }

        if ($normalized_points) {
            $organization['contactPoint'] = $normalized_points;
        }
    }

    return $organization;
}

/**
 * Build the WebSite node.
 */
function hr_sa_jsonld_build_website_node(): array
{
    $site_url = hr_sa_jsonld_site_url();

    $url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('website.url'));
    if ($url === '') {
        $url = $site_url;
    }

    $name = hr_sa_hrdf_get('website.name');
    if (!is_string($name) || $name === '') {
        $name = get_bloginfo('name');
    }

    $website = [
        '@type' => 'WebSite',
        '@id'   => hr_sa_jsonld_website_id(),
        'url'   => $url,
        'name'  => $name,
    ];

    $alternate_name = hr_sa_hrdf_get('website.alternate_name');
    if (is_string($alternate_name) && $alternate_name !== '') {
        $website['alternateName'] = $alternate_name;
    }

    $description = hr_sa_hrdf_get('website.description');
    if (is_string($description) && $description !== '') {
        $website['description'] = hr_sa_jsonld_clean_text($description, 80);
    }

    $in_language = hr_sa_hrdf_get('website.in_language');
    if (is_string($in_language) && $in_language !== '') {
        $website['inLanguage'] = $in_language;
    }

    $potential_action = hr_sa_hrdf_get('website.potential_action');
    if (is_array($potential_action) && !empty($potential_action)) {
        $website['potentialAction'] = $potential_action;
    }

    return $website;
}

/**
 * Build the WebPage node for the current view.
 */
function hr_sa_jsonld_build_webpage_node(): array
{
    $post_id = get_queried_object_id();

    $url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('webpage.url', $post_id));
    if ($url === '' && $post_id) {
        $url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('trip.product.url', $post_id));
    }

    if ($url === '') {
        return [];
    }

    $node = [
        '@type'    => 'WebPage',
        '@id'      => trailingslashit($url) . '#webpage',
        'url'      => $url,
        'isPartOf' => ['@id' => hr_sa_jsonld_website_id()],
        'about'    => ['@id' => hr_sa_jsonld_org_id()],
    ];

    $name = hr_sa_hrdf_get('webpage.name', $post_id);
    if ((!is_string($name) || $name === '') && $post_id) {
        $name = hr_sa_hrdf_get('trip.product.name', $post_id);
    }
    if (is_string($name) && $name !== '') {
        $node['name'] = $name;
    }

    $description = hr_sa_hrdf_get('webpage.description', $post_id);
    if ((!is_string($description) || $description === '') && $post_id) {
        $description = hr_sa_hrdf_get('trip.product.description', $post_id);
        if (!is_string($description) || $description === '') {
            $description = hr_sa_jsonld_description_fallback($post_id, 60);
        }
    }
    if (is_string($description) && $description !== '') {
        $node['description'] = $description;
    }

    $primary_image = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('webpage.primary_image', $post_id));
    if ($primary_image !== '') {
        $node['image'] = $primary_image;
    }

    $breadcrumb = hr_sa_hrdf_get('webpage.breadcrumb', $post_id);
    if (is_array($breadcrumb) && !empty($breadcrumb)) {
        $node['breadcrumb'] = $breadcrumb;
    }

    $speakable = hr_sa_hrdf_get('webpage.speakable', $post_id);
    if (is_array($speakable) && !empty($speakable)) {
        $node['speakable'] = $speakable;
    }

    $date_published = hr_sa_jsonld_normalize_iso8601(hr_sa_hrdf_get('webpage.date_published', $post_id));
    if ($date_published !== '') {
        $node['datePublished'] = $date_published;
    }

    $date_modified = hr_sa_jsonld_normalize_iso8601(hr_sa_hrdf_get('webpage.date_modified', $post_id));
    if ($date_modified !== '') {
        $node['dateModified'] = $date_modified;
    }

    return $node;
}

require_once __DIR__ . '/org.php';
require_once __DIR__ . '/itinerary.php';
require_once __DIR__ . '/faq.php';
require_once __DIR__ . '/vehicles.php';
require_once __DIR__ . '/trip.php';
