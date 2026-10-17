<?php
/** Sponsorable AI Tools uninstall cleanup. */
if (!defined('WP_UNINSTALL_PLUGIN') || !class_exists('\\MillionDollarScript\\Extensions\\CleanupPolicy')) { exit; }
\MillionDollarScript\Extensions\CleanupPolicy::cleanup('mds-sponsorable-ai-tools', [
    'post_types' => ['mds_sat_tool', 'mds_sat_lead'],
    'option_prefixes' => ['mds_sponsorable_ai_tools_'],
    'transient_prefixes' => ['mds_sponsorable_ai_tools_'],
]);
