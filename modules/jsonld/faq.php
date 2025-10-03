<?php
/**
 * FAQ helpers for JSON-LD sourced from HRDF.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build FAQPage nodes for a Trip using HRDF payloads.
 *
 * @param array<int, mixed> $faq_items
 * @return array<int, array<string, mixed>>
 */
function hr_sa_trip_faq_nodes_from_hrdf(array $faq_items, string $trip_url, string $product_id): array
{
    if (!$faq_items) {
        return [];
    }

    $questions = [];

    foreach ($faq_items as $faq) {
        if (!is_array($faq)) {
            continue;
        }

        $question = isset($faq['question']) ? hr_sa_hrdf_normalize_text($faq['question']) : '';
        $answer   = isset($faq['answer']) ? (string) $faq['answer'] : '';

        if ($question === '' || $answer === '') {
            continue;
        }

        $questions[] = [
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => hr_sa_jsonld_sanitize_answer_html($answer),
            ],
        ];
    }

    if (!$questions) {
        return [];
    }

    $faq_id = '';
    if ($trip_url !== '') {
        $faq_id = rtrim($trip_url, '/') . '#faq';
    } elseif ($product_id !== '') {
        $faq_id = $product_id . '-faq';
    }

    $node = [
        '@type'      => 'FAQPage',
        'mainEntity' => $questions,
    ];

    if ($faq_id !== '') {
        $node['@id'] = $faq_id;
    }

    if ($product_id !== '') {
        $node['about'] = ['@id' => $product_id];
    }

    return [$node];
}
