<?php
/**
 * Scheduled private lead-data cleanup.
 *
 * @package MillionDollarScript\Extensions\SponsorableAiTools
 */

namespace MillionDollarScript\Extensions\SponsorableAiTools;

if (!defined('ABSPATH')) {
    exit;
}

final class Retention {

    public const HOOK = 'mds_sponsorable_ai_tools_privacy_cleanup';

    public static function register() {
        add_action(self::HOOK, [__CLASS__, 'run']);
        add_action('init', [__CLASS__, 'schedule']);
    }

    public static function activate() {
        self::schedule();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public static function run() {
        $page = 1;
        do {
            $tools = Repository::tools_page($page, 50);
            foreach ($tools['items'] as $tool) {
                $iterations = 0;
                do {
                    $changed = Repository::cleanup_tool_private_data(
                        absint($tool['id'] ?? 0),
                        max(1, absint($tool['retention_days'] ?? 365))
                    );
                    $iterations++;
                } while ($changed > 0 && $iterations < 20);
            }
            $page++;
        } while ($page <= absint($tools['pages'] ?? 1));
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }
}
