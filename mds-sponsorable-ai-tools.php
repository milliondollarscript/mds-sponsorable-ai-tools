<?php
/**
 * Plugin Name: Million Dollar Script - Sponsorable AI Tools
 * Plugin URI: https://milliondollarscript.com/extensions/sponsorable-ai-tools
 * Description: Launch sponsor-supported calculators, planners, quizzes, generators, and guides with lead capture and moderation.
 * Version: 0.2.0
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * Text Domain: mds-sponsorable-ai-tools
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.1.3
 * Requires PHP: 8.1
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: million-dollar-script
 *
 * Requires MDS: 3.0.0
 * Requires MDS API: 1
 * Requires License: No
 * MDS Provides: inventory.sponsorable-tools lead-capture.tools
 * MDS Requires: platform.core
 * MDS Recommends: mds-revenue-agent mds-woocommerce
 * MDS Requires Service: no
 * MDS Setup Category: monetization
 * MDS Minimum Security Level: api_key_read
 * MDS LLM Safe Actions: read_tools
 *
 * @package MillionDollarScript\Extensions\SponsorableAiTools
 */

namespace MillionDollarScript\Extensions\SponsorableAiTools;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MDS_SPONSORABLE_AI_TOOLS_VERSION')) {
    define('MDS_SPONSORABLE_AI_TOOLS_VERSION', '0.2.0');
}

if (!defined('MDS_SPONSORABLE_AI_TOOLS_FILE')) {
    define('MDS_SPONSORABLE_AI_TOOLS_FILE', __FILE__);
}

if (!defined('MDS_SPONSORABLE_AI_TOOLS_PATH')) {
    define('MDS_SPONSORABLE_AI_TOOLS_PATH', plugin_dir_path(__FILE__));
}

if (!defined('MDS_SPONSORABLE_AI_TOOLS_URL')) {
    define('MDS_SPONSORABLE_AI_TOOLS_URL', plugin_dir_url(__FILE__));
}

if (!defined('MDS_SPONSORABLE_AI_TOOLS_BASENAME')) {
    define('MDS_SPONSORABLE_AI_TOOLS_BASENAME', plugin_basename(__FILE__));
}

function mds_sponsorable_ai_tools_check_mds_active() {
    return class_exists('\\MillionDollarScript\\Core\\Runtime')
        && \MillionDollarScript\Core\Runtime::is_ready();
}

function mds_sponsorable_ai_tools_missing_mds_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('Million Dollar Script - Sponsorable AI Tools', 'mds-sponsorable-ai-tools') . '</strong>: ';
    echo esc_html__('This extension requires the Million Dollar Script plugin to be installed and activated.', 'mds-sponsorable-ai-tools');
    echo ' <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Manage Plugins', 'mds-sponsorable-ai-tools') . '</a>';
    echo '</p></div>';
}

function mds_sponsorable_ai_tools_load_files() {
    require_once MDS_SPONSORABLE_AI_TOOLS_PATH . 'includes/Repository.php';
    require_once MDS_SPONSORABLE_AI_TOOLS_PATH . 'includes/Notifications.php';
    require_once MDS_SPONSORABLE_AI_TOOLS_PATH . 'includes/Privacy.php';
    require_once MDS_SPONSORABLE_AI_TOOLS_PATH . 'includes/Retention.php';
    require_once MDS_SPONSORABLE_AI_TOOLS_PATH . 'includes/Main.php';
}

function mds_sponsorable_ai_tools_init() {
    if (!mds_sponsorable_ai_tools_check_mds_active()) {
        add_action('admin_notices', __NAMESPACE__ . '\\mds_sponsorable_ai_tools_missing_mds_notice');
        return;
    }

    mds_sponsorable_ai_tools_load_files();
    Main::instance()->register();
}

function mds_sponsorable_ai_tools_register_extension() {
    if (!mds_sponsorable_ai_tools_check_mds_active()) {
        return;
    }

    if (class_exists('\\MillionDollarScript\\Extensions\\Registry')) {
        \MillionDollarScript\Extensions\Registry::register([
            'id' => 'mds-sponsorable-ai-tools',
            'name' => 'Sponsorable AI Tools',
            'description' => 'Launch small sponsor-supported tools with lead capture and moderation.',
            'version' => MDS_SPONSORABLE_AI_TOOLS_VERSION,
            'author' => 'Million Dollar Script',
            'url' => 'https://milliondollarscript.com/extensions/sponsorable-ai-tools',
            'main_file' => __FILE__,
            'premium' => false,
            'license_required' => false,
            'cleanup' => [
                'description' => 'Sponsorable tool definitions, leads, and extension settings.',
            ],
            'capabilities' => [
                'admin_pages' => true,
                'frontend_assets' => true,
                'lead_capture' => true,
                'api' => true,
            ],
        ]);
    }
}

function mds_sponsorable_ai_tools_activate() {
    if (!mds_sponsorable_ai_tools_check_mds_active()) {
        deactivate_plugins(MDS_SPONSORABLE_AI_TOOLS_BASENAME);
        wp_die(
            esc_html__('Million Dollar Script - Sponsorable AI Tools requires the Million Dollar Script plugin to be installed and activated.', 'mds-sponsorable-ai-tools'),
            esc_html__('Plugin Activation Error', 'mds-sponsorable-ai-tools'),
            ['back_link' => true]
        );
    }

    mds_sponsorable_ai_tools_load_files();
    Main::register_post_types();
    Repository::maybe_seed();
    Retention::activate();
    flush_rewrite_rules();
}

function mds_sponsorable_ai_tools_deactivate() {
    require_once MDS_SPONSORABLE_AI_TOOLS_PATH . 'includes/Retention.php';
    Retention::deactivate();
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, __NAMESPACE__ . '\\mds_sponsorable_ai_tools_activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\mds_sponsorable_ai_tools_deactivate');

add_action('plugins_loaded', __NAMESPACE__ . '\\mds_sponsorable_ai_tools_init', 20);
add_action('million-dollar-script/register/extensions', __NAMESPACE__ . '\\mds_sponsorable_ai_tools_register_extension');
