<?php
/**
 * Plugin Name: HR — Schema Core (Settings + Sitewide Graph)
 * Description: Native Settings page (wp_options) + sitewide Organization/WebSite/WebPage JSON-LD.
 * Version: 1.1.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const HR_SCHEMA_SETTINGS_OPTION = 'hr_schema_settings';
const HR_SCHEMA_TEXT_DOMAIN = 'himalayan-rides';

add_action('admin_menu', 'hr_schema_core_register_options_page');
add_action('admin_init', 'hr_schema_core_register_settings');
add_action('init', 'hr_schema_core_setup_frontend_hooks');

if (!function_exists('hr_schema_jsonld_encode')) {
    /**
     * Encode data for JSON-LD using WordPress helper flags that keep unicode/slashes.
     */
    function hr_schema_jsonld_encode($data): string
    {
        return (string) wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('hr_schema_normalize_type')) {
    /**
     * Normalize an @type value to a flat array of unique strings.
     *
     * @param mixed $type Raw type value.
     *
     * @return array<int, string>
     */
    function hr_schema_normalize_type($type): array
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
}

if (!function_exists('hr_schema_dedupe_by_id')) {
    /**
     * Remove duplicate nodes by @id while preserving the first occurrence.
     *
     * @param array<int, array<string, mixed>> $graph
     *
     * @return array<int, array<string, mixed>>
     */
    function hr_schema_dedupe_by_id(array $graph): array
    {
        $result = [];
        $seen = [];

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
}

if (!function_exists('hr_schema_sanitize_answer_html')) {
    /**
     * Sanitize FAQ answer markup with a strict allowlist and whitespace folding.
     */
    function hr_schema_sanitize_answer_html(string $html): string
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
}
/**
 * Register the Schema settings page under the Settings menu.
 */
function hr_schema_core_register_options_page(): void
{
    add_options_page(
        esc_html__('Schema Settings', HR_SCHEMA_TEXT_DOMAIN),
        esc_html__('Schema Settings', HR_SCHEMA_TEXT_DOMAIN),
        'manage_options',
        'hr-schema-settings-native',
        'hr_schema_core_render_settings_page'
    );
}

/**
 * Register settings, sections, and fields for the Schema options page.
 */
function hr_schema_core_register_settings(): void
{
    register_setting(
        'hr_schema_settings_group',
        HR_SCHEMA_SETTINGS_OPTION,
        [
            'type'              => 'array',
            'sanitize_callback' => 'hr_schema_core_sanitize_settings',
            'default'           => [],
        ]
    );

    add_settings_section(
        'hr_schema_main',
        esc_html__('Organization', HR_SCHEMA_TEXT_DOMAIN),
        '__return_false',
        'hr-schema-settings-native'
    );

    $fields = [
        ['org_name', esc_html__('Organization Name', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_url', esc_html__('Organization URL', HR_SCHEMA_TEXT_DOMAIN), 'url'],
        ['org_legal_name', esc_html__('Legal Name (optional)', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_slogan', esc_html__('Slogan (optional)', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_description', esc_html__('Organization Description', HR_SCHEMA_TEXT_DOMAIN), 'textarea'],
        ['org_founding_date', esc_html__('Founding Date', HR_SCHEMA_TEXT_DOMAIN), 'date'],
        ['org_address_street', esc_html__('Address: Street', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_address_locality', esc_html__('Address: Locality/City', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_address_region', esc_html__('Address: Region/State', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_address_postal', esc_html__('Address: Postal Code', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_address_country', esc_html__('Address: Country (ISO or name)', HR_SCHEMA_TEXT_DOMAIN), 'text'],
        ['org_sameas', esc_html__('Profiles (sameAs) — one URL per line', HR_SCHEMA_TEXT_DOMAIN), 'textarea'],
        ['org_contact_points', esc_html__('ContactPoints JSON (array)', HR_SCHEMA_TEXT_DOMAIN), 'textarea'],
    ];

    foreach ($fields as $definition) {
        [$key, $label, $type] = $definition;
        add_settings_field(
            $key,
            $label,
            static function () use ($key, $type) {
                $options = get_option(HR_SCHEMA_SETTINGS_OPTION, []);
                $value = isset($options[$key]) ? $options[$key] : '';
                $name = sprintf('%s[%s]', HR_SCHEMA_SETTINGS_OPTION, $key);

                if ($type === 'textarea') {
                    printf(
                        '<textarea name="%1$s" rows="6" cols="60" class="large-text code">%2$s</textarea>',
                        esc_attr($name),
                        esc_textarea((string) $value)
                    );

                    if ($key === 'org_contact_points') {
                        echo '<p class="description">' . esc_html__(
                            'Example: [{"contactType":"support","telephone":"+1-555-555-5555","email":"help@example.com","availableLanguage":["en"]}]',
                            HR_SCHEMA_TEXT_DOMAIN
                        ) . '</p>';
                    } elseif ($key === 'org_description') {
                        echo '<p class="description">' . esc_html__(
                            'One or two sentences about the company (shown in Organization JSON-LD).',
                            HR_SCHEMA_TEXT_DOMAIN
                        ) . '</p>';
                    }

                    return;
                }

                $input_type = $type === 'date' ? 'date' : $type;
                printf(
                    '<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
                    esc_attr($input_type),
                    esc_attr($name),
                    esc_attr((string) $value)
                );

                if ($type === 'date') {
                    echo '<p class="description">' . esc_html__(
                        'Format: YYYY-MM-DD',
                        HR_SCHEMA_TEXT_DOMAIN
                    ) . '</p>';
                }
            },
            'hr-schema-settings-native',
            'hr_schema_main'
        );
    }

    add_settings_field(
        'org_logo_id',
        esc_html__('Organization Logo (media)', HR_SCHEMA_TEXT_DOMAIN),
        'hr_schema_core_render_logo_field',
        'hr-schema-settings-native',
        'hr_schema_main'
    );

    add_action('admin_enqueue_scripts', 'hr_schema_core_enqueue_admin_assets');
}

/**
 * Render the settings page markup.
 */
function hr_schema_core_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Schema Settings', HR_SCHEMA_TEXT_DOMAIN); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('hr_schema_settings_group');
            do_settings_sections('hr-schema-settings-native');
            submit_button();
            ?>
        </form>
        <hr />
        <p><strong><?php esc_html_e('Notes:', HR_SCHEMA_TEXT_DOMAIN); ?></strong></p>
        <ul style="list-style: disc; padding-left: 20px;">
            <li><?php esc_html_e('Profiles (sameAs): one URL per line.', HR_SCHEMA_TEXT_DOMAIN); ?></li>
            <li><?php esc_html_e('ContactPoints JSON: array of objects. Each can include contactType, telephone, email, areaServed, availableLanguage (array), contactOption (array).', HR_SCHEMA_TEXT_DOMAIN); ?></li>
            <li><?php esc_html_e('All values are stored in wp_options under hr_schema_settings.', HR_SCHEMA_TEXT_DOMAIN); ?></li>
        </ul>
    </div>
    <?php
}

/**
 * Sanitize and normalize settings data prior to persistence.
 *
 * @param array<string, mixed>|mixed $input
 *
 * @return array<string, mixed>
 */
function hr_schema_core_sanitize_settings($input): array
{
    if (!is_array($input)) {
        return [];
    }

    $output = [];
    $simple_keys = [
        'org_name',
        'org_url',
        'org_legal_name',
        'org_slogan',
        'org_founding_date',
        'org_description',
        'org_address_street',
        'org_address_locality',
        'org_address_region',
        'org_address_postal',
        'org_address_country',
        'org_logo_id',
    ];

    foreach ($simple_keys as $key) {
        if (!isset($input[$key])) {
            continue;
        }

        $value = $input[$key];
        if ($key === 'org_logo_id') {
            $output[$key] = (int) $value;
            continue;
        }

        if (is_string($value)) {
            $output[$key] = wp_kses_post(trim($value));
        }
    }

    if (!empty($input['org_sameas'])) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $input['org_sameas']);
        $urls = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $urls[] = esc_url_raw($line);
        }
        $output['org_sameas'] = implode("\n", $urls);
    } else {
        $output['org_sameas'] = '';
    }

    if (!empty($input['org_contact_points'])) {
        $raw = trim((string) $input['org_contact_points']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $output['org_contact_points'] = hr_schema_jsonld_encode($decoded);
        } else {
            $output['org_contact_points'] = wp_kses_post($raw);
        }
    } else {
        $output['org_contact_points'] = '';
    }

    return $output;
}

/**
 * Render the media picker field for the Organization logo.
 */
function hr_schema_core_render_logo_field(): void
{
    $options = get_option(HR_SCHEMA_SETTINGS_OPTION, []);
    $attachment_id = isset($options['org_logo_id']) ? (int) $options['org_logo_id'] : 0;
    $url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'full') : '';
    $name = sprintf('%s[org_logo_id]', HR_SCHEMA_SETTINGS_OPTION);

    if ($url) {
        printf(
            '<img src="%s" style="max-width:160px;display:block;margin:.5em 0;border:1px solid #ddd;padding:4px;background:#fff" alt="%s" />',
            esc_url($url),
            esc_attr__('Organization logo preview', HR_SCHEMA_TEXT_DOMAIN)
        );
    } else {
        echo '<em>' . esc_html__('No logo selected', HR_SCHEMA_TEXT_DOMAIN) . '</em>';
    }

    printf(
        '<input type="hidden" id="hr_org_logo_id" name="%s" value="%d" />',
        esc_attr($name),
        $attachment_id
    );
    echo ' <button type="button" class="button" id="hr_org_logo_btn">' . esc_html__('Select Logo', HR_SCHEMA_TEXT_DOMAIN) . '</button> ';
    if ($attachment_id) {
        echo ' <button type="button" class="button" id="hr_org_logo_clear">' . esc_html__('Clear', HR_SCHEMA_TEXT_DOMAIN) . '</button>';
    }
}

/**
 * Enqueue admin assets for the Schema settings page.
 */
function hr_schema_core_enqueue_admin_assets(string $hook): void
{
    if ($hook !== 'settings_page_hr-schema-settings-native') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-smoothness', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css', [], '1.13.2');

    $script = <<<'JS'
    jQuery(function($){
        let frame;
        $(document).on('click', '#hr_org_logo_btn', function(e){
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({ title: 'Select Logo', button: { text: 'Use this logo' }, multiple: false });
            frame.on('select', function(){
                const att = frame.state().get('selection').first().toJSON();
                $('#hr_org_logo_id').val(att.id);
                const img = $('<img/>', {
                    src: att.url,
                    css: { maxWidth: '160px', display: 'block', margin: '.5em 0', border: '1px solid #ddd', padding: '4px', background: '#fff' },
                    alt: att.alt || ''
                });
                const wrap = $('#hr_org_logo_btn').closest('td');
                wrap.find('img, em').remove();
                wrap.prepend(img);
                if (!$('#hr_org_logo_clear').length) {
                    $('<button type="button" class="button" id="hr_org_logo_clear">').text('Clear').insertAfter('#hr_org_logo_btn');
                }
            });
            frame.open();
        });

        $(document).on('click', '#hr_org_logo_clear', function(e){
            e.preventDefault();
            $('#hr_org_logo_id').val('');
            const wrap = $('#hr_org_logo_btn').closest('td');
            wrap.find('img').remove();
            if (!wrap.find('em').length) {
                wrap.prepend($('<em/>').text('No logo selected'));
            }
            $(this).remove();
        });

        const test = document.createElement('input');
        test.setAttribute('type','date');
        if (test.type !== 'date') {
            $('input[name="hr_schema_settings[org_founding_date]"]').datepicker({ dateFormat: 'yy-mm-dd' });
        }
    });
    JS;

    wp_add_inline_script('jquery-ui-datepicker', $script);
}
/**
 * Set up front-end hooks for schema output and consolidation.
 */
