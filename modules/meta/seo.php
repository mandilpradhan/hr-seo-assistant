<?php
/**
 * Front-end emission of stored AI meta tags.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp', 'hr_sa_meta_maybe_schedule');

/**
 * Determine whether meta tags should be scheduled for output.
 */
function hr_sa_meta_maybe_schedule(): void
{
    if (is_admin()) {
        return;
    }

    if (!is_singular()) {
        return;
    }

    if (hr_sa_should_respect_other_seo() && hr_sa_other_seo_active()) {
        return;
    }

    add_action('wp_head', 'hr_sa_output_ai_meta', 28);
}

/**
 * Output stored AI-generated meta tags when available.
 */
function hr_sa_output_ai_meta(): void
{
    if (!is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    $title       = (string) get_post_meta($post_id, '_hr_sa_title', true);
    $description = (string) get_post_meta($post_id, '_hr_sa_description', true);
    $keywords    = (string) get_post_meta($post_id, '_hr_sa_keywords', true);

    $title       = hr_sa_ai_sanitize_meta_title($title);
    $description = hr_sa_ai_sanitize_meta_description($description);
    $keywords    = hr_sa_ai_sanitize_meta_keywords($keywords);

    if ($title === '' && $description === '' && $keywords === '') {
        return;
    }

    if ($title !== '') {
        printf('<meta name="title" content="%s" />' . PHP_EOL, esc_attr($title));
    }

    if ($description !== '') {
        printf('<meta name="description" content="%s" />' . PHP_EOL, esc_attr($description));
    }

    if ($keywords !== '') {
        printf('<meta name="keywords" content="%s" />' . PHP_EOL, esc_attr($keywords));
    }
}
