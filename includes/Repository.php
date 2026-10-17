<?php
/**
 * Sponsorable AI Tools repository.
 *
 * @package MillionDollarScript\Extensions\SponsorableAiTools
 */

namespace MillionDollarScript\Extensions\SponsorableAiTools;

if (!defined('ABSPATH')) {
    exit;
}

final class Repository {

    public const TOOL_POST_TYPE = 'mds_sat_tool';
    public const LEAD_POST_TYPE = 'mds_sat_lead';

    public static function maybe_seed() {
        if (self::tools(1)) {
            return;
        }

        self::save_tool([
            'title' => __('Launch Budget Estimator', 'mds-sponsorable-ai-tools'),
            'slug' => 'launch-budget-estimator',
            'kind' => 'budget_estimator',
            'description' => __('Help visitors estimate a simple sponsorship budget and daily campaign pacing.', 'mds-sponsorable-ai-tools'),
            'status' => 'active',
            'sponsor_name' => __('Launch Partner', 'mds-sponsorable-ai-tools'),
            'sponsor_url' => home_url('/'),
            'sponsor_label' => __('Presented by a launch sponsor', 'mds-sponsorable-ai-tools'),
            'disclosure' => __('This tool is informational and sponsor-supported. It does not provide financial, legal, medical, tax, or regulated advice.', 'mds-sponsorable-ai-tools'),
            'lead_capture_enabled' => '1',
            'unlock_mode' => 'free',
            'price' => 0,
            'currency' => self::default_currency(),
            'contact_email' => get_option('admin_email'),
            'terms_url' => '',
            'retention_days' => 365,
            'submission_limit_per_hour' => 10,
        ]);
    }