function hr_schema_core_setup_frontend_hooks(): void
{
    if (is_admin()) {
        return;
    }

    add_action('wp_head', 'hr_schema_core_start_buffer', PHP_INT_MIN);
    add_action('wp_head', 'hr_schema_core_print_graph', 98);
    add_action('wp_head', 'hr_schema_core_finalize_buffer', PHP_INT_MAX);
}

/**
 * Retrieve an option from the Schema settings array.
 *
 * @param string $key
 * @param mixed  $default
 *
 * @return mixed
 */
function hr_schema_core_get_option(string $key, $default = '')
{
    $options = get_option(HR_SCHEMA_SETTINGS_OPTION, []);
    return isset($options[$key]) && $options[$key] !== '' ? $options[$key] : $default;
}

/**
 * Resolve the Organization logo URL.
 */
function hr_schema_core_get_logo_url(): string
{
    $logo_id = (int) hr_schema_core_get_option('org_logo_id', 0);
    if ($logo_id > 0) {
        $url = wp_get_attachment_image_url($logo_id, 'full');
        if ($url) {
            return $url;
        }
    }

    $theme_logo_id = (int) get_theme_mod('custom_logo');
    if ($theme_logo_id > 0) {
        $url = wp_get_attachment_image_url($theme_logo_id, 'full');
        if ($url) {
            return $url;
        }
    }

    return '';
}

