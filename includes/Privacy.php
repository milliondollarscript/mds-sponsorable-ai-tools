<?php
/**
 * WordPress personal-data export and erasure integration.
 *
 * @package MillionDollarScript\Extensions\SponsorableAiTools
 */

namespace MillionDollarScript\Extensions\SponsorableAiTools;

if (!defined('ABSPATH')) {
    exit;
}

final class Privacy {

    private const PAGE_SIZE = 50;

    public static function exporter($email_address, $page = 1) {
        $page = max(1, absint($page));
        $collection = Repository::leads_by_email_page($email_address, $page, self::PAGE_SIZE);
        $items = [];

        foreach ($collection['items'] as $lead) {
            $tool = Repository::tool(absint($lead['tool_id'] ?? 0));
            $items[] = [
                'group_id' => 'mds-sponsorable-ai-tools',
                'group_label' => __('Sponsorable tool result requests', 'mds-sponsorable-ai-tools'),
                'item_id' => 'mds-sponsorable-ai-tools-' . absint($lead['id'] ?? 0),
                'data' => [
                    ['name' => __('Tool', 'mds-sponsorable-ai-tools'), 'value' => (string) ($tool['title'] ?? '')],
                    ['name' => __('Name', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['name'] ?? '')],
                    ['name' => __('Email', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['email'] ?? '')],
                    ['name' => __('Message', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['message'] ?? '')],
                    ['name' => __('Saved result', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['result'] ?? '')],
                    ['name' => __('Moderation status', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['status'] ?? '')],
                    ['name' => __('Payment status', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['payment_status'] ?? '')],
                    ['name' => __('Delivery status', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['delivery_status'] ?? '')],
                    ['name' => __('Amount', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['amount'] ?? '')],
                    ['name' => __('Currency', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['currency'] ?? '')],
                    ['name' => __('Terms accepted', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['terms_accepted_at'] ?? '')],
                    ['name' => __('Delivered', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['delivered_at'] ?? '')],
                    ['name' => __('Created', 'mds-sponsorable-ai-tools'), 'value' => (string) ($lead['created_at'] ?? '')],
                ],
            ];
        }

        return [
            'data' => $items,
            'done' => $page >= absint($collection['pages'] ?? 1),
        ];
    }

    public static function eraser($email_address, $page = 1) {
        unset($page);
        $collection = Repository::leads_by_email_page($email_address, 1, self::PAGE_SIZE);
        $removed = 0;
        foreach ($collection['items'] as $lead) {
            if (Repository::anonymize_lead(absint($lead['id'] ?? 0))) {
                $removed++;
            }
        }
        $remaining = Repository::leads_by_email_page($email_address, 1, 1);

        return [
            'items_removed' => $removed > 0,
            'items_retained' => $removed > 0,
            'messages' => $removed ? [__('Contact details, messages, saved results, checkout links, and IP-derived abuse data were erased. Non-identifying payment, moderation, provider-order, consent-time, and delivery records were retained for accounting, fraud prevention, and dispute handling.', 'mds-sponsorable-ai-tools')] : [],
            'done' => empty($remaining['items']),
        ];
    }
}