    public static function register_post_types() {
        register_post_type(self::TOOL_POST_TYPE, [
            'label' => __('Sponsorable AI Tools', 'mds-sponsorable-ai-tools'),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type(self::LEAD_POST_TYPE, [
            'label' => __('Sponsorable Tool Leads', 'mds-sponsorable-ai-tools'),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function save_tool(array $data, $tool_id = 0) {
        $tool_id = absint($tool_id);
        $title = self::bounded_text($data['title'] ?? '', 120);
        if ('' === $title) {
            return 0;
        }

        $postarr = [
            'post_type' => self::TOOL_POST_TYPE,
            'post_title' => $title,
            'post_content' => self::bounded_textarea($data['description'] ?? '', 5000),
            'post_status' => 'publish',
        ];

        $result = $tool_id
            ? wp_update_post(array_merge($postarr, ['ID' => $tool_id]), true)
            : wp_insert_post($postarr, true);

        if (is_wp_error($result) || !$result) {
            return 0;
        }

        $tool_id = absint($result);
        $kind = sanitize_key((string) ($data['kind'] ?? 'budget_estimator'));
        if (!isset(self::tool_kinds()[$kind])) {
            $kind = 'budget_estimator';
        }

        $status = sanitize_key((string) ($data['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'active', 'paused', 'archived'], true)) {
            $status = 'draft';
        }

        $unlock_mode = sanitize_key((string) ($data['unlock_mode'] ?? 'free'));
        if (!in_array($unlock_mode, ['free', 'manual', 'provider'], true)) {
            $unlock_mode = 'free';
        }

        $meta = [
            '_mds_sat_slug' => sanitize_title((string) ($data['slug'] ?? $title)) ?: 'tool-' . $tool_id,
            '_mds_sat_kind' => $kind,
            '_mds_sat_status' => $status,
            '_mds_sat_sponsor_name' => self::bounded_text($data['sponsor_name'] ?? '', 120),
            '_mds_sat_sponsor_url' => self::http_url($data['sponsor_url'] ?? ''),
            '_mds_sat_sponsor_label' => self::bounded_text($data['sponsor_label'] ?? '', 120),
            '_mds_sat_disclosure' => self::bounded_textarea($data['disclosure'] ?? '', 2000),
            '_mds_sat_lead_capture_enabled' => !empty($data['lead_capture_enabled']) ? '1' : '0',
            '_mds_sat_unlock_mode' => $unlock_mode,
            '_mds_sat_price' => self::money($data['price'] ?? 0),
            '_mds_sat_currency' => self::effective_currency((string) ($data['currency'] ?? self::default_currency())),
            '_mds_sat_contact_email' => self::valid_contact_email($data['contact_email'] ?? ''),
            '_mds_sat_terms_url' => self::http_url($data['terms_url'] ?? ''),
            '_mds_sat_retention_days' => min(3650, max(1, absint($data['retention_days'] ?? 365))),
            '_mds_sat_submission_limit_per_hour' => min(500, max(1, absint($data['submission_limit_per_hour'] ?? 10))),
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($tool_id, $key, $value);
        }

        return $tool_id;
    }

    public static function tool($tool_id) {
        $post = get_post(absint($tool_id));
        if (!$post || self::TOOL_POST_TYPE !== $post->post_type) {
            return null;
        }

        return self::tool_payload($post);
    }

    public static function tool_by_slug($slug) {
        $slug = sanitize_title((string) $slug);
        if (!$slug) {
            return null;
        }

        $posts = get_posts([
            'post_type' => self::TOOL_POST_TYPE,
            'post_status' => 'publish',
            'meta_key' => '_mds_sat_slug',
            'meta_value' => $slug,
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);

        return $posts ? self::tool_payload($posts[0]) : null;
    }

    public static function tools($limit = 50, $public_only = false) {
        return self::tools_page(1, $limit, $public_only)['items'];
    }

    public static function tools_page($page = 1, $per_page = 20, $public_only = false) {
        $page = max(1, absint($page));
        $per_page = min(100, max(1, absint($per_page)));
        $args = [
            'post_type' => self::TOOL_POST_TYPE,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'no_found_rows' => false,
        ];
        if ($public_only) {
            $args['meta_query'] = [[
                'key' => '_mds_sat_status',
                'value' => 'active',
            ]];
        }
        $query = new \WP_Query($args);
        $tools = array_values(array_filter(array_map([__CLASS__, 'tool_payload'], $query->posts)));
        if ($public_only) {
            $tools = array_values(array_filter(array_map([__CLASS__, 'public_tool_payload'], $tools)));
        }

        return self::collection($tools, $query->found_posts, $page, $per_page);
    }

    public static function create_lead($tool_id, array $data) {
        $tool = self::tool($tool_id);
        if (!$tool || 'active' !== (string) ($tool['status'] ?? '') || empty($tool['lead_capture_enabled'])) {
            return new \WP_Error('mds_sat_closed', __('This tool is not accepting result requests.', 'mds-sponsorable-ai-tools'));
        }

        $email = sanitize_email((string) ($data['email'] ?? ''));
        $name = self::bounded_text($data['name'] ?? '', 80);
        $message = self::bounded_textarea($data['message'] ?? '', 2000);
        $result = self::bounded_textarea($data['result'] ?? '', 4000);
        if (!$email || !is_email($email) || '' === $name || '' === $result) {
            return new \WP_Error('mds_sat_invalid', __('Add a valid name, email address, and generated result.', 'mds-sponsorable-ai-tools'));
        }
        if (empty($data['terms_accepted'])) {
            return new \WP_Error('mds_sat_terms_required', __('Accept the tool, delivery, and privacy terms before submitting.', 'mds-sponsorable-ai-tools'));
        }

        $lead_id = wp_insert_post([
            'post_type' => self::LEAD_POST_TYPE,
            'post_title' => sprintf(__('Lead for %s', 'mds-sponsorable-ai-tools'), $tool['title']),
            'post_content' => $message,
            'post_status' => 'pending',
        ], true);

        if (is_wp_error($lead_id) || !$lead_id) {
            return new \WP_Error('mds_sat_create_failed', __('The saved-result request could not be created.', 'mds-sponsorable-ai-tools'));
        }

        $lead_id = absint($lead_id);
        $unlock_mode = sanitize_key((string) ($tool['unlock_mode'] ?? 'free'));
        $amount = 'free' !== $unlock_mode ? self::money($tool['price'] ?? 0) : 0.0;
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $meta = [
            '_mds_sat_tool_id' => absint($tool_id),
            '_mds_sat_name' => $name,
            '_mds_sat_email' => $email,
            '_mds_sat_result' => $result,
            '_mds_sat_status' => 'pending_review',
            '_mds_sat_unlock_mode' => $unlock_mode,
            '_mds_sat_amount' => $amount,
            '_mds_sat_currency' => self::effective_currency($tool['currency'] ?? self::default_currency()),
            '_mds_sat_payment_status' => $amount > 0 ? 'requested' : 'not_required',
            '_mds_sat_delivery_status' => 'pending',
            '_mds_sat_delivery_attempts' => 0,
            '_mds_sat_delivered_at' => '',
            '_mds_sat_delivery_error' => '',
            '_mds_sat_terms_accepted_at' => current_time('mysql', true),
            '_mds_sat_private_data_updated_at' => time(),
            '_mds_sat_ip_hash' => hash_hmac('sha256', $ip ?: 'unknown', wp_salt('nonce')),
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($lead_id, $key, $value);
        }

        return $lead_id;
    }

    public static function leads($tool_id = 0, $limit = 100) {
        return self::leads_page($tool_id, 1, $limit)['items'];
    }

    public static function leads_page($tool_id = 0, $page = 1, $per_page = 20, array $args = []) {
        $page = max(1, absint($page));
        $per_page = min(100, max(1, absint($per_page)));
        $meta_query = [];
        if ($tool_id) {
            $meta_query[] = [
                'key' => '_mds_sat_tool_id',
                'value' => absint($tool_id),
            ];
        }

        if (!empty($args['status'])) {
            $status = sanitize_key((string) $args['status']);
            if (in_array($status, ['pending_review', 'approved', 'rejected'], true)) {
                $meta_query[] = [
                    'key' => '_mds_sat_status',
                    'value' => $status,
                ];
            }
        }
        $query_args = [
            'post_type' => self::LEAD_POST_TYPE,
            'post_status' => ['publish', 'pending', 'draft'],
            'meta_query' => $meta_query,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false,
        ];
        $search = self::bounded_text($args['search'] ?? '', 80);
        if ($search) {
            $query_args['s'] = $search;
        }
        $query = new \WP_Query($query_args);

        return self::collection(
            array_values(array_filter(array_map([__CLASS__, 'lead_payload'], $query->posts))),
            $query->found_posts,
            $page,
            $per_page
        );
    }

    public static function leads_by_email($email_address, $page = 1, $per_page = 100) {
        return self::leads_by_email_page($email_address, $page, $per_page)['items'];
    }

    public static function leads_by_email_page($email_address, $page = 1, $per_page = 50) {
        $email = sanitize_email((string) $email_address);
        $page = max(1, absint($page));
        $per_page = min(100, max(1, absint($per_page)));
        if (!$email) {
            return self::empty_collection($page, $per_page);
        }

        $query = new \WP_Query([
            'post_type' => self::LEAD_POST_TYPE,
            'post_status' => array_keys(get_post_stati()),
            'meta_key' => '_mds_sat_email',
            'meta_value' => $email,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => false,
        ]);

        return self::collection(
            array_values(array_filter(array_map([__CLASS__, 'lead_payload'], $query->posts))),
            $query->found_posts,
            $page,
            $per_page
        );
    }

    public static function update_lead_status($lead_id, $status) {
        $lead = self::lead($lead_id);
        if (!$lead) {
            return false;
        }

        $status = sanitize_key((string) $status);
        if ('approve' === $status) {
            wp_update_post(['ID' => absint($lead_id), 'post_status' => 'publish']);
            update_post_meta($lead_id, '_mds_sat_status', 'approved');
            if ('manual' === (string) ($lead['unlock_mode'] ?? '') && (float) ($lead['amount'] ?? 0) > 0) {
                update_post_meta($lead_id, '_mds_sat_payment_status', 'paid');
            }
            return true;
        }
        if ('reject' === $status) {
            wp_update_post(['ID' => absint($lead_id), 'post_status' => 'draft']);
            update_post_meta($lead_id, '_mds_sat_status', 'rejected');
            if ('delivered' !== (string) ($lead['delivery_status'] ?? '')) {
                update_post_meta($lead_id, '_mds_sat_delivery_status', 'cancelled');
            }
            return true;
        }

        return false;
    }

    public static function update_lead_payment_status($lead_id, $status) {
        $lead = self::lead($lead_id);
        if (!$lead) {
            return false;
        }

        $status = sanitize_key((string) $status);
        if (!in_array($status, ['requested', 'pending', 'processing', 'paid', 'completed', 'failed', 'cancelled', 'expired', 'refunded', 'denied', 'not_required'], true)) {
            return false;
        }

        update_post_meta(absint($lead_id), '_mds_sat_payment_status', $status);
        if (in_array($status, ['failed', 'cancelled', 'expired', 'refunded', 'denied'], true) && 'delivered' !== (string) ($lead['delivery_status'] ?? '')) {
            update_post_meta(absint($lead_id), '_mds_sat_delivery_status', 'cancelled');
        } elseif (in_array($status, ['paid', 'completed', 'processing', 'not_required'], true) && 'cancelled' === (string) ($lead['delivery_status'] ?? '')) {
            update_post_meta(absint($lead_id), '_mds_sat_delivery_status', 'pending');
        }
        return true;
    }

    public static function update_lead_checkout($lead_id, array $checkout) {
        $lead = self::lead($lead_id);
        if (!$lead) {
            return false;
        }

        $provider = sanitize_key((string) ($checkout['provider'] ?? ''));
        $provider_order_id = sanitize_text_field((string) ($checkout['provider_order_id'] ?? ''));
        $checkout_url = self::http_url($checkout['checkout_url'] ?? '');
        if ($provider) {
            update_post_meta(absint($lead_id), '_mds_sat_payment_provider', $provider);
        }
        if ($provider_order_id) {
            update_post_meta(absint($lead_id), '_mds_sat_provider_order_id', $provider_order_id);
            self::update_lead_payment_status($lead_id, 'pending');
        }
        if ($checkout_url) {
            update_post_meta(absint($lead_id), '_mds_sat_checkout_url', $checkout_url);
        }

        return true;
    }

    public static function begin_lead_delivery($lead_id) {
        $lead = self::lead($lead_id);
        if (!$lead || 'delivered' === (string) ($lead['delivery_status'] ?? '')) {
            return false;
        }

        $lock = wp_generate_password(24, false, false);
        if (!add_post_meta(absint($lead_id), '_mds_sat_delivery_lock', $lock, true)) {
            return false;
        }

        update_post_meta(absint($lead_id), '_mds_sat_delivery_status', 'delivering');
        update_post_meta(absint($lead_id), '_mds_sat_delivery_attempts', absint($lead['delivery_attempts'] ?? 0) + 1);
        update_post_meta(absint($lead_id), '_mds_sat_delivery_error', '');

        return $lock;
    }

    public static function finish_lead_delivery($lead_id, $lock, $delivered, $error = '') {
        $lead_id = absint($lead_id);
        $stored_lock = (string) get_post_meta($lead_id, '_mds_sat_delivery_lock', true);
        if (!$lead_id || !$stored_lock || !hash_equals($stored_lock, (string) $lock)) {
            return false;
        }

        if ($delivered) {
            update_post_meta($lead_id, '_mds_sat_delivery_status', 'delivered');
            update_post_meta($lead_id, '_mds_sat_delivered_at', current_time('mysql', true));
            update_post_meta($lead_id, '_mds_sat_delivery_error', '');
        } else {
            update_post_meta($lead_id, '_mds_sat_delivery_status', 'failed');
            update_post_meta($lead_id, '_mds_sat_delivery_error', sanitize_text_field((string) $error));
        }
        delete_post_meta($lead_id, '_mds_sat_delivery_lock');

        return true;
    }

    public static function anonymize_lead($lead_id) {
        $lead = self::lead($lead_id);
        if (!$lead) {
            return false;
        }

        $anonymous_name = __('Anonymous lead', 'mds-sponsorable-ai-tools');
        $updated = wp_update_post([
            'ID' => absint($lead_id),
            'post_title' => $anonymous_name,
            'post_content' => '',
        ], true);
        if (is_wp_error($updated)) {
            return false;
        }

        update_post_meta(absint($lead_id), '_mds_sat_name', $anonymous_name);
        update_post_meta(absint($lead_id), '_mds_sat_email', '');
        update_post_meta(absint($lead_id), '_mds_sat_result', '');
        update_post_meta(absint($lead_id), '_mds_sat_checkout_url', '');
        update_post_meta(absint($lead_id), '_mds_sat_ip_hash', '');
        update_post_meta(absint($lead_id), '_mds_sat_personal_data_erased_at', current_time('mysql', true));
        update_post_meta(absint($lead_id), '_mds_sat_private_data_updated_at', time());
        delete_post_meta(absint($lead_id), '_mds_sat_delivery_lock');

        return true;
    }

    public static function cleanup_tool_private_data($tool_id, $retention_days, $limit = 100) {
        $cutoff = time() - (max(1, absint($retention_days)) * DAY_IN_SECONDS);
        $query = new \WP_Query([
            'post_type' => self::LEAD_POST_TYPE,
            'post_status' => array_keys(get_post_stati()),
            'posts_per_page' => min(500, max(1, absint($limit))),
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_mds_sat_tool_id', 'value' => absint($tool_id)],
                ['key' => '_mds_sat_email', 'value' => '', 'compare' => '!='],
                ['key' => '_mds_sat_private_data_updated_at', 'value' => $cutoff, 'compare' => '<=', 'type' => 'NUMERIC'],
            ],
        ]);

        $changed = 0;
        foreach ($query->posts as $lead_id) {
            if (self::anonymize_lead($lead_id)) {
                $changed++;
            }
        }

        return $changed;
    }

    public static function lead($lead_id) {
        $post = get_post(absint($lead_id));
        if (!$post || self::LEAD_POST_TYPE !== $post->post_type) {
            return null;
        }

        return self::lead_payload($post);
    }

    public static function tool_kinds() {
        return [
            'budget_estimator' => __('Budget estimator', 'mds-sponsorable-ai-tools'),
            'launch_planner' => __('Launch planner', 'mds-sponsorable-ai-tools'),
            'sponsor_fit_quiz' => __('Sponsor fit quiz', 'mds-sponsorable-ai-tools'),
            'copy_generator' => __('Copy generator', 'mds-sponsorable-ai-tools'),
        ];
    }

    public static function tool_payload($post) {
        $post = is_object($post) ? $post : get_post(absint($post));
        if (!$post) {
            return null;
        }

        return [
            'id' => absint($post->ID),
            'title' => get_the_title($post),
            'slug' => (string) get_post_meta($post->ID, '_mds_sat_slug', true),
            'kind' => (string) get_post_meta($post->ID, '_mds_sat_kind', true),
            'description' => (string) $post->post_content,
            'status' => (string) get_post_meta($post->ID, '_mds_sat_status', true),
            'sponsor_name' => (string) get_post_meta($post->ID, '_mds_sat_sponsor_name', true),
            'sponsor_url' => (string) get_post_meta($post->ID, '_mds_sat_sponsor_url', true),
            'sponsor_label' => (string) get_post_meta($post->ID, '_mds_sat_sponsor_label', true),
            'disclosure' => (string) get_post_meta($post->ID, '_mds_sat_disclosure', true),
            'lead_capture_enabled' => '1' === (string) get_post_meta($post->ID, '_mds_sat_lead_capture_enabled', true),
            'unlock_mode' => (string) get_post_meta($post->ID, '_mds_sat_unlock_mode', true),
            'price' => (float) get_post_meta($post->ID, '_mds_sat_price', true),
            'currency' => (string) get_post_meta($post->ID, '_mds_sat_currency', true),
            'contact_email' => (string) get_post_meta($post->ID, '_mds_sat_contact_email', true) ?: self::valid_contact_email(''),
            'terms_url' => (string) get_post_meta($post->ID, '_mds_sat_terms_url', true),
            'retention_days' => absint(get_post_meta($post->ID, '_mds_sat_retention_days', true)) ?: 365,
            'submission_limit_per_hour' => absint(get_post_meta($post->ID, '_mds_sat_submission_limit_per_hour', true)) ?: 10,
            'lead_count' => self::lead_count($post->ID),
        ];
    }

    public static function lead_payload($post) {
        $post = is_object($post) ? $post : get_post(absint($post));
        if (!$post) {
            return null;
        }

        return [
            'id' => absint($post->ID),
            'tool_id' => absint(get_post_meta($post->ID, '_mds_sat_tool_id', true)),
            'name' => (string) get_post_meta($post->ID, '_mds_sat_name', true),
            'email' => (string) get_post_meta($post->ID, '_mds_sat_email', true),
            'message' => (string) $post->post_content,
            'result' => (string) get_post_meta($post->ID, '_mds_sat_result', true),
            'status' => (string) get_post_meta($post->ID, '_mds_sat_status', true),
            'unlock_mode' => (string) get_post_meta($post->ID, '_mds_sat_unlock_mode', true),
            'amount' => (float) get_post_meta($post->ID, '_mds_sat_amount', true),
            'currency' => (string) get_post_meta($post->ID, '_mds_sat_currency', true),
            'payment_status' => (string) get_post_meta($post->ID, '_mds_sat_payment_status', true),
            'payment_provider' => (string) get_post_meta($post->ID, '_mds_sat_payment_provider', true),
            'provider_order_id' => (string) get_post_meta($post->ID, '_mds_sat_provider_order_id', true),
            'checkout_url' => (string) get_post_meta($post->ID, '_mds_sat_checkout_url', true),
            'delivery_status' => (string) get_post_meta($post->ID, '_mds_sat_delivery_status', true) ?: 'pending',
            'delivery_attempts' => absint(get_post_meta($post->ID, '_mds_sat_delivery_attempts', true)),
            'delivered_at' => (string) get_post_meta($post->ID, '_mds_sat_delivered_at', true),
            'delivery_error' => (string) get_post_meta($post->ID, '_mds_sat_delivery_error', true),
            'terms_accepted_at' => (string) get_post_meta($post->ID, '_mds_sat_terms_accepted_at', true),
            'created_at' => self::post_created_at($post),
        ];
    }

    public static function public_tool_payload($tool) {
        $tool = is_array($tool) ? $tool : self::tool_payload($tool);
        if (!$tool || 'active' !== (string) ($tool['status'] ?? '')) {
            return null;
        }

        return array_intersect_key($tool, array_flip([
            'id', 'title', 'slug', 'kind', 'description', 'status', 'sponsor_name', 'sponsor_url',
            'sponsor_label', 'disclosure', 'lead_capture_enabled', 'unlock_mode', 'price', 'currency', 'terms_url',
        ]));
    }

    public static function money($value) {
        if (class_exists('\\MillionDollarScript\\Commerce\\Currency') && method_exists('\\MillionDollarScript\\Commerce\\Currency', 'amount')) {
            return min(100000000, max(0, (float) \MillionDollarScript\Commerce\Currency::amount($value)));
        }

        return round(min(100000000, max(0, (float) $value)), 2);
    }

    private static function lead_count($tool_id) {
        $query = new \WP_Query([
            'post_type' => self::LEAD_POST_TYPE,
            'post_status' => ['publish', 'pending', 'draft'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_key' => '_mds_sat_tool_id',
            'meta_value' => absint($tool_id),
        ]);

        return absint($query->found_posts);
    }

    public static function default_currency() {
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];

        if (class_exists('\\MillionDollarScript\\Commerce\\Currency')) {
            return \MillionDollarScript\Commerce\Currency::current_code($settings);
        }

        $fallback = !empty($settings['currency']) ? (string) $settings['currency'] : '';
        if (!$fallback && function_exists('get_woocommerce_currency')) {
            $fallback = get_woocommerce_currency();
        }

        return self::currency($fallback ?: 'USD');
    }

    public static function effective_currency($value = '') {
        if (class_exists('\\MillionDollarScript\\Commerce\\Currency') && method_exists('\\MillionDollarScript\\Commerce\\Currency', 'effective_code')) {
            return \MillionDollarScript\Commerce\Currency::effective_code($value);
        }

        if (self::currency_locked()) {
            return self::default_currency();
        }

        return self::currency($value ?: self::default_currency());
    }

    public static function currency_locked() {
        $settings = get_option('mds3_settings', []);
        $settings = is_array($settings) ? $settings : [];

        return class_exists('\\MillionDollarScript\\Commerce\\Currency') && \MillionDollarScript\Commerce\Currency::provider_locks_currency($settings);
    }

    private static function bounded_text($value, $length) {
        $value = sanitize_text_field((string) $value);
        $length = max(1, absint($length));

        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    private static function bounded_textarea($value, $length) {
        $value = sanitize_textarea_field((string) $value);
        $length = max(1, absint($length));

        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    private static function http_url($value) {
        return esc_url_raw(trim((string) $value), ['http', 'https']);
    }

    private static function valid_contact_email($value) {
        $email = sanitize_email((string) $value);
        if (is_email($email)) {
            return $email;
        }

        $fallback = sanitize_email((string) get_option('admin_email'));
        return is_email($fallback) ? $fallback : '';
    }

    private static function post_created_at($post) {
        $date = (string) ($post->post_date_gmt ?? '');
        if ('' === $date || '0000-00-00 00:00:00' === $date) {
            $date = get_gmt_from_date((string) ($post->post_date ?? ''));
        }

        return $date && '0000-00-00 00:00:00' !== $date ? mysql_to_rfc3339($date) : '';
    }

    private static function collection(array $items, $total, $page, $per_page) {
        $total = max(0, absint($total));
        $per_page = max(1, absint($per_page));

        return [
            'items' => array_values($items),
            'total' => $total,
            'page' => max(1, absint($page)),
            'per_page' => $per_page,
            'pages' => max(1, (int) ceil($total / $per_page)),
        ];
    }

    private static function empty_collection($page, $per_page) {
        return self::collection([], 0, $page, $per_page);
    }

    private static function currency($value) {
        if (class_exists('\\MillionDollarScript\\Commerce\\Currency') && method_exists('\\MillionDollarScript\\Commerce\\Currency', 'normalize_code')) {
            return \MillionDollarScript\Commerce\Currency::normalize_code($value, self::default_currency());
        }

        $code = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper(sanitize_text_field((string) $value))), 0, 3));

        return '' !== $code ? $code : 'USD';
    }
}