/**
 * Get the canonical HTTPS site URL with trailing slash.
 */
function hr_schema_core_site_url(): string
{
    static $site = '';
    if ($site !== '') {
        return $site;
    }

    $site = trailingslashit(set_url_scheme(home_url('/'), 'https'));
    return $site;
}

/**
 * Canonical Organization @id reference.
 */
function hr_schema_core_org_id(): string
{
    return hr_schema_core_site_url() . '#org';
}

/**
 * Canonical WebSite @id reference.
 */
function hr_schema_core_website_id(): string
{
    return hr_schema_core_site_url() . '#website';
}

/**
 * Determine the current page URL.
 */
function hr_schema_core_current_url(): string
{
    if (is_front_page() || is_home()) {
        return hr_schema_core_site_url();
    }

    if (is_singular()) {
        $permalink = get_permalink();
        if ($permalink) {
            return trailingslashit($permalink);
        }
    }

    global $wp;
    if ($wp instanceof WP) {
        $request = (string) $wp->request;
        if ($request !== '') {
            return home_url(add_query_arg([], $request));
        }
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    return home_url($uri);
}

/**
 * Build Organization, WebSite, and WebPage base graph nodes.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_schema_core_collect_base_graph(): array
{
    return [
        hr_schema_core_build_organization_node(),
        hr_schema_core_build_website_node(),
        hr_schema_core_build_webpage_node(),
    ];
}

/**
 * Build the Organization node from settings.
 */
function hr_schema_core_build_organization_node(): array
{
    $site_url = hr_schema_core_site_url();
    $organization = [
        '@type' => 'Organization',
        '@id'   => hr_schema_core_org_id(),
        'name'  => hr_schema_core_get_option('org_name', get_bloginfo('name')),
        'url'   => $site_url,
    ];

    $logo_url = hr_schema_core_get_logo_url();
    if ($logo_url) {
        $organization['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $logo_url,
        ];
    }

    $optionals = [
        'legalName'    => 'org_legal_name',
        'slogan'       => 'org_slogan',
        'description'  => 'org_description',
        'foundingDate' => 'org_founding_date',
    ];

    foreach ($optionals as $field => $key) {
        $value = hr_schema_core_get_option($key);
        if ($value !== '') {
            $organization[$field] = $value;
        }
    }

    $address = [
        'streetAddress'   => hr_schema_core_get_option('org_address_street'),
        'addressLocality' => hr_schema_core_get_option('org_address_locality'),
        'addressRegion'   => hr_schema_core_get_option('org_address_region'),
        'postalCode'      => hr_schema_core_get_option('org_address_postal'),
        'addressCountry'  => hr_schema_core_get_option('org_address_country'),
    ];

    if (array_filter($address, static fn($value) => $value !== '')) {
        $organization['address'] = array_merge(['@type' => 'PostalAddress'], $address);
    }

    $same_as_raw = hr_schema_core_get_option('org_sameas', '');
    if ($same_as_raw !== '') {
        $same_as = [];
        foreach (preg_split('/\r\n|\r|\n/', $same_as_raw) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $same_as[] = esc_url_raw($line);
        }
        if ($same_as) {
            $organization['sameAs'] = $same_as;
        }
    }

    $contact_points_raw = hr_schema_core_get_option('org_contact_points', '');
    if ($contact_points_raw !== '') {
        $decoded = json_decode($contact_points_raw, true);
        if (is_array($decoded) && $decoded) {
            $contact_points = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $contact_point = ['@type' => 'ContactPoint'];
                if (!empty($row['contactType'])) {
                    $contact_point['contactType'] = $row['contactType'];
                }
                if (!empty($row['telephone'])) {
                    $contact_point['telephone'] = preg_replace('/\s+/u', '', (string) $row['telephone']);
                }
                if (!empty($row['email'])) {
                    $contact_point['email'] = $row['email'];
                }
                if (!empty($row['areaServed'])) {
                    $contact_point['areaServed'] = $row['areaServed'];
                }
                if (!empty($row['availableLanguage'])) {
                    $contact_point['availableLanguage'] = $row['availableLanguage'];
                }
                if (!empty($row['contactOption'])) {
                    $contact_point['contactOption'] = $row['contactOption'];
                }

                if (!empty($contact_point['telephone']) || !empty($contact_point['email'])) {
                    $contact_points[] = $contact_point;
                }
            }

            if ($contact_points) {
                $organization['contactPoint'] = $contact_points;
            }
        }
    }

    return $organization;
}

