<?php
/**
 * Sponsorable tool email notifications.
 *
 * @package MillionDollarScript\Extensions\SponsorableAiTools
 */

namespace MillionDollarScript\Extensions\SponsorableAiTools;

if (!defined('ABSPATH')) {
    exit;
}

final class Notifications {

    public static function submission(array $tool, array $lead, $admin_url) {
        $site_name = self::site_name();
        $admin_email = sanitize_email((string) ($tool['contact_email'] ?? ''));
        $lead_email = sanitize_email((string) ($lead['email'] ?? ''));

        if (is_email($admin_email)) {
            $message = sprintf(__('A saved-result request was submitted for "%s".', 'mds-sponsorable-ai-tools'), (string) ($tool['title'] ?? ''))
                . "\n\n" . sprintf(__('Visitor: %1$s <%2$s>', 'mds-sponsorable-ai-tools'), (string) ($lead['name'] ?? ''), $lead_email)
                . "\n" . sprintf(__('Delivery mode: %s', 'mds-sponsorable-ai-tools'), (string) ($lead['unlock_mode'] ?? 'free'))
                . "\n" . sprintf(__('Payment status: %s', 'mds-sponsorable-ai-tools'), (string) ($lead['payment_status'] ?? 'not_required'))
                . "\n\n" . sprintf(__('Review it: %s', 'mds-sponsorable-ai-tools'), esc_url_raw((string) $admin_url));
            wp_mail(
                $admin_email,
                sprintf(__('[%1$s] Tool result request: %2$s', 'mds-sponsorable-ai-tools'), $site_name, (string) ($tool['title'] ?? '')),
                $message
            );
        }

        if ('free' !== (string) ($lead['unlock_mode'] ?? 'free') && is_email($lead_email)) {
            wp_mail(
                $lead_email,
                sprintf(__('[%s] Saved-result request received', 'mds-sponsorable-ai-tools'), $site_name),
                sprintf(__('We received your saved-result request for "%1$s". Delivery requires administrator approval%2$s.', 'mds-sponsorable-ai-tools'), (string) ($tool['title'] ?? ''), 'provider' === (string) ($lead['unlock_mode'] ?? '') ? __(' and successful checkout', 'mds-sponsorable-ai-tools') : '')
            );
        }
    }

    private static function site_name() {
        return wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    }
}
