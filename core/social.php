<?php
/**
 * Shared social metadata helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve the stored social image override for a post.
 */
function hr_sa_get_social_image_override(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $value = get_post_meta($post_id, '_hr_sa_social_image_override', true);

    return hr_sa_sanitize_social_image_override($value);
}

/**
 * Retrieve the stored social description override for a post.
 */
function hr_sa_get_social_description_override(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $value = get_post_meta($post_id, '_hr_sa_social_description_override', true);

    return hr_sa_sanitize_social_description_override($value);
}

/**
 * Sanitize a social image override value.
 *
 * @param mixed $value Raw value submitted by the user.
 */
function hr_sa_sanitize_social_image_override($value): string
{
    if (is_array($value)) {
        $value = $value['url'] ?? '';
    }

    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }

    $sanitized = hr_sa_sanitize_context_image_url($value);

    return $sanitized ?? '';
}

/**
 * Sanitize a social description override value.
 *
 * @param mixed $value Raw value submitted by the user.
 */
function hr_sa_sanitize_social_description_override($value): string
{
    return hr_sa_ai_sanitize_meta_description(is_string($value) ? $value : '');
}

/**
 * Resolve the preferred social image URL and source for a post.
 *
 * @return array{url: string, source: 'override'|'meta'|'fallback'|'disabled'}
 */
function hr_sa_resolve_social_image_url(int $post_id): array
{
    $blocked = hr_sa_should_respect_other_seo() && hr_sa_other_seo_active();
    $enabled = hr_sa_is_og_enabled();

    $result = [
        'url'    => '',
        'source' => 'disabled',
    ];

    if (!$enabled || $blocked) {
        /**
         * Filter the resolved social image data when disabled.
         *
         * @param array{url: string, source: string} $result
         * @param int                                $post_id
         */
        return apply_filters('hr_sa_social_image_url', $result, $post_id);
    }

    $candidates = [];

    if ($post_id > 0) {
        $override = hr_sa_get_social_image_override($post_id);
        if ($override !== '') {
            $candidates[] = [
                'url'    => $override,
                'source' => 'override',
            ];
        }

        $meta = get_post_meta($post_id, '_hrih_header_image_url', true);
        if (is_array($meta) && isset($meta['url'])) {
            $meta = (string) $meta['url'];
        }
        if (is_string($meta) && $meta !== '') {
            $sanitized_meta = hr_sa_sanitize_social_image_override($meta);
            if ($sanitized_meta !== '') {
                $candidates[] = [
                    'url'    => $sanitized_meta,
                    'source' => 'meta',
                ];
            }
        }
    }

    $connector = hr_sa_get_media_help_hero_url();
    if ($connector) {
        $connector_url = hr_sa_sanitize_social_image_override($connector);
        if ($connector_url !== '') {
            $candidates[] = [
                'url'    => $connector_url,
                'source' => 'meta',
            ];
        }
    }

    $fallback = (string) hr_sa_get_setting('hr_sa_fallback_image', '');
    $fallback = hr_sa_sanitize_social_image_override($fallback);
    if ($fallback !== '') {
        $candidates[] = [
            'url'    => $fallback,
            'source' => 'fallback',
        ];
    }

    foreach ($candidates as $candidate) {
        $transformed = hr_sa_apply_image_url_replacements($candidate['url']);
        if ($transformed === '') {
            continue;
        }

        $result = [
            'url'    => $transformed,
            'source' => $candidate['source'],
        ];
        break;
    }

    if ($result['url'] === '') {
        $result['source'] = 'disabled';
    }

    /**
     * Filter the resolved social image data.
     *
     * @param array{url: string, source: string} $result
     * @param int                                $post_id
     */
    return apply_filters('hr_sa_social_image_url', $result, $post_id);
}

/**
 * Resolve the social description string for a post.
 */
function hr_sa_resolve_social_description(int $post_id, array $context): string
{
    if ($post_id > 0) {
        $override = hr_sa_get_social_description_override($post_id);
        if ($override !== '') {
            return $override;
        }

        $generated = get_post_meta($post_id, '_hr_sa_description', true);
        $generated = hr_sa_ai_sanitize_meta_description(is_string($generated) ? $generated : '');
        if ($generated !== '') {
            return $generated;
        }
    }

    $derived = isset($context['description'])
        ? hr_sa_ai_sanitize_meta_description((string) $context['description'])
        : '';
    if ($derived !== '') {
        return $derived;
    }

    if ($post_id > 0) {
        $excerpt = (string) get_post_field('post_excerpt', $post_id);
        $excerpt = hr_sa_ai_sanitize_meta_description($excerpt);
        if ($excerpt !== '') {
            return $excerpt;
        }

        $content = (string) get_post_field('post_content', $post_id);
        if ($content !== '') {
            $content = hr_sa_ai_prepare_snippet($content, hr_sa_ai_get_description_limit());
            $content = hr_sa_ai_sanitize_meta_description($content);
            if ($content !== '') {
                return $content;
            }
        }
    }

    $site_description = (string) get_bloginfo('description', 'display');
    $site_description = hr_sa_ai_sanitize_meta_description($site_description);
    if ($site_description !== '') {
        return $site_description;
    }

    $fallback = (string) ($context['site_name'] ?? get_bloginfo('name'));

    /**
     * Filter the resolved social description string.
     *
     * @param string               $fallback
     * @param int                  $post_id
     * @param array<string, mixed> $context
     */
    return apply_filters('hr_sa_social_description', hr_sa_ai_sanitize_meta_description($fallback), $post_id, $context);
}
