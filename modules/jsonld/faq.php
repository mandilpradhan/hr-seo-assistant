<?php
/**
 * FAQ helpers for JSON-LD.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build FAQPage nodes for a Trip using HRDF data.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_faq_nodes(int $trip_id): array
{
    if ($trip_id <= 0 || get_post_type($trip_id) !== 'trip') {
        return [];
    }

    $items = hr_sa_hrdf_get_array('trip.faq', $trip_id);
    if (!$items) {
        return [];
    }

    $questions = [];
    foreach ($items as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $question = $entry['question'] ?? ($entry['q'] ?? '');
        $answer   = $entry['answer'] ?? ($entry['a'] ?? '');

        if (!is_string($question) || trim($question) === '') {
            continue;
        }

        if (!is_string($answer) || trim($answer) === '') {
            continue;
        }

        $clean_answer = hr_sa_jsonld_sanitize_answer_html($answer);
        if ($clean_answer === '') {
            continue;
        }

        $questions[] = [
            '@type'          => 'Question',
            'name'           => trim($question),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $clean_answer,
            ],
        ];
    }

    if (!$questions) {
        return [];
    }

    $faq_url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('trip.faq.url', $trip_id));
    if ($faq_url === '') {
        $faq_url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('webpage.url', $trip_id));
    }
    if ($faq_url === '') {
        $faq_url = hr_sa_jsonld_normalize_url(hr_sa_hrdf_get('trip.product.url', $trip_id));
    }

    if ($faq_url === '') {
        return [];
    }

    $node = [
        '@type'      => 'FAQPage',
        '@id'        => trailingslashit($faq_url) . '#faqs',
        'mainEntity' => array_values($questions),
    ];

    return [$node];
}
