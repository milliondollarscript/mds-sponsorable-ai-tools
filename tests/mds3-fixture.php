<?php
/**
 * Local workflow fixture for Sponsorable AI Tools.
 *
 * Run with:
 * ./scripts/wp eval-file wp-content/plugins/mds-sponsorable-ai-tools/tests/mds3-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

use MillionDollarScript\Extensions\SponsorableAiTools\Main;
use MillionDollarScript\Extensions\SponsorableAiTools\Repository;

if (!class_exists(Main::class) || !class_exists(Repository::class)) {
    fwrite(STDERR, "Sponsorable AI Tools classes are not loaded.\n");
    exit(1);
}

wp_set_current_user(1);
Main::register_post_types();
$main = Main::instance();
$suffix = strtolower(wp_generate_password(8, false, false));
$created_ids = [];
$sent_messages = [];
$fail_recipient = '';
$failure = '';
$mail_filter = static function ($return, $atts) use (&$sent_messages, &$fail_recipient) {
    $recipient = sanitize_email((string) ($atts['to'] ?? ''));
    $sent_messages[] = [
        'to' => $recipient,
        'subject' => (string) ($atts['subject'] ?? ''),
        'message' => (string) ($atts['message'] ?? ''),
    ];

    return $recipient && $recipient === $fail_recipient ? false : true;
};
add_filter('pre_wp_mail', $mail_filter, 10, 2);

try {
    $main->admin_menu();
    global $submenu;
    $menu_slugs = array_map(static function ($item) {
        return (string) ($item[2] ?? '');
    }, (array) ($submenu['mds3'] ?? []));
    if (!in_array('mds-sponsorable-ai-tools', $menu_slugs, true)) {
        throw new RuntimeException('Sponsorable AI Tools did not register its extension submenu.');
    }

    $onboarding = apply_filters('million-dollar-script/extension/onboarding/items', []);
    if (empty($onboarding['mds-sponsorable-ai-tools']['actions']) || empty($onboarding['mds-sponsorable-ai-tools']['legal_documents'])) {
        throw new RuntimeException('Sponsorable AI Tools onboarding is incomplete.');
    }
    $blocks = apply_filters('million-dollar-script/editor/extension/blocks', []);
    $block_names = array_map(static function ($block) {
        return is_array($block) ? (string) ($block['name'] ?? '') : '';
    }, $blocks);
    if (!in_array('mds-sponsorable-ai-tools/tool', $block_names, true)) {
        throw new RuntimeException('Sponsorable Tool block is not registered.');
    }
    if (0 !== Repository::save_tool(['title' => ''])) {
        throw new RuntimeException('A tool without a title should not be saved.');
    }

    $create_tool = static function ($mode, $price, $label) use (&$created_ids, $suffix) {
        $tool_id = Repository::save_tool([
            'title' => 'Fixture ' . $label . ' Tool ' . $suffix,
            'slug' => 'fixture-' . sanitize_title($label) . '-' . $suffix,
            'kind' => 'budget_estimator',
            'description' => 'Fixture browser tool description.',
            'status' => 'active',
            'sponsor_name' => 'Fixture Sponsor',
            'sponsor_url' => 'https://example.test/sponsor',
            'sponsor_label' => 'Presented by',
            'disclosure' => 'Fixture informational and sponsor disclosure.',
            'lead_capture_enabled' => '1',
            'unlock_mode' => $mode,
            'price' => $price,
            'currency' => Repository::default_currency(),
            'contact_email' => 'sat-admin-' . $suffix . '@example.test',
            'terms_url' => 'https://example.test/tool-terms',
            'retention_days' => 365,
            'submission_limit_per_hour' => 10,
        ]);
        if (!$tool_id) {
            throw new RuntimeException('Fixture tool could not be created: ' . $label);
        }
        $created_ids[] = $tool_id;

        return $tool_id;
    };
    $create_lead = static function ($tool_id, $email, $name = 'Fixture Lead') use (&$created_ids) {
        $lead_id = Repository::create_lead($tool_id, [
            'name' => $name,
            'email' => $email,
            'message' => 'Fixture lead note.',
            'result' => 'Fixture saved result with useful planning details.',
            'terms_accepted' => '1',
        ]);
        if (!$lead_id) {
            throw new RuntimeException('Fixture lead could not be created for ' . $email);
        }
        $created_ids[] = $lead_id;

        return $lead_id;
    };

    $free_tool_id = $create_tool('free', 0, 'Free');
    if (!is_wp_error(Repository::create_lead($free_tool_id, ['name' => 'Invalid', 'email' => 'not-an-email', 'result' => 'Result', 'terms_accepted' => '1']))) {
        throw new RuntimeException('Invalid lead email should be rejected.');
    }
    if (!is_wp_error(Repository::create_lead($free_tool_id, ['name' => 'No Result', 'email' => 'no-result@example.test', 'result' => '', 'terms_accepted' => '1']))) {
        throw new RuntimeException('A result delivery request without a generated result should be rejected.');
    }
    $no_consent = Repository::create_lead($free_tool_id, ['name' => 'No Consent', 'email' => 'no-consent@example.test', 'result' => 'Result']);
    if (!is_wp_error($no_consent) || 'mds_sat_terms_required' !== $no_consent->get_error_code()) {
        throw new RuntimeException('Explicit tool and privacy consent should be required.');
    }

    $free_email = 'sat-free-' . $suffix . '@example.test';
    $free_lead_id = $create_lead($free_tool_id, $free_email, 'Free Fixture Lead');
    if (!$main->deliver_lead($free_lead_id)) {
        throw new RuntimeException('Free saved result should deliver immediately.');
    }
    $free_lead = Repository::lead($free_lead_id);
    if ('delivered' !== (string) ($free_lead['delivery_status'] ?? '') || 1 !== absint($free_lead['delivery_attempts'] ?? 0) || empty($free_lead['delivered_at'])) {
        throw new RuntimeException('Free lead delivery state is incomplete.');
    }
    $mail_count = count($sent_messages);
    if (!$main->deliver_lead($free_lead_id) || $mail_count !== count($sent_messages)) {
        throw new RuntimeException('Repeated free delivery should be idempotent.');
    }
    $last_mail = end($sent_messages);
    if ($free_email !== (string) ($last_mail['to'] ?? '') || false === strpos((string) ($last_mail['message'] ?? ''), 'Fixture saved result')) {
        throw new RuntimeException('Delivered email did not contain the recipient and saved result.');
    }

    $manual_tool_id = $create_tool('manual', 12, 'Manual');
    $manual_lead_id = $create_lead($manual_tool_id, 'sat-manual-' . $suffix . '@example.test');
    if ($main->deliver_lead($manual_lead_id)) {
        throw new RuntimeException('Manual result should not deliver before approval.');
    }
    Repository::update_lead_status($manual_lead_id, 'approve');
    if (!$main->deliver_lead($manual_lead_id)) {
        throw new RuntimeException('Approved manual result should deliver.');
    }
    $manual_lead = Repository::lead($manual_lead_id);
    if ('paid' !== (string) ($manual_lead['payment_status'] ?? '') || 'delivered' !== (string) ($manual_lead['delivery_status'] ?? '')) {
        throw new RuntimeException('Manual approval should confirm payment and delivery.');
    }

    $provider_tool_id = $create_tool('provider', 20, 'Provider');
    $moderated_first_id = $create_lead($provider_tool_id, 'sat-moderated-first-' . $suffix . '@example.test');
    Repository::update_lead_status($moderated_first_id, 'approve');
    if ($main->deliver_lead($moderated_first_id)) {
        throw new RuntimeException('Provider result should not deliver before payment.');
    }
    $main->handle_payment_status('sponsorable-ai-tools', $moderated_first_id, 'paid');
    if ('delivered' !== (string) (Repository::lead($moderated_first_id)['delivery_status'] ?? '')) {
        throw new RuntimeException('Moderation-first provider result did not deliver after payment.');
    }

    $paid_first_id = $create_lead($provider_tool_id, 'sat-paid-first-' . $suffix . '@example.test');
    $main->handle_payment_status('sponsorable-ai-tools', $paid_first_id, 'paid');
    if ('pending' !== (string) (Repository::lead($paid_first_id)['delivery_status'] ?? '')) {
        throw new RuntimeException('Payment-first provider result should remain pending moderation.');
    }
    Repository::update_lead_status($paid_first_id, 'approve');
    if (!$main->deliver_lead($paid_first_id)) {
        throw new RuntimeException('Payment-first provider result did not deliver after moderation.');
    }

    $refunded_id = $create_lead($provider_tool_id, 'sat-refunded-' . $suffix . '@example.test');
    $main->handle_payment_status('sponsorable-ai-tools', $refunded_id, 'refunded');
    Repository::update_lead_status($refunded_id, 'approve');
    if ($main->deliver_lead($refunded_id) || 'cancelled' !== (string) (Repository::lead($refunded_id)['delivery_status'] ?? '')) {
        throw new RuntimeException('Refunded provider request should not deliver.');
    }

    $fail_recipient = 'sat-mail-failure-' . $suffix . '@example.test';
    $failed_delivery_id = $create_lead($free_tool_id, $fail_recipient);
    if ($main->deliver_lead($failed_delivery_id)) {
        throw new RuntimeException('Simulated mail failure should not report delivery.');
    }
    $failed_lead = Repository::lead($failed_delivery_id);
    if ('failed' !== (string) ($failed_lead['delivery_status'] ?? '') || empty($failed_lead['delivery_error'])) {
        throw new RuntimeException('Mail failure should remain visible and retryable.');
    }
    $fail_recipient = '';
    if (!$main->deliver_lead($failed_delivery_id) || 2 !== absint(Repository::lead($failed_delivery_id)['delivery_attempts'] ?? 0)) {
        throw new RuntimeException('Failed delivery should succeed on retry without losing attempt history.');
    }

    $privacy_email = 'sat-privacy-' . $suffix . '@example.test';
    $privacy_id = $create_lead($provider_tool_id, $privacy_email, 'Private Lead');
    Repository::update_lead_checkout($privacy_id, [
        'provider' => 'woocommerce',
        'provider_order_id' => '998877',
        'checkout_url' => 'https://example.test/private-checkout-key',
    ]);
    $exporters = apply_filters('wp_privacy_personal_data_exporters', []);
    $erasers = apply_filters('wp_privacy_personal_data_erasers', []);
    if (!is_callable($exporters['mds-sponsorable-ai-tools']['callback'] ?? null) || !is_callable($erasers['mds-sponsorable-ai-tools']['callback'] ?? null)) {
        throw new RuntimeException('Sponsorable AI Tools privacy callbacks are not registered.');
    }
    $export = $main->privacy_exporter($privacy_email, 1);
    if (empty($export['done']) || 1 !== count($export['data'] ?? []) || !empty($main->privacy_exporter('not-' . $privacy_email, 1)['data'])) {
        throw new RuntimeException('Sponsorable AI Tools privacy export matching is incomplete.');
    }
    if (empty($main->privacy_exporter($privacy_email, 2)['done'])) {
        throw new RuntimeException('Sponsorable AI Tools privacy export pagination did not complete.');
    }
    $erasure = $main->privacy_eraser($privacy_email, 1);
    $erased = Repository::lead($privacy_id);
    if (empty($erasure['items_removed']) || empty($erasure['items_retained']) || empty($erasure['done'])) {
        throw new RuntimeException('Sponsorable AI Tools privacy erasure result is incomplete.');
    }
    if ('' !== (string) ($erased['email'] ?? '') || 'Anonymous lead' !== (string) ($erased['name'] ?? '') || '' !== (string) ($erased['message'] ?? '') || '' !== (string) ($erased['result'] ?? '') || '' !== (string) ($erased['checkout_url'] ?? '')) {
        throw new RuntimeException('Sponsorable AI Tools privacy erasure did not clear personal result data.');
    }
    if ('998877' !== (string) ($erased['provider_order_id'] ?? '') || 'pending' !== (string) ($erased['payment_status'] ?? '')) {
        throw new RuntimeException('Sponsorable AI Tools privacy erasure should retain provider accounting state.');
    }
    $repeat_erasure = $main->privacy_eraser($privacy_email, 1);
    if (!empty($repeat_erasure['items_removed']) || !empty($repeat_erasure['items_retained']) || empty($repeat_erasure['done'])) {
        throw new RuntimeException('Sponsorable AI Tools privacy erasure should be idempotent.');
    }

    $scale_tool_id = $create_tool('manual', 5, 'Scale');
    for ($index = 1; $index <= 102; $index++) {
        $create_lead($scale_tool_id, 'sat-scale-' . $index . '-' . $suffix . '@example.test', 'Scale Lead ' . $index);
    }
    $scale_tool = Repository::tool($scale_tool_id);
    $scale_page_one = Repository::leads_page($scale_tool_id, 1, 40);
    $scale_page_three = Repository::leads_page($scale_tool_id, 3, 40);
    if (102 !== absint($scale_tool['lead_count'] ?? 0) || 102 !== absint($scale_page_one['total'] ?? 0) || 3 !== absint($scale_page_one['pages'] ?? 0) || 22 !== count($scale_page_three['items'] ?? [])) {
        throw new RuntimeException('Lead counts and pagination should remain exact beyond 100 records.');
    }

    $paged_privacy_email = 'sat-paged-privacy-' . $suffix . '@example.test';
    $paged_privacy_ids = [
        $create_lead($scale_tool_id, $paged_privacy_email, 'Paged Privacy One'),
        $create_lead($scale_tool_id, $paged_privacy_email, 'Paged Privacy Two'),
    ];
    $privacy_page_one = Repository::leads_by_email_page($paged_privacy_email, 1, 1);
    $privacy_page_two = Repository::leads_by_email_page($paged_privacy_email, 2, 1);
    if (2 !== absint($privacy_page_one['pages'] ?? 0) || 1 !== count($privacy_page_one['items'] ?? []) || 1 !== count($privacy_page_two['items'] ?? [])) {
        throw new RuntimeException('Email-based privacy matching should paginate without losing records.');
    }

    update_post_meta($paged_privacy_ids[0], '_mds_sat_private_data_updated_at', time() - (10 * DAY_IN_SECONDS));
    if (1 !== Repository::cleanup_tool_private_data($scale_tool_id, 5, 1)) {
        throw new RuntimeException('Retention cleanup should anonymize expired private lead data.');
    }
    $retained = Repository::lead($paged_privacy_ids[0]);
    if ('' !== (string) ($retained['email'] ?? '') || '' !== (string) ($retained['result'] ?? '') || 'Anonymous lead' !== (string) ($retained['name'] ?? '')) {
        throw new RuntimeException('Retention cleanup left personal lead data behind.');
    }

    $notification_lead = Repository::lead($paged_privacy_ids[1]);
    $mail_count = count($sent_messages);
    MillionDollarScript\Extensions\SponsorableAiTools\Notifications::submission(Repository::tool($scale_tool_id), $notification_lead, admin_url('admin.php?page=mds-sponsorable-ai-tools'));
    if (count($sent_messages) !== $mail_count + 2) {
        throw new RuntimeException('Paid result requests should notify both the configured administrator and requester.');
    }

    $html = $main->shortcode([
        'id' => $free_tool_id,
        'show_form' => 'true',
        'show_sponsor' => 'true',
        'show_disclosure' => 'true',
    ]);
    if (false === strpos($html, 'Fixture Free Tool') || false === strpos($html, 'Fixture Sponsor') || false === strpos($html, 'Email my saved result')) {
        throw new RuntimeException('Sponsorable AI Tools public output is incomplete.');
    }

    do_action('rest_api_init');
    $routes = rest_get_server()->get_routes();
    foreach (['/million-dollar-script/v1/sponsorable-ai-tools/tools', '/million-dollar-script/v1/sponsorable-ai-tools/leads'] as $route) {
        if (empty($routes[$route])) {
            throw new RuntimeException('Missing REST route: ' . $route);
        }
    }
    if (!$main->permission_manage()) {
        throw new RuntimeException('Administrator should be able to read private leads.');
    }
    wp_set_current_user(0);
    if ($main->permission_manage()) {
        throw new RuntimeException('Anonymous visitor should not be able to read private leads.');
    }
    wp_set_current_user(1);

    $manifest = $main->api_endpoint_manifest([]);
    $manifest_by_id = [];
    foreach ($manifest as $entry) {
        if (is_array($entry) && !empty($entry['id'])) {
            $manifest_by_id[(string) $entry['id']] = $entry;
        }
    }
    if ('public_read' !== (string) ($manifest_by_id['sponsorable-ai-tools-read']['minimum_security_level'] ?? '')) {
        throw new RuntimeException('Public tools endpoint security is missing from the API manifest.');
    }
    if ('wp_capability' !== (string) ($manifest_by_id['sponsorable-ai-tools-leads-read']['minimum_security_level'] ?? '')) {
        throw new RuntimeException('Private leads endpoint must require a WordPress capability.');
    }

    $public_tool = Repository::public_tool_payload(Repository::tool($free_tool_id));
    if (!$public_tool || array_key_exists('contact_email', $public_tool) || array_key_exists('lead_count', $public_tool)) {
        throw new RuntimeException('Public tool data exposed private administration fields.');
    }
    $scale_post = get_post($scale_tool_id);
    wp_update_post(['ID' => $scale_tool_id, 'post_date_gmt' => '0000-00-00 00:00:00']);
    if (empty(Repository::tool($scale_tool_id)['title']) || empty(Repository::lead($paged_privacy_ids[1])['created_at'])) {
        throw new RuntimeException('Tool and lead payloads should remain valid when WordPress has no GMT post date.');
    }
    if ($scale_post) {
        wp_update_post(['ID' => $scale_tool_id, 'post_date_gmt' => $scale_post->post_date_gmt]);
    }
} catch (Throwable $error) {
    $failure = $error->getMessage();
} finally {
    remove_filter('pre_wp_mail', $mail_filter, 10);
    wp_set_current_user(1);
    foreach (array_reverse(array_unique(array_filter(array_map('absint', $created_ids)))) as $post_id) {
        wp_delete_post($post_id, true);
    }
}

if ($failure) {
    fwrite(STDERR, "Sponsorable AI Tools fixture failed: " . $failure . "\n");
    exit(1);
}

echo "Success: Sponsorable AI Tools fixture passed.\n";