/**
 * Build the WebSite node.
 */
function hr_schema_core_build_website_node(): array
{
    return [
        '@type' => 'WebSite',
        '@id'   => hr_schema_core_website_id(),
        'url'   => hr_schema_core_site_url(),
        'name'  => get_bloginfo('name'),
    ];
}

/**
 * Build the WebPage node for the current request.
 */
function hr_schema_core_build_webpage_node(): array
{
    $current_url = hr_schema_core_current_url();

    return [
        '@type'    => 'WebPage',
        '@id'      => trailingslashit($current_url) . '#webpage',
        'url'      => $current_url,
        'name'     => function_exists('wp_get_document_title') ? wp_get_document_title() : get_the_title(),
        'isPartOf' => ['@id' => hr_schema_core_website_id()],
        'about'    => ['@id' => hr_schema_core_org_id()],
    ];
}
/**
 * Begin buffering wp_head output for JSON-LD consolidation.
 */
function hr_schema_core_start_buffer(): void
{
    if (!empty($GLOBALS['hr_schema_core_buffer_active'])) {
        return;
    }

    $GLOBALS['hr_schema_core_buffer_active'] = true;
    ob_start('hr_schema_core_buffer_callback');
}

/**
 * Flush the buffered wp_head output through the consolidation pipeline.
 */
