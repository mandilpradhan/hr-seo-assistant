<?php
/**
 * Organization/WebSite/WebPage nodes.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

hr_sa_jsonld_register_emitter('org', 'hr_sa_jsonld_emit_org_graph');

/**
 * Emit base graph nodes for organization/site/webpage.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_emit_org_graph(): array
{
    return [
        hr_sa_jsonld_build_organization_node(),
        hr_sa_jsonld_build_website_node(),
        hr_sa_jsonld_build_webpage_node(),
    ];
}