function hr_schema_core_finalize_buffer(): void
{
    if (empty($GLOBALS['hr_schema_core_buffer_active'])) {
        return;
    }

    $GLOBALS['hr_schema_core_buffer_active'] = false;
    ob_end_flush();
}

/**
 * Print the initial JSON-LD block composed of core graph nodes.
 */
function hr_schema_core_print_graph(): void
{
    $graph = hr_schema_core_collect_base_graph();
    $graph = apply_filters('hr_schema_graph_nodes', $graph);
    $graph = hr_schema_core_enforce_org_and_brand($graph);
    $graph = hr_schema_dedupe_by_id($graph);

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => array_values($graph),
    ];

    echo '<script type="application/ld+json">' . hr_schema_jsonld_encode($payload) . '</script>' . "\n";
}

/**
 * Output buffer callback that merges all JSON-LD fragments into a single block.
 */
function hr_schema_core_buffer_callback(string $buffer): string
{
    return hr_schema_core_merge_jsonld_blocks($buffer);
}

/**
 * Merge any JSON-LD fragments found in the provided HTML into one normalized block.
 */
function hr_schema_core_merge_jsonld_blocks(string $html): string
{
    if ($html === '') {
        return $html;
    }

    $pattern = '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is';
    if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
        return $html;
    }

    $graph = [];
    foreach ($matches as $match) {
        $fragment = json_decode($match[1], true);
        if (!is_array($fragment)) {
            continue;
        }

        $graph = array_merge($graph, hr_schema_core_extract_nodes_from_fragment($fragment));
    }

    if (!$graph) {
        return $html;
    }

    $graph = hr_schema_core_normalize_internal_urls($graph);
    $graph = hr_schema_core_enforce_org_and_brand($graph);
    $graph = hr_schema_dedupe_by_id($graph);

    $graph = apply_filters('hr_schema_graph_nodes', $graph);
    $graph = hr_schema_core_normalize_internal_urls($graph);
    $graph = hr_schema_core_enforce_org_and_brand($graph);
    $graph = hr_schema_dedupe_by_id($graph);

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => array_values($graph),
    ];

    foreach ($matches as $match) {
        $html = str_replace($match[0], '', $html);
    }

    $html .= "\n<script type=\"application/ld+json\">" . hr_schema_jsonld_encode($payload) . '</script>' . "\n";

    return $html;
}

/**
 * Extract graph nodes from a decoded JSON-LD fragment.
 *
 * @param array<string, mixed> $fragment
 *
 * @return array<int, array<string, mixed>>
 */
function hr_schema_core_extract_nodes_from_fragment(array $fragment): array
{
    if (isset($fragment['@graph']) && is_array($fragment['@graph'])) {
        $nodes = array_values(array_filter($fragment['@graph'], 'is_array'));
    } else {
        $nodes = [$fragment];
    }

    foreach ($nodes as &$node) {
        if (isset($node['@context'])) {
            unset($node['@context']);
        }
    }
    unset($node);

    return $nodes;
}

/**
 * Force all internal URLs to HTTPS across the graph payload.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_schema_core_normalize_internal_urls(array $graph): array
{
    $host = wp_parse_url(hr_schema_core_site_url(), PHP_URL_HOST);
    if (!$host) {
        return $graph;
    }

    $pattern = '#^https?://' . preg_quote($host, '#') . '#i';
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
 * Ensure an Organization node exists and Product.brand references it.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_schema_core_enforce_org_and_brand(array $graph): array
{
    $org_id = hr_schema_core_org_id();
    $site_url = hr_schema_core_site_url();
    $has_org = false;

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }

        $types = hr_schema_normalize_type($node['@type'] ?? null);
        if (in_array('Organization', $types, true)) {
            $graph[$index]['@id'] = $org_id;
            $graph[$index]['url'] = $site_url;
            $graph[$index]['@type'] = count($types) === 1 ? $types[0] : $types;
            $has_org = true;
        }
    }

    if (!$has_org) {
        $graph[] = [
            '@type' => 'Organization',
            '@id'   => $org_id,
            'name'  => get_bloginfo('name'),
            'url'   => $site_url,
        ];
    }

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }

        $types = hr_schema_normalize_type($node['@type'] ?? null);
        if (!in_array('Product', $types, true)) {
            continue;
        }

        $brand = $node['brand'] ?? [];
        if (!is_array($brand)) {
            $brand = [];
        }

        $brand['@id'] = $org_id;
        $brand['@type'] = 'Brand';
        $graph[$index]['brand'] = $brand;
    }

    return $graph;
}
