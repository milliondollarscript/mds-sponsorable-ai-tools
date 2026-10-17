<?php
/**
 * Sponsorable AI Tools runtime, admin UI, shortcode, and REST API.
 *
 * @package MillionDollarScript\Extensions\SponsorableAiTools
 */

namespace MillionDollarScript\Extensions\SponsorableAiTools;

if (!defined('ABSPATH')) {
    exit;
}

final class Main {

    public const REST_NAMESPACE = 'million-dollar-script/v1';
    public const REST_BASE = 'sponsorable-ai-tools';

    private static $instance;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register() {
        add_action('init', [__CLASS__, 'register_post_types'], 5);
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('admin_menu', [$this, 'admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('admin_post_mds_sponsorable_ai_tools_save_tool', [$this, 'handle_save_tool']);
        add_action('admin_post_mds_sponsorable_ai_tools_moderate_lead', [$this, 'handle_moderate_lead']);
        add_action('admin_post_mds_sponsorable_ai_tools_deliver_lead', [$this, 'handle_deliver_lead']);
        add_action('admin_post_mds_sponsorable_ai_tools_submit_lead', [$this, 'handle_submit_lead']);
        add_action('admin_post_nopriv_mds_sponsorable_ai_tools_submit_lead', [$this, 'handle_submit_lead']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('million-dollar-script/payment/source/status', [$this, 'handle_payment_status'], 10, 4);
        add_filter('million-dollar-script/api/endpoint/manifest', [$this, 'api_endpoint_manifest']);
        add_filter('million-dollar-script/extension/onboarding/items', [$this, 'extension_onboarding_items']);
        add_filter('million-dollar-script/editor/extension/blocks', [$this, 'editor_blocks']);
        add_filter('wp_privacy_personal_data_exporters', [$this, 'privacy_exporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'privacy_erasers']);
        Retention::register();
    }

    public static function register_post_types() {
        Repository::register_post_types();
    }

    public function load_textdomain() {
        load_plugin_textdomain('mds-sponsorable-ai-tools', false, dirname(MDS_SPONSORABLE_AI_TOOLS_BASENAME) . '/languages');
    }

    public function register_shortcodes() {
        add_shortcode('mds_sponsorable_ai_tools', [$this, 'shortcode']);
    }

    public function editor_blocks(array $blocks) {
        $blocks[] = [
            'name' => 'mds-sponsorable-ai-tools/tool',
            'title' => __('Sponsorable Tool', 'mds-sponsorable-ai-tools'),
            'description' => __('Display one Million Dollar Script sponsorable tool or the active tool directory.', 'mds-sponsorable-ai-tools'),
            'icon' => 'superhero',
            'category' => 'widgets',
            'shortcode' => 'mds_sponsorable_ai_tools',
            'attributes' => [
                'id' => ['type' => 'number', 'default' => 0],
                'slug' => ['type' => 'string', 'default' => ''],
                'show_form' => ['type' => 'boolean', 'default' => true],
                'show_sponsor' => ['type' => 'boolean', 'default' => true],
                'show_disclosure' => ['type' => 'boolean', 'default' => true],
            ],
            'controls' => [
                [
                    'attribute' => 'id',
                    'type' => 'entity',
                    'label' => __('Tool', 'mds-sponsorable-ai-tools'),
                    'help' => __('Choose a saved tool by numeric ID. Leave this on All active tools to list every active tool, or use Custom tool ID only when you already know the ID.', 'mds-sponsorable-ai-tools'),
                    'empty_label' => __('All active tools', 'mds-sponsorable-ai-tools'),
                    'custom_label' => __('Custom tool ID', 'mds-sponsorable-ai-tools'),
                    'custom_help' => __('Enter the numeric database ID for a tool that is not shown in the list. This is different from the tool slug.', 'mds-sponsorable-ai-tools'),
                    'options' => $this->block_entity_options(Repository::tools(100), __('Untitled tool', 'mds-sponsorable-ai-tools')),
                ],
                [
                    'attribute' => 'slug',
                    'type' => 'text',
                    'label' => __('Tool slug', 'mds-sponsorable-ai-tools'),
                    'help' => __('Optional URL-safe tool slug used only when no specific tool ID is selected. Leave blank unless you want this block to find one tool by slug.', 'mds-sponsorable-ai-tools'),
                ],
                [
                    'attribute' => 'show_form',
                    'type' => 'toggle',
                    'label' => __('Show tool form', 'mds-sponsorable-ai-tools'),
                    'help' => __('Display the visitor input form and lead capture flow.', 'mds-sponsorable-ai-tools'),
                ],
                [
                    'attribute' => 'show_sponsor',
                    'type' => 'toggle',
                    'label' => __('Show sponsor label', 'mds-sponsorable-ai-tools'),
                    'help' => __('Display the configured sponsor name and sponsor link.', 'mds-sponsorable-ai-tools'),
                ],
                [
                    'attribute' => 'show_disclosure',
                    'type' => 'toggle',
                    'label' => __('Show disclosure text', 'mds-sponsorable-ai-tools'),
                    'help' => __('Display the configured informational and sponsor disclosure.', 'mds-sponsorable-ai-tools'),
                ],
            ],
            'preview' => [
                'title' => __('Sponsorable Tool', 'mds-sponsorable-ai-tools'),
                'description' => __('Shows a sponsor-supported calculator, planner, quiz, generator, or guide with lead capture controls.', 'mds-sponsorable-ai-tools'),
                'rows' => [
                    __('Tool title, template, and sponsor label', 'mds-sponsorable-ai-tools'),
                    __('Optional visitor input form', 'mds-sponsorable-ai-tools'),
                    __('Optional disclosure and lead capture notice', 'mds-sponsorable-ai-tools'),
                ],
            ],
            'render_callback' => [$this, 'render_editor_block'],
        ];

        return $blocks;
    }

    private function block_entity_options(array $items, $fallback_title) {
        $options = [];
        foreach ($items as $item) {
            $id = absint($item['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $label = sprintf('#%d %s', $id, (string) ($item['title'] ?? $fallback_title));
            if (!empty($item['status'])) {
                $label .= ' - ' . ucfirst((string) $item['status']);
            }

            $options[] = [
                'label' => $label,
                'value' => (string) $id,
            ];
        }

        return $options;
    }

    public function render_editor_block(array $attributes = []) {
        return $this->shortcode([
            'id' => absint($attributes['id'] ?? 0),
            'slug' => sanitize_title((string) ($attributes['slug'] ?? '')),
            'show_form' => $this->truthy($attributes['show_form'] ?? true) ? 'true' : 'false',
            'show_sponsor' => $this->truthy($attributes['show_sponsor'] ?? true) ? 'true' : 'false',
            'show_disclosure' => $this->truthy($attributes['show_disclosure'] ?? true) ? 'true' : 'false',
        ]);
    }

    public function admin_menu() {
        add_submenu_page(
            $this->admin_parent_slug(),
            __('Sponsorable AI Tools', 'mds-sponsorable-ai-tools'),
            __('Sponsorable AI Tools', 'mds-sponsorable-ai-tools'),
            'manage_options',
            'mds-sponsorable-ai-tools',
            [$this, 'admin_page']
        );
    }

    public function admin_assets($hook) {
        if (false === strpos((string) $hook, 'mds-sponsorable-ai-tools')) {
            return;
        }

        if (class_exists('\MillionDollarScript\Core\Runtime')) {
            wp_enqueue_style('million-dollar-script-admin', \MillionDollarScript\Core\Runtime::url('assets/mds3/') . 'css/admin.css', [], \MillionDollarScript\Core\Runtime::version());
            wp_enqueue_script('million-dollar-script-admin', \MillionDollarScript\Core\Runtime::url('assets/mds3/') . 'js/admin.js', [], \MillionDollarScript\Core\Runtime::version(), true);
        }
        wp_enqueue_style('mds-sponsorable-ai-tools-admin', MDS_SPONSORABLE_AI_TOOLS_URL . 'assets/css/admin.css', [], MDS_SPONSORABLE_AI_TOOLS_VERSION);
    }

    public function extension_onboarding_items(array $items) {
        $items['mds-sponsorable-ai-tools'] = [
            'name' => __('Sponsorable AI Tools', 'mds-sponsorable-ai-tools'),
            'summary' => __('Review tool disclaimers, sponsor placement rules, lead-capture privacy, and AI/automation limits before publishing interactive tools.', 'mds-sponsorable-ai-tools'),
            'priority' => 50,
            'actions' => [
                ['label' => __('Open Sponsorable AI Tools', 'mds-sponsorable-ai-tools'), 'url' => admin_url('admin.php?page=mds-sponsorable-ai-tools'), 'primary' => true],
            ],
            'legal_documents' => [
                [
                    'slug' => 'tool-sponsor-privacy-terms',
                    'title' => __('Sponsorable Tool Privacy and Sponsor Terms', 'mds-sponsorable-ai-tools'),
                    'description' => __('Terms for sponsor-supported tools, lead capture, AI-assisted outputs, disclaimers, moderation, and data retention.', 'mds-sponsorable-ai-tools'),
                    'page_slug' => 'sponsorable-tool-privacy-sponsor-terms',
                    'content' => $this->sponsorable_tools_legal_content(),
                ],
            ],
        ];

        return $items;
    }

    private function sponsorable_tools_legal_content() {
        return '<h2>' . esc_html__('Sponsorable Tool Privacy and Sponsor Terms', 'mds-sponsorable-ai-tools') . '</h2>'
            . '<p>' . esc_html__('These terms apply when {{site_name}} publishes sponsor-supported tools, including calculators, quizzes, planners, generators, guides, calls to action, lead-capture forms, sponsor messages, and optional automated or AI-assisted outputs.', 'mds-sponsorable-ai-tools') . '</p>'
            . '<h3>' . esc_html__('Tool Output and Sponsor Disclaimers', 'mds-sponsorable-ai-tools') . '</h3>'
            . '<p>' . esc_html__('Tool outputs are informational previews and may be incomplete, inaccurate, outdated, delayed, generalized, or unsuitable for a specific user. They do not provide legal, financial, tax, medical, investment, employment, safety, or other regulated professional advice. Sponsor logos, prompts, highlighted results, saved-result delivery, reviewed follow-up, recommended next steps, or calls to action may be paid placements and should be treated as advertising unless clearly stated otherwise.', 'mds-sponsorable-ai-tools') . '</p>'
            . '<h3>' . esc_html__('Saved Results, Review, and Payment', 'mds-sponsorable-ai-tools') . '</h3>'
            . '<p>' . esc_html__('A browser preview may be available before a visitor requests delivery. Free saved results are emailed after a valid request. Manual delivery requires site review and any stated offline payment. Payment-provider delivery requires both successful payment and administrator approval before email delivery. Failed, cancelled, expired, denied, or refunded payments prevent undelivered requests from being sent. Email delivery is not guaranteed when an address is invalid or the site mail service is unavailable.', 'mds-sponsorable-ai-tools') . '</p>'
            . '<h3>' . esc_html__('Lead Capture, Retention, and Moderation', 'mds-sponsorable-ai-tools') . '</h3>'
            . '<p>' . esc_html__('Tools may collect names, email addresses, business details, answers, preferences, generated outputs, sponsor choices, consent records, payment references, timestamps, and technical request data. This data may be used to provide the tool, unlock results, contact users when requested, route qualified leads, measure sponsor performance, prevent abuse, improve tools, handle disputes, and maintain audit records.', 'mds-sponsorable-ai-tools') . '</p>'
            . '<h3>' . esc_html__('Sponsor and Third-Party Processing', 'mds-sponsorable-ai-tools') . '</h3>'
            . '<p>' . esc_html__('When a tool is sponsored or includes lead delivery, relevant lead data may be shared with the sponsor, payment gateway, email provider, analytics provider, automation provider, hosting provider, or security service needed to operate that tool. Sponsors are responsible for their own follow-up, privacy compliance, advertising claims, and use of any lead information they receive.', 'mds-sponsorable-ai-tools') . '</p>'
            . '<h3>' . esc_html__('Moderation, Limits, and Removal', 'mds-sponsorable-ai-tools') . '</h3>'
            . '<p>' . esc_html__('{{site_name}} may limit tool use, reject sponsor creative, remove unsafe prompts or outputs, block automated abuse, disable lead delivery, change unlock rules, correct inaccurate public text, or remove tools that create legal, security, payment, reputation, or operational risk.', 'mds-sponsorable-ai-tools') . '</p>'
            . '<h3>' . esc_html__('Privacy Requests and Contact', 'mds-sponsorable-ai-tools') . '</h3>'
            . '<p>' . esc_html__('Users can request access, correction, removal, export, or erasure of personal information through {{contact_method}}. Approved erasure requests remove contact details, messages, saved results, private checkout links, and IP-derived abuse data while non-identifying payment, provider-order, moderation, and delivery records may be retained for legal, accounting, fraud-prevention, security, backup, sponsor-contract, and dispute-retention obligations.', 'mds-sponsorable-ai-tools') . '</p>';
    }

    public function frontend_assets() {
        wp_register_style('mds-sponsorable-ai-tools', MDS_SPONSORABLE_AI_TOOLS_URL . 'assets/css/style.css', [], MDS_SPONSORABLE_AI_TOOLS_VERSION);
        wp_register_script('mds-sponsorable-ai-tools', MDS_SPONSORABLE_AI_TOOLS_URL . 'assets/js/frontend.js', [], MDS_SPONSORABLE_AI_TOOLS_VERSION, true);
        wp_localize_script('mds-sponsorable-ai-tools', 'mdsSponsorableTools', [
            'runFirst' => __('Run the tool before requesting a saved result.', 'mds-sponsorable-ai-tools'),
            'budgetResult' => __('Estimated daily budget: %1$s. Budget per 1,000 visitors: %2$s. Use this as a simple planning estimate, then confirm pricing and placement terms.', 'mds-sponsorable-ai-tools'),
            'launchResult' => __('Plan around %1$s: confirm the offer, prepare sponsor creative, schedule placement review, publish approved assets, and review results against %2$s.', 'mds-sponsorable-ai-tools'),
            'sponsorResult' => __('%1$s looks best suited for a %2$s package with clear disclosure, simple creative, and a short review checkpoint.', 'mds-sponsorable-ai-tools'),
            'copyResult' => __('Draft angle: a %1$s guide about %2$s with one useful takeaway, one sponsor-supported resource, and a clear disclosure.', 'mds-sponsorable-ai-tools'),
            'launchDateFallback' => __('the launch date', 'mds-sponsorable-ai-tools'),
            'goalFallback' => __('the primary campaign goal', 'mds-sponsorable-ai-tools'),
            'sponsorFallback' => __('This sponsor', 'mds-sponsorable-ai-tools'),
            'visibilityFallback' => __('visibility', 'mds-sponsorable-ai-tools'),
            'topicFallback' => __('this topic', 'mds-sponsorable-ai-tools'),
            'toneFallback' => __('practical', 'mds-sponsorable-ai-tools'),
        ]);
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage sponsorable tools.', 'mds-sponsorable-ai-tools'));
        }

        $tool_page = max(1, absint($_GET['mds_sat_tool_page'] ?? 1));
        $tools_collection = Repository::tools_page($tool_page, 20);
        $tools = $tools_collection['items'];
        $selected_id = absint($_GET['tool_id'] ?? ($tools[0]['id'] ?? 0));
        $tool = $selected_id ? Repository::tool($selected_id) : null;
        $form_tool = $tool ?: [
            'id' => 0,
            'title' => '',
            'slug' => '',
            'kind' => 'budget_estimator',
            'description' => '',
            'status' => 'draft',
            'sponsor_name' => '',
            'sponsor_url' => '',
            'sponsor_label' => '',
            'disclosure' => __('This tool is informational and sponsor-supported. It does not provide financial, legal, medical, tax, or regulated advice.', 'mds-sponsorable-ai-tools'),
            'lead_capture_enabled' => true,
            'unlock_mode' => 'free',
            'price' => 0,
            'currency' => Repository::default_currency(),
            'contact_email' => get_option('admin_email'),
            'terms_url' => '',
            'retention_days' => 365,
            'submission_limit_per_hour' => 10,
        ];
        $lead_page = max(1, absint($_GET['mds_sat_lead_page'] ?? 1));
        $lead_status = sanitize_key((string) ($_GET['mds_sat_lead_status'] ?? ''));
        $lead_search = sanitize_text_field(wp_unslash($_GET['mds_sat_lead_search'] ?? ''));
        $leads_collection = $tool ? Repository::leads_page($tool['id'], $lead_page, 20, ['status' => $lead_status, 'search' => $lead_search]) : ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
        $leads = $leads_collection['items'];
        $default_tab = $tool ? 'leads' : 'settings';
        $active_tab = sanitize_key((string) ($_GET['tab'] ?? $default_tab));
        $allowed_tabs = $tool ? ['settings', 'leads'] : ['settings'];
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = $default_tab;
        }
        ?>
        <div class="wrap mds3-admin mds-sponsorable-ai-tools-admin">
            <div class="mds-sponsorable-ai-tools-hero">
                <div>
                    <p class="mds-sponsorable-ai-tools-eyebrow"><?php esc_html_e('Million Dollar Script extension', 'mds-sponsorable-ai-tools'); ?></p>
                    <h1><?php esc_html_e('Sponsorable AI Tools', 'mds-sponsorable-ai-tools'); ?></h1>
                    <p><?php esc_html_e('Publish small useful tools with sponsor placement, clear disclosures, lead capture, moderation, and API-safe public data.', 'mds-sponsorable-ai-tools'); ?></p>
                </div>
                <div class="mds-extension-header-actions has-shortcode">
                    <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::docs_button('mds-sponsorable-ai-tools')); ?>
                    <?php if ($tool) : ?>
                        <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::shortcode_copy(sprintf('[mds_sponsorable_ai_tools id="%d"]', absint($tool['id'])))); ?>
                    <?php endif; ?>
                </div>
            </div>
            <hr class="wp-header-end">

            <?php $this->admin_notice(); ?>

            <nav class="nav-tab-wrapper wp-clearfix mds-sponsorable-ai-tools-tabs" aria-label="<?php esc_attr_e('Sponsorable AI Tools workspace', 'mds-sponsorable-ai-tools'); ?>">
                <?php if ($tool) : ?>
                    <a class="nav-tab<?php echo 'leads' === $active_tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->admin_url(['tool_id' => $selected_id, 'tab' => 'leads'])); ?>" <?php echo 'leads' === $active_tab ? 'aria-current="page"' : ''; ?>><?php echo esc_html(sprintf(__('Leads (%d)', 'mds-sponsorable-ai-tools'), absint($leads_collection['total']))); ?></a>
                <?php endif; ?>
                <a class="nav-tab<?php echo 'settings' === $active_tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->admin_url(['tool_id' => $selected_id, 'tab' => 'settings'])); ?>" <?php echo 'settings' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e('Tool settings', 'mds-sponsorable-ai-tools'); ?></a>
            </nav>

            <div class="mds-sponsorable-ai-tools-layout">
                <section class="mds3-card mds-sponsorable-ai-tools-panel">
                    <h2><?php esc_html_e('Tools', 'mds-sponsorable-ai-tools'); ?></h2>
                    <div class="mds-sponsorable-ai-tools-list">
                        <?php foreach ($tools as $item) : ?>
                            <a class="mds-sponsorable-ai-tools-list-item <?php echo absint($item['id']) === absint($selected_id) ? 'is-selected' : ''; ?>" href="<?php echo esc_url($this->admin_url(['tool_id' => $item['id'], 'tab' => $active_tab])); ?>">
                                <strong><?php echo esc_html($item['title']); ?></strong>
                                <span><?php echo esc_html($this->kind_label($item['kind'])); ?> · <?php echo esc_html($item['status']); ?> · <?php echo esc_html(sprintf(_n('%d lead', '%d leads', absint($item['lead_count']), 'mds-sponsorable-ai-tools'), absint($item['lead_count']))); ?></span>
                            </a>
                        <?php endforeach; ?>
                        <a class="button" href="<?php echo esc_url($this->admin_url(['tool_id' => 0, 'tab' => 'settings'])); ?>"><?php esc_html_e('New tool', 'mds-sponsorable-ai-tools'); ?></a>
                    </div>
                    <?php $this->pagination($tools_collection, 'mds_sat_tool_page', ['tool_id' => $selected_id, 'tab' => $active_tab]); ?>
                </section>

                <?php if ('settings' === $active_tab) : ?>
                <section class="mds3-card mds-sponsorable-ai-tools-panel">
                    <h2><?php echo $tool ? esc_html__('Tool settings', 'mds-sponsorable-ai-tools') : esc_html__('Create tool', 'mds-sponsorable-ai-tools'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorable-ai-tools-form">
                        <?php wp_nonce_field('mds_sponsorable_ai_tools_save_tool_' . absint($form_tool['id'])); ?>
                        <input type="hidden" name="action" value="mds_sponsorable_ai_tools_save_tool">
                        <input type="hidden" name="tool_id" value="<?php echo esc_attr((string) $form_tool['id']); ?>">
                        <?php $this->field('title', __('Title', 'mds-sponsorable-ai-tools'), $form_tool['title'], 'text', true); ?>
                        <?php $this->field('slug', __('Slug', 'mds-sponsorable-ai-tools'), $form_tool['slug'], 'text'); ?>
                        <?php $this->select('kind', __('Template', 'mds-sponsorable-ai-tools'), $form_tool['kind'], Repository::tool_kinds()); ?>
                        <?php $this->select('status', __('Status', 'mds-sponsorable-ai-tools'), $form_tool['status'], ['draft' => __('Draft', 'mds-sponsorable-ai-tools'), 'active' => __('Active', 'mds-sponsorable-ai-tools'), 'paused' => __('Paused', 'mds-sponsorable-ai-tools'), 'archived' => __('Archived', 'mds-sponsorable-ai-tools')]); ?>
                        <?php $this->field('description', __('Description', 'mds-sponsorable-ai-tools'), $form_tool['description'], 'textarea'); ?>
                        <?php $this->field('sponsor_name', __('Sponsor name', 'mds-sponsorable-ai-tools'), $form_tool['sponsor_name'], 'text'); ?>
                        <?php $this->field('sponsor_url', __('Sponsor URL', 'mds-sponsorable-ai-tools'), $form_tool['sponsor_url'], 'url'); ?>
                        <?php $this->field('sponsor_label', __('Sponsor label', 'mds-sponsorable-ai-tools'), $form_tool['sponsor_label'], 'text'); ?>
                        <?php $this->field('disclosure', __('Disclosure', 'mds-sponsorable-ai-tools'), $form_tool['disclosure'], 'textarea'); ?>
                        <?php $this->select('unlock_mode', __('Delivery mode', 'mds-sponsorable-ai-tools'), $form_tool['unlock_mode'], ['free' => __('Free result email', 'mds-sponsorable-ai-tools'), 'manual' => __('Manual reviewed delivery', 'mds-sponsorable-ai-tools'), 'provider' => __('Payment provider delivery', 'mds-sponsorable-ai-tools')]); ?>
                        <?php $this->field('price', __('Delivery price', 'mds-sponsorable-ai-tools'), $form_tool['price'], 'number'); ?>
                        <?php $this->field('currency', __('Currency', 'mds-sponsorable-ai-tools'), $form_tool['currency'], 'text'); ?>
                        <?php $this->field('contact_email', __('Contact email', 'mds-sponsorable-ai-tools'), $form_tool['contact_email'], 'email'); ?>
                        <?php $this->field('terms_url', __('Terms and privacy URL', 'mds-sponsorable-ai-tools'), $form_tool['terms_url'], 'url'); ?>
                        <?php $this->field('retention_days', __('Private data retention days', 'mds-sponsorable-ai-tools'), $form_tool['retention_days'], 'number'); ?>
                        <?php $this->field('submission_limit_per_hour', __('Requests per visitor per hour', 'mds-sponsorable-ai-tools'), $form_tool['submission_limit_per_hour'], 'number'); ?>
                        <label class="mds-sponsorable-ai-tools-check"><input type="checkbox" name="lead_capture_enabled" value="1" <?php checked(!empty($form_tool['lead_capture_enabled'])); ?>> <span><?php esc_html_e('Capture visitor leads for this tool', 'mds-sponsorable-ai-tools'); ?></span></label>
                        <p class="mds-sponsorable-ai-tools-note"><?php esc_html_e('Templates are intentionally narrow and informational. Do not use them for regulated advice without a reviewed custom template.', 'mds-sponsorable-ai-tools'); ?></p>
                        <p class="submit"><button class="button button-primary" type="submit"><?php esc_html_e('Save tool', 'mds-sponsorable-ai-tools'); ?></button></p>
                    </form>
                </section>
                <?php endif; ?>

            <?php if ($tool && 'leads' === $active_tab) : ?>
                <section class="mds3-card mds-sponsorable-ai-tools-panel">
                    <h2><?php esc_html_e('Leads', 'mds-sponsorable-ai-tools'); ?></h2>
                    <form method="get" class="mds-sponsorable-ai-tools-filters">
                        <input type="hidden" name="page" value="mds-sponsorable-ai-tools">
                        <input type="hidden" name="tool_id" value="<?php echo esc_attr((string) absint($tool['id'])); ?>">
                        <input type="hidden" name="tab" value="leads">
                        <label><span><?php esc_html_e('Search leads', 'mds-sponsorable-ai-tools'); ?></span><input type="search" name="mds_sat_lead_search" value="<?php echo esc_attr($lead_search); ?>"></label>
                        <label><span><?php esc_html_e('Status', 'mds-sponsorable-ai-tools'); ?></span><select name="mds_sat_lead_status"><option value=""><?php esc_html_e('All statuses', 'mds-sponsorable-ai-tools'); ?></option><option value="pending_review" <?php selected($lead_status, 'pending_review'); ?>><?php esc_html_e('Pending review', 'mds-sponsorable-ai-tools'); ?></option><option value="approved" <?php selected($lead_status, 'approved'); ?>><?php esc_html_e('Approved', 'mds-sponsorable-ai-tools'); ?></option><option value="rejected" <?php selected($lead_status, 'rejected'); ?>><?php esc_html_e('Rejected', 'mds-sponsorable-ai-tools'); ?></option></select></label>
                        <button class="button" type="submit"><?php esc_html_e('Filter', 'mds-sponsorable-ai-tools'); ?></button>
                    </form>
                    <?php if (!$leads) : ?>
                        <p><?php esc_html_e('No leads yet.', 'mds-sponsorable-ai-tools'); ?></p>
                        <p><a class="button" href="<?php echo esc_url($this->admin_url(['tool_id' => $tool['id'], 'tab' => 'settings'])); ?>"><?php esc_html_e('Review tool settings', 'mds-sponsorable-ai-tools'); ?></a></p>
                    <?php else : ?>
                        <div class="mds-sponsorable-ai-tools-leads">
                            <?php foreach ($leads as $lead) : ?>
                                <article>
                                    <div>
                                        <strong><?php echo esc_html($lead['name']); ?></strong>
                                        <span><?php echo esc_html($lead['email']); ?> · <?php echo esc_html($lead['status']); ?><?php echo !empty($lead['payment_status']) ? ' · ' . esc_html($lead['payment_status']) : ''; ?> · <?php echo esc_html(sprintf(__('Delivery: %1$s (%2$d attempts)', 'mds-sponsorable-ai-tools'), (string) ($lead['delivery_status'] ?? 'pending'), absint($lead['delivery_attempts'] ?? 0))); ?><?php echo (float) ($lead['amount'] ?? 0) > 0 ? ' · ' . esc_html($this->format_money($lead['amount'], $lead['currency'])) : ''; ?><?php echo !empty($lead['provider_order_id']) ? ' · ' . esc_html(sprintf(__('Provider order #%s', 'mds-sponsorable-ai-tools'), $lead['provider_order_id'])) : ''; ?></span>
                                        <?php if (!empty($lead['delivered_at'])) : ?><p><?php echo esc_html(sprintf(__('Delivered %s UTC', 'mds-sponsorable-ai-tools'), (string) $lead['delivered_at'])); ?></p><?php endif; ?>
                                        <?php if (!empty($lead['delivery_error'])) : ?><p class="mds-sponsorable-ai-tools-delivery-error"><?php echo esc_html($lead['delivery_error']); ?></p><?php endif; ?>
                                        <?php if (!empty($lead['result'])) : ?><p><?php echo esc_html(wp_trim_words($lead['result'], 28)); ?></p><?php endif; ?>
                                        <?php if (!empty($lead['message'])) : ?><p><?php echo esc_html(wp_trim_words($lead['message'], 28)); ?></p><?php endif; ?>
                                    </div>
                                    <div class="mds-sponsorable-ai-tools-actions">
                                        <?php if ('approved' !== (string) $lead['status']) : ?>
                                            <?php
                                            $approve_label = 'manual' === (string) ($lead['unlock_mode'] ?? '') && (float) ($lead['amount'] ?? 0) > 0
                                                ? __('Approve and confirm payment', 'mds-sponsorable-ai-tools')
                                                : __('Approve request', 'mds-sponsorable-ai-tools');
                                            $this->lead_button($lead['id'], 'approve', $approve_label);
                                            ?>
                                        <?php endif; ?>
                                        <?php if ('rejected' !== (string) $lead['status']) : ?>
                                            <?php $this->lead_button($lead['id'], 'reject', __('Reject', 'mds-sponsorable-ai-tools')); ?>
                                        <?php endif; ?>
                                        <?php if ('failed' === (string) ($lead['delivery_status'] ?? '')) : ?>
                                            <?php $this->delivery_button($lead['id'], __('Retry delivery', 'mds-sponsorable-ai-tools')); ?>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php $this->pagination($leads_collection, 'mds_sat_lead_page', ['tool_id' => absint($tool['id']), 'tab' => 'leads', 'mds_sat_lead_status' => $lead_status, 'mds_sat_lead_search' => $lead_search]); ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function handle_save_tool() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to save sponsorable tools.', 'mds-sponsorable-ai-tools'));
        }

        $tool_id = absint($_POST['tool_id'] ?? 0);
        check_admin_referer('mds_sponsorable_ai_tools_save_tool_' . $tool_id);
        $saved = Repository::save_tool([
            'title' => wp_unslash($_POST['title'] ?? ''),
            'slug' => wp_unslash($_POST['slug'] ?? ''),
            'kind' => wp_unslash($_POST['kind'] ?? 'budget_estimator'),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'status' => wp_unslash($_POST['status'] ?? 'draft'),
            'sponsor_name' => wp_unslash($_POST['sponsor_name'] ?? ''),
            'sponsor_url' => wp_unslash($_POST['sponsor_url'] ?? ''),
            'sponsor_label' => wp_unslash($_POST['sponsor_label'] ?? ''),
            'disclosure' => wp_unslash($_POST['disclosure'] ?? ''),
            'lead_capture_enabled' => !empty($_POST['lead_capture_enabled']) ? '1' : '0',
            'unlock_mode' => wp_unslash($_POST['unlock_mode'] ?? 'free'),
            'price' => wp_unslash($_POST['price'] ?? 0),
            'currency' => wp_unslash($_POST['currency'] ?? Repository::default_currency()),
            'contact_email' => wp_unslash($_POST['contact_email'] ?? ''),
            'terms_url' => wp_unslash($_POST['terms_url'] ?? ''),
            'retention_days' => wp_unslash($_POST['retention_days'] ?? 365),
            'submission_limit_per_hour' => wp_unslash($_POST['submission_limit_per_hour'] ?? 10),
        ], $tool_id);

        $this->redirect(['tool_id' => $saved ?: $tool_id, 'tab' => 'settings', 'mds_sat_status' => $saved ? 'saved' : 'error']);
    }

    public function handle_submit_lead() {
        $tool_id = absint($_POST['tool_id'] ?? 0);
        check_admin_referer('mds_sponsorable_ai_tools_submit_lead_' . $tool_id);
        $referrer = esc_url_raw(wp_unslash($_POST['_wp_http_referer'] ?? wp_get_referer() ?: home_url('/')));
        if (!empty($_POST['company'])) {
            $this->safe_redirect($referrer, ['sat_status' => 'submitted']);
        }
        $tool = Repository::tool($tool_id);
        if (!$tool || 'active' !== (string) ($tool['status'] ?? '') || empty($tool['lead_capture_enabled'])) {
            $this->safe_redirect($referrer, ['sat_status' => 'closed']);
        }
        if ($this->rate_limited($tool_id, absint($tool['submission_limit_per_hour'] ?? 10))) {
            $this->safe_redirect($referrer, ['sat_status' => 'rate_limited']);
        }

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (!$name || !$email || !is_email($email)) {
            $this->safe_redirect($referrer, ['sat_status' => 'invalid']);
        }
        $result = sanitize_textarea_field(wp_unslash($_POST['result'] ?? ''));
        if (!$result) {
            $this->safe_redirect($referrer, ['sat_status' => 'missing_result']);
        }

        $lead_id = Repository::create_lead($tool_id, [
            'name' => $name,
            'email' => $email,
            'message' => wp_unslash($_POST['message'] ?? ''),
            'result' => $result,
            'terms_accepted' => !empty($_POST['terms_accepted']) ? '1' : '0',
        ]);
        if (is_wp_error($lead_id)) {
            $this->safe_redirect($referrer, ['sat_status' => $lead_id->get_error_code() === 'mds_sat_terms_required' ? 'terms_required' : 'invalid']);
        }
        $lead = Repository::lead($lead_id);
        if (!$lead) {
            $this->safe_redirect($referrer, ['sat_status' => 'invalid']);
        }

        Notifications::submission($tool, $lead, $this->admin_url(['tool_id' => $tool_id]));

        if ($this->requires_checkout($tool, $lead)) {
            if (!class_exists('\\MillionDollarScript\\Commerce\\Payments')) {
                Repository::update_lead_payment_status($lead_id, 'failed');
                $this->safe_redirect($referrer, ['sat_status' => 'checkout_unavailable']);
            }
            $checkout = $this->start_checkout($tool, $lead, $referrer);
            if (is_array($checkout)) {
                Repository::update_lead_checkout(absint($lead['id'] ?? 0), $checkout);
            }
            if (is_array($checkout) && !empty($checkout['checkout_url'])) {
                $this->checkout_redirect((string) $checkout['checkout_url'], $referrer);
            }
            Repository::update_lead_payment_status($lead_id, 'failed');
            $this->safe_redirect($referrer, ['sat_status' => 'checkout_failed']);
        }

        if ($lead_id && 'free' === (string) ($tool['unlock_mode'] ?? 'free')) {
            $this->deliver_lead($lead_id);
        }

        $this->safe_redirect($referrer, ['sat_status' => 'submitted']);
    }

    public function handle_moderate_lead() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to moderate leads.', 'mds-sponsorable-ai-tools'));
        }

        $lead_id = absint($_POST['lead_id'] ?? 0);
        check_admin_referer('mds_sponsorable_ai_tools_moderate_lead_' . $lead_id);
        $lead = Repository::lead($lead_id);
        $ok = $lead && Repository::update_lead_status($lead_id, wp_unslash($_POST['moderation'] ?? ''));
        if ($ok && 'approve' === sanitize_key((string) wp_unslash($_POST['moderation'] ?? ''))) {
            $this->deliver_lead($lead_id);
        }
        $this->redirect([
            'tool_id' => absint($lead['tool_id'] ?? 0),
            'tab' => 'leads',
            'mds_sat_status' => $ok ? 'lead_moderated' : 'error',
        ]);
    }

    public function handle_deliver_lead() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to deliver saved tool results.', 'mds-sponsorable-ai-tools'));
        }

        $lead_id = absint($_POST['lead_id'] ?? 0);
        check_admin_referer('mds_sponsorable_ai_tools_deliver_lead_' . $lead_id);
        $lead = Repository::lead($lead_id);
        $delivered = $lead && $this->deliver_lead($lead_id);
        $updated = $lead ? Repository::lead($lead_id) : null;
        $this->redirect([
            'tool_id' => absint($lead['tool_id'] ?? 0),
            'tab' => 'leads',
            'mds_sat_status' => $delivered ? 'delivery_sent' : ('failed' === (string) ($updated['delivery_status'] ?? '') ? 'delivery_failed' : 'delivery_pending'),
        ]);
    }

    public function shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'slug' => '',
            'show_form' => 'true',
            'show_sponsor' => 'true',
            'show_disclosure' => 'true',
        ], $atts, 'mds_sponsorable_ai_tools');
        $show_form = $this->truthy($atts['show_form']);
        $show_sponsor = $this->truthy($atts['show_sponsor']);
        $show_disclosure = $this->truthy($atts['show_disclosure']);
        $collection = null;
        if (absint($atts['id'])) {
            $tools = array_filter([Repository::tool(absint($atts['id']))]);
        } elseif ('' !== (string) $atts['slug']) {
            $tools = array_filter([Repository::tool_by_slug((string) $atts['slug'])]);
        } else {
            $collection = Repository::tools_page(max(1, absint($_GET['mds_sat_page'] ?? 1)), 12, true);
            $tools = $collection['items'];
        }

        $tools = array_values(array_filter($tools, function ($tool) {
            return 'active' === (string) ($tool['status'] ?? '');
        }));

        if (!$tools) {
            return current_user_can('manage_options') ? '<p class="mds-sponsorable-ai-tools-empty">' . esc_html__('No active sponsorable tools found.', 'mds-sponsorable-ai-tools') . '</p>' : '';
        }

        wp_enqueue_style('mds-sponsorable-ai-tools');
        wp_enqueue_script('mds-sponsorable-ai-tools');
        ob_start();
        ?>
        <section class="mds-sponsorable-ai-tools">
            <?php $this->public_notice(); ?>
            <?php foreach ($tools as $tool) : ?>
                <article class="mds-sponsorable-ai-tools__tool" data-tool-kind="<?php echo esc_attr((string) $tool['kind']); ?>" data-tool-currency="<?php echo esc_attr(Repository::effective_currency($tool['currency'] ?? '')); ?>">
                    <header>
                        <span><?php echo esc_html($this->kind_label($tool['kind'])); ?></span>
                        <h2><?php echo esc_html($tool['title']); ?></h2>
                        <?php if (!empty($tool['description'])) : ?><p><?php echo esc_html($tool['description']); ?></p><?php endif; ?>
                        <?php if ($show_sponsor && !empty($tool['sponsor_name'])) : ?>
                            <p class="mds-sponsorable-ai-tools__sponsor">
                                <?php echo esc_html($tool['sponsor_label'] ?: __('Sponsored by', 'mds-sponsorable-ai-tools')); ?>
                                <?php if (!empty($tool['sponsor_url'])) : ?>
                                    <a href="<?php echo esc_url($tool['sponsor_url']); ?>" rel="nofollow sponsored noopener" target="_blank"><?php echo esc_html($tool['sponsor_name']); ?></a>
                                <?php else : ?>
                                    <strong><?php echo esc_html($tool['sponsor_name']); ?></strong>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </header>

                    <?php if ($show_form) : ?>
                        <?php $this->tool_form($tool); ?>
                    <?php endif; ?>

                    <?php if ($show_disclosure && !empty($tool['disclosure'])) : ?>
                        <small><?php echo esc_html($tool['disclosure']); ?></small>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($collection) : ?>
                <?php $this->public_pagination($collection, 'mds_sat_page'); ?>
            <?php endif; ?>
        </section>
        <?php
        return trim((string) ob_get_clean());
    }

    private function truthy($value) {
        if (is_bool($value)) {
            return $value;
        }

        return !in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'off'], true);
    }

    public function register_rest_routes() {
        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/tools', [[
            'methods' => 'GET',
            'callback' => function ($request) {
                $collection = Repository::tools_page(
                    max(1, absint($request['page'] ?? 1)),
                    min(100, max(1, absint($request['per_page'] ?? 20))),
                    true
                );
                return rest_ensure_response([
                    'tools' => $collection['items'],
                    'pagination' => array_diff_key($collection, ['items' => true]),
                ]);
            },
            'permission_callback' => '__return_true',
            'args' => [
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ],
        ]]);

        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/leads', [[
            'methods' => 'GET',
            'callback' => function ($request) {
                $collection = Repository::leads_page(
                    absint($request['tool_id'] ?? 0),
                    max(1, absint($request['page'] ?? 1)),
                    min(100, max(1, absint($request['per_page'] ?? 20))),
                    [
                        'status' => sanitize_key((string) ($request['status'] ?? '')),
                        'search' => sanitize_text_field((string) ($request['search'] ?? '')),
                    ]
                );
                return rest_ensure_response([
                    'leads' => $collection['items'],
                    'pagination' => array_diff_key($collection, ['items' => true]),
                ]);
            },
            'permission_callback' => [$this, 'permission_manage'],
            'args' => [
                'tool_id' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'status' => ['type' => 'string', 'enum' => ['', 'pending_review', 'approved', 'rejected'], 'default' => ''],
                'search' => ['type' => 'string', 'maxLength' => 80, 'default' => ''],
            ],
        ]]);
    }

    public function permission_manage() {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            return \MillionDollarScript\Extensions\Support::can_manage_api();
        }

        return current_user_can('manage_options');
    }

    public function handle_payment_status($source, $source_id, $status, $context = []) {
        unset($context);
        if ('sponsorable-ai-tools' !== sanitize_key((string) $source)) {
            return;
        }

        $status = sanitize_key((string) $status);
        if (in_array($status, ['paid', 'completed', 'processing'], true)) {
            Repository::update_lead_payment_status(absint($source_id), 'paid');
            $this->deliver_lead(absint($source_id));
            return;
        }

        if (in_array($status, ['failed', 'cancelled', 'expired', 'refunded', 'denied'], true)) {
            Repository::update_lead_payment_status(absint($source_id), $status);
        }
    }

    public function deliver_lead($lead_id) {
        $lead = Repository::lead($lead_id);
        if (!$lead) {
            return false;
        }
        if ('delivered' === (string) ($lead['delivery_status'] ?? '')) {
            return true;
        }
        if (!$this->lead_ready_for_delivery($lead)) {
            return false;
        }

        $tool = Repository::tool(absint($lead['tool_id'] ?? 0));
        $email = sanitize_email((string) ($lead['email'] ?? ''));
        $result = trim((string) ($lead['result'] ?? ''));
        if (!$tool || !$email || !is_email($email) || !$result) {
            return false;
        }

        $lock = Repository::begin_lead_delivery(absint($lead['id'] ?? 0));
        if (!$lock) {
            $current = Repository::lead(absint($lead['id'] ?? 0));
            return 'delivered' === (string) ($current['delivery_status'] ?? '');
        }

        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $subject = sprintf(__('[%1$s] Your saved result from %2$s', 'mds-sponsorable-ai-tools'), $site_name, (string) ($tool['title'] ?? ''));
        $message = implode("\n\n", array_filter([
            sprintf(__('Hello %s,', 'mds-sponsorable-ai-tools'), (string) ($lead['name'] ?? '')),
            sprintf(__('Here is the saved result you requested from %s:', 'mds-sponsorable-ai-tools'), (string) ($tool['title'] ?? '')),
            $result,
            !empty($lead['message']) ? sprintf(__('Your note: %s', 'mds-sponsorable-ai-tools'), (string) $lead['message']) : '',
            !empty($tool['disclosure']) ? sprintf(__('Important: %s', 'mds-sponsorable-ai-tools'), (string) $tool['disclosure']) : '',
            sprintf(__('Contact the site administrator through %s if you need help with this request.', 'mds-sponsorable-ai-tools'), home_url('/')),
        ]));
        $subject = sanitize_text_field((string) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorable-ai-tools/delivery/subject', ['mds_sponsorable_ai_tools_delivery_subject'], $subject, $lead, $tool));
        $message = (string) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorable-ai-tools/delivery/message', ['mds_sponsorable_ai_tools_delivery_message'], $message, $lead, $tool);
        $headers = (array) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorable-ai-tools/delivery/headers', ['mds_sponsorable_ai_tools_delivery_headers'], ['Content-Type: text/plain; charset=UTF-8'], $lead, $tool);
        $delivered = (bool) wp_mail($email, $subject, $message, $headers);
        Repository::finish_lead_delivery(
            absint($lead['id'] ?? 0),
            $lock,
            $delivered,
            $delivered ? '' : __('WordPress could not send the result email. Check mail delivery and retry.', 'mds-sponsorable-ai-tools')
        );
        \MillionDollarScript\Core\Hooks::do_compat('million-dollar-script/extensions/sponsorable-ai-tools/delivery/attempted', ['mds_sponsorable_ai_tools_delivery_attempted'], absint($lead['id'] ?? 0), $delivered, $lead, $tool);

        return $delivered;
    }

    private function lead_ready_for_delivery(array $lead) {
        if ('free' === (string) ($lead['unlock_mode'] ?? 'free')) {
            return true;
        }
        if ('approved' !== (string) ($lead['status'] ?? '')) {
            return false;
        }

        return in_array(sanitize_key((string) ($lead['payment_status'] ?? '')), ['paid', 'completed', 'processing', 'not_required'], true);
    }

    public function api_endpoint_manifest($manifest) {
        $manifest = is_array($manifest) ? $manifest : [];
        $manifest[] = ['id' => 'sponsorable-ai-tools-read', 'route' => '/million-dollar-script/v1/sponsorable-ai-tools/tools', 'methods' => ['GET'], 'scope' => 'sponsorable_tools.read', 'minimum_security_level' => 'public_read', 'description' => __('Read active public sponsorable tools.', 'mds-sponsorable-ai-tools')];
        $manifest[] = ['id' => 'sponsorable-ai-tools-leads-read', 'route' => '/million-dollar-script/v1/sponsorable-ai-tools/leads', 'methods' => ['GET'], 'scope' => 'sponsorable_tools.leads', 'minimum_security_level' => 'wp_capability', 'description' => __('Read private sponsorable tool result requests.', 'mds-sponsorable-ai-tools')];

        return $manifest;
    }

    public function privacy_exporters($exporters) {
        $exporters['mds-sponsorable-ai-tools'] = [
            'exporter_friendly_name' => __('Sponsorable tool result requests', 'mds-sponsorable-ai-tools'),
            'callback' => [$this, 'privacy_exporter'],
        ];
        return $exporters;
    }

    public function privacy_exporter($email_address, $page = 1) {
        return Privacy::exporter($email_address, $page);
    }

    public function privacy_erasers($erasers) {
        $erasers['mds-sponsorable-ai-tools'] = [
            'eraser_friendly_name' => __('Sponsorable tool result requests', 'mds-sponsorable-ai-tools'),
            'callback' => [$this, 'privacy_eraser'],
        ];
        return $erasers;
    }

    public function privacy_eraser($email_address, $page = 1) {
        return Privacy::eraser($email_address, $page);
    }

    private function tool_form(array $tool) {
        $price = $this->format_money($tool['price'], $tool['currency']);
        $currency = Repository::effective_currency($tool['currency'] ?? '');
        $unlock_mode = (string) ($tool['unlock_mode'] ?? 'free');
        ?>
        <form class="mds-sponsorable-ai-tools__form">
            <input type="hidden" name="tool_id" value="<?php echo esc_attr((string) $tool['id']); ?>">
            <p class="mds-sponsorable-ai-tools__form-note"><?php esc_html_e('Run the tool in your browser. Results are estimates and require your own review before acting on them.', 'mds-sponsorable-ai-tools'); ?></p>
            <?php if ('budget_estimator' === (string) $tool['kind']) : ?>
                <label><span><?php esc_html_e('Estimated audience', 'mds-sponsorable-ai-tools'); ?></span><input type="number" min="1" max="100000000" name="audience" value="1000"></label>
                <label><span><?php echo esc_html($currency ? sprintf(__('Sponsor budget (%s)', 'mds-sponsorable-ai-tools'), $currency) : __('Sponsor budget', 'mds-sponsorable-ai-tools')); ?></span><input type="number" min="0" max="100000000" step="1" name="budget" value="250"></label>
                <label><span><?php esc_html_e('Campaign days', 'mds-sponsorable-ai-tools'); ?></span><input type="number" min="1" max="3650" name="days" value="14"></label>
            <?php elseif ('launch_planner' === (string) $tool['kind']) : ?>
                <label><span><?php esc_html_e('Launch date', 'mds-sponsorable-ai-tools'); ?></span><input type="date" name="launch_date"></label>
                <label><span><?php esc_html_e('Primary goal', 'mds-sponsorable-ai-tools'); ?></span><input type="text" name="goal" maxlength="120"></label>
            <?php elseif ('sponsor_fit_quiz' === (string) $tool['kind']) : ?>
                <label><span><?php esc_html_e('Business type', 'mds-sponsorable-ai-tools'); ?></span><input type="text" name="business_type" maxlength="80"></label>
                <label><span><?php esc_html_e('Campaign goal', 'mds-sponsorable-ai-tools'); ?></span><select name="goal"><option value="visibility"><?php esc_html_e('Visibility', 'mds-sponsorable-ai-tools'); ?></option><option value="leads"><?php esc_html_e('Lead capture', 'mds-sponsorable-ai-tools'); ?></option><option value="launch"><?php esc_html_e('Launch support', 'mds-sponsorable-ai-tools'); ?></option></select></label>
            <?php else : ?>
                <label><span><?php esc_html_e('Topic', 'mds-sponsorable-ai-tools'); ?></span><input type="text" name="topic" maxlength="120"></label>
                <label><span><?php esc_html_e('Tone', 'mds-sponsorable-ai-tools'); ?></span><select name="tone"><option value="practical"><?php esc_html_e('Practical', 'mds-sponsorable-ai-tools'); ?></option><option value="friendly"><?php esc_html_e('Friendly', 'mds-sponsorable-ai-tools'); ?></option><option value="direct"><?php esc_html_e('Direct', 'mds-sponsorable-ai-tools'); ?></option></select></label>
            <?php endif; ?>
            <?php if ('free' !== $unlock_mode && $price) : ?>
                <p class="mds-sponsorable-ai-tools__price">
                    <?php
                    echo esc_html('provider' === $unlock_mode
                        ? sprintf(__('Saved result and reviewed follow-up: %s. Submitting the delivery request continues to checkout.', 'mds-sponsorable-ai-tools'), $price)
                        : sprintf(__('Manually reviewed result delivery: %s. The site owner confirms payment and approval before sending it.', 'mds-sponsorable-ai-tools'), $price));
                    ?>
                </p>
            <?php endif; ?>
            <button type="submit"><?php esc_html_e('Run tool', 'mds-sponsorable-ai-tools'); ?></button>
            <output class="mds-sponsorable-ai-tools__output" aria-live="polite"></output>
        </form>

        <?php if (!empty($tool['lead_capture_enabled'])) : ?>
            <details class="mds-sponsorable-ai-tools__lead">
                <summary>
                    <?php
                    echo esc_html('free' === $unlock_mode
                        ? __('Email my saved result', 'mds-sponsorable-ai-tools')
                        : __('Request saved result and follow-up', 'mds-sponsorable-ai-tools'));
                    ?>
                </summary>
                <p class="mds-sponsorable-ai-tools__form-note">
                    <?php
                    echo esc_html('free' === $unlock_mode
                        ? __('Run the tool first, then enter your email to receive a saved copy of the result.', 'mds-sponsorable-ai-tools')
                        : ('provider' === $unlock_mode
                            ? __('Run the tool first, then submit your contact details. Delivery requires successful checkout and administrator approval.', 'mds-sponsorable-ai-tools')
                            : __('Run the tool first, then submit your contact details. The site owner confirms payment, reviews the request, and sends the saved result.', 'mds-sponsorable-ai-tools')));
                    ?>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mds_sponsorable_ai_tools_submit_lead_' . absint($tool['id'])); ?>
                    <input type="hidden" name="action" value="mds_sponsorable_ai_tools_submit_lead">
                    <input type="hidden" name="tool_id" value="<?php echo esc_attr((string) $tool['id']); ?>">
                    <input type="hidden" name="result" value="">
                    <input type="text" name="company" value="" tabindex="-1" autocomplete="off" class="mds-sponsorable-ai-tools__trap">
                    <label><span><?php esc_html_e('Name', 'mds-sponsorable-ai-tools'); ?></span><input type="text" name="name" required maxlength="80" autocomplete="name"></label>
                    <label><span><?php esc_html_e('Email', 'mds-sponsorable-ai-tools'); ?></span><input type="email" name="email" required autocomplete="email"></label>
                    <label><span><?php esc_html_e('Message', 'mds-sponsorable-ai-tools'); ?></span><textarea name="message" rows="3" maxlength="2000"></textarea></label>
                    <label class="mds-sponsorable-ai-tools__check"><input type="checkbox" name="terms_accepted" value="1" required> <span>
                        <?php if (!empty($tool['terms_url'])) : ?>
                            <?php printf(wp_kses_post(__('I accept the <a href="%s" target="_blank" rel="noopener">tool, delivery, and privacy terms</a>.', 'mds-sponsorable-ai-tools')), esc_url($tool['terms_url'])); ?>
                        <?php else : ?>
                            <?php esc_html_e('I accept the tool, delivery, and privacy terms described on this page.', 'mds-sponsorable-ai-tools'); ?>
                        <?php endif; ?>
                    </span></label>
                    <button type="submit">
                        <?php
                        echo esc_html('provider' === $unlock_mode && $price
                            ? __('Submit and continue to checkout', 'mds-sponsorable-ai-tools')
                            : ('free' === $unlock_mode ? __('Email saved result', 'mds-sponsorable-ai-tools') : __('Send review request', 'mds-sponsorable-ai-tools')));
                        ?>
                    </button>
                </form>
            </details>
        <?php endif; ?>
        <?php
    }

    private function requires_checkout(array $tool, array $lead) {
        return 'provider' === (string) ($tool['unlock_mode'] ?? '')
            && (float) ($lead['amount'] ?? 0) > 0;
    }

    private function start_checkout(array $tool, array $lead, $fallback_url) {
        if (
            class_exists('\MillionDollarScript\Extensions\Subscriptions\Entitlements')
            && \MillionDollarScript\Extensions\Subscriptions\Entitlements::consume(
                'sponsorable-ai-tools',
                'results',
                'result:' . absint($lead['id'] ?? 0),
                [
                    'user_id' => get_current_user_id(),
                    'email' => sanitize_email((string) ($lead['email'] ?? '')),
                ],
                (string) absint($tool['id'] ?? 0)
            )
        ) {
            \MillionDollarScript\Commerce\Payments::mark_source_paid('sponsorable-ai-tools', absint($lead['id'] ?? 0), [
                'provider' => 'subscription_allowance',
            ]);
            return [
                'provider' => 'subscription_allowance',
                'status' => 'paid',
                'checkout_url' => add_query_arg('sponsorable_ai_tools_status', 'delivered', (string) $fallback_url),
            ];
        }

        $amount = (float) ($lead['amount'] ?? 0);
        $transaction = [
            'source' => 'sponsorable-ai-tools',
            'source_id' => absint($lead['id'] ?? 0),
            'source_key' => 'sponsorable-tool-lead-' . absint($lead['id'] ?? 0),
            'user_id' => get_current_user_id(),
            'email' => sanitize_email($lead['email'] ?? ''),
            'currency' => Repository::effective_currency($lead['currency'] ?? $tool['currency'] ?? ''),
            'subtotal' => $amount,
            'total' => $amount,
            'items' => [[
                'name' => sprintf(__('Sponsorable tool unlock: %s', 'mds-sponsorable-ai-tools'), $tool['title']),
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
            ]],
            'manage_url' => esc_url_raw((string) $fallback_url),
            'metadata' => [
                'tool_id' => absint($tool['id'] ?? 0),
                'lead_id' => absint($lead['id'] ?? 0),
            ],
        ];

        return \MillionDollarScript\Commerce\Payments::create_checkout($transaction);
    }

    private function field($name, $label, $value, $type = 'text', $required = false, $help = '') {
        $wide = in_array($type, ['textarea'], true) ? ' mds-sponsorable-ai-tools-field-wide' : '';
        $help = $help ?: $this->field_help($name);
        $help_id = 'mds-sponsorable-ai-tools-help-' . sanitize_html_class((string) $name);
        ?>
        <label class="mds-sponsorable-ai-tools-field<?php echo esc_attr($wide); ?>">
            <span><?php echo esc_html($label); ?></span>
            <?php if ('textarea' === $type) : ?>
                <textarea name="<?php echo esc_attr($name); ?>" rows="4" <?php echo $help ? 'aria-describedby="' . esc_attr($help_id) . '"' : ''; ?> <?php echo $required ? 'required' : ''; ?>><?php echo esc_textarea((string) $value); ?></textarea>
            <?php else : ?>
                <?php $number_attributes = $this->number_field_attributes($name, $type); ?>
                <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" <?php echo $help ? 'aria-describedby="' . esc_attr($help_id) . '"' : ''; ?> <?php echo $required ? 'required' : ''; ?> <?php echo $number_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo $this->field_maxlength($name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php endif; ?>
            <?php if ($help) : ?>
                <small id="<?php echo esc_attr($help_id); ?>" class="mds-sponsorable-ai-tools-field-help"><?php echo esc_html($help); ?></small>
            <?php endif; ?>
        </label>
        <?php
    }

    private function select($name, $label, $value, array $options, $help = '') {
        $help = $help ?: $this->field_help($name);
        $help_id = 'mds-sponsorable-ai-tools-help-' . sanitize_html_class((string) $name);
        ?>
        <label class="mds-sponsorable-ai-tools-field">
            <span><?php echo esc_html($label); ?></span>
            <select name="<?php echo esc_attr($name); ?>" <?php echo $help ? 'aria-describedby="' . esc_attr($help_id) . '"' : ''; ?>>
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr((string) $option_value); ?>" <?php selected((string) $value, (string) $option_value); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($help) : ?>
                <small id="<?php echo esc_attr($help_id); ?>" class="mds-sponsorable-ai-tools-field-help"><?php echo esc_html($help); ?></small>
            <?php endif; ?>
        </label>
        <?php
    }

    private function field_help($name) {
        $help = [
            'title' => __('The public tool name shown to visitors.', 'mds-sponsorable-ai-tools'),
            'slug' => __('Optional URL-safe identifier for shortcodes and integrations. Leave blank to generate one from the title.', 'mds-sponsorable-ai-tools'),
            'kind' => __('Select the built-in tool template visitors will use.', 'mds-sponsorable-ai-tools'),
            'status' => __('Only active tools are shown publicly and can capture leads.', 'mds-sponsorable-ai-tools'),
            'description' => __('Short public description that explains what the tool helps visitors estimate or plan.', 'mds-sponsorable-ai-tools'),
            'sponsor_name' => __('Optional sponsor name shown beside the tool.', 'mds-sponsorable-ai-tools'),
            'sponsor_url' => __('Optional sponsor destination link. Use a reviewed public URL.', 'mds-sponsorable-ai-tools'),
            'sponsor_label' => __('Short text before the sponsor name, such as Presented by or Sponsored by.', 'mds-sponsorable-ai-tools'),
            'disclosure' => __('Public disclosure explaining sponsorship and limits of the tool output.', 'mds-sponsorable-ai-tools'),
            'unlock_mode' => __('Choose free saved-result email, manually approved delivery, or checkout through the active Million Dollar Script payment provider. The browser result remains a preview in every mode.', 'mds-sponsorable-ai-tools'),
            'price' => __('Amount charged for manually reviewed or provider-based saved-result delivery and follow-up.', 'mds-sponsorable-ai-tools'),
            'currency' => __('Three-letter currency code used for paid saved-result delivery.', 'mds-sponsorable-ai-tools'),
            'contact_email' => __('Private administrator address that receives new saved-result request notifications.', 'mds-sponsorable-ai-tools'),
            'terms_url' => __('Optional HTTP or HTTPS page containing the reviewed tool, delivery, sponsorship, and privacy terms visitors must accept.', 'mds-sponsorable-ai-tools'),
            'retention_days' => __('Days to retain private visitor contact details, messages, and generated results before scheduled anonymization.', 'mds-sponsorable-ai-tools'),
            'submission_limit_per_hour' => __('Maximum saved-result requests accepted from one visitor for this tool during an hour.', 'mds-sponsorable-ai-tools'),
        ];

        return (string) ($help[(string) $name] ?? '');
    }

    private function number_field_attributes($name, $type) {
        if ('number' !== $type) {
            return '';
        }

        if ('retention_days' === $name) {
            return 'min="1" max="3650" step="1"';
        }
        if ('submission_limit_per_hour' === $name) {
            return 'min="1" max="500" step="1"';
        }

        return 'min="0" max="100000000" step="0.01"';
    }

    private function field_maxlength($name) {
        $lengths = [
            'title' => 120,
            'slug' => 160,
            'sponsor_name' => 120,
            'sponsor_url' => 2048,
            'sponsor_label' => 120,
            'currency' => 3,
            'contact_email' => 254,
            'terms_url' => 2048,
        ];

        return isset($lengths[$name]) ? 'maxlength="' . absint($lengths[$name]) . '"' : '';
    }

    private function lead_button($lead_id, $status, $label) {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds_sponsorable_ai_tools_moderate_lead_' . absint($lead_id)); ?>
            <input type="hidden" name="action" value="mds_sponsorable_ai_tools_moderate_lead">
            <input type="hidden" name="lead_id" value="<?php echo esc_attr((string) absint($lead_id)); ?>">
            <input type="hidden" name="moderation" value="<?php echo esc_attr((string) $status); ?>">
            <button class="button" type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    private function delivery_button($lead_id, $label) {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds_sponsorable_ai_tools_deliver_lead_' . absint($lead_id)); ?>
            <input type="hidden" name="action" value="mds_sponsorable_ai_tools_deliver_lead">
            <input type="hidden" name="lead_id" value="<?php echo esc_attr((string) absint($lead_id)); ?>">
            <button class="button" type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    private function admin_notice() {
        $status = sanitize_key((string) ($_GET['mds_sat_status'] ?? ''));
        $messages = [
            'saved' => __('Tool saved.', 'mds-sponsorable-ai-tools'),
            'lead_moderated' => __('Lead updated.', 'mds-sponsorable-ai-tools'),
            'delivery_sent' => __('The saved result was delivered by email.', 'mds-sponsorable-ai-tools'),
            'delivery_failed' => __('WordPress could not send the saved result. Check site mail delivery and retry.', 'mds-sponsorable-ai-tools'),
            'delivery_pending' => __('The result is not ready for delivery. Confirm approval and payment first.', 'mds-sponsorable-ai-tools'),
            'error' => __('Sponsorable AI Tools could not complete that action.', 'mds-sponsorable-ai-tools'),
        ];
        if (isset($messages[$status])) {
            $class = in_array($status, ['error', 'delivery_failed'], true) ? 'error' : (in_array($status, ['delivery_pending'], true) ? 'warning' : 'success');
            echo '<div class="notice notice-' . esc_attr($class) . '"><p>' . esc_html($messages[$status]) . '</p></div>';
        }
    }

    private function public_notice() {
        $status = sanitize_key((string) ($_GET['sat_status'] ?? ''));
        $messages = [
            'submitted' => __('Your saved result request was submitted. Free delivery is sent immediately; reviewed or paid delivery is emailed after its requirements are complete.', 'mds-sponsorable-ai-tools'),
            'invalid' => __('Add a valid name and email before submitting.', 'mds-sponsorable-ai-tools'),
            'missing_result' => __('Run the tool before requesting a saved result.', 'mds-sponsorable-ai-tools'),
            'closed' => __('This tool is not accepting result requests right now.', 'mds-sponsorable-ai-tools'),
            'rate_limited' => __('Please wait before submitting again.', 'mds-sponsorable-ai-tools'),
            'terms_required' => __('Accept the tool, delivery, and privacy terms before submitting.', 'mds-sponsorable-ai-tools'),
            'checkout_unavailable' => __('Checkout is not available for this tool right now. Your result was not charged or delivered.', 'mds-sponsorable-ai-tools'),
            'checkout_failed' => __('Checkout could not be started. Your result was not charged or delivered.', 'mds-sponsorable-ai-tools'),
        ];
        if (isset($messages[$status])) {
            $type = 'submitted' === $status ? 'success' : (in_array($status, ['closed', 'rate_limited'], true) ? 'warning' : 'error');
            echo '<p class="mds-sponsorable-ai-tools__notice mds-sponsorable-ai-tools__notice--' . esc_attr($type) . '" role="' . ('error' === $type ? 'alert' : 'status') . '">' . esc_html($messages[$status]) . '</p>';
        }
    }

    private function kind_label($kind) {
        $kinds = Repository::tool_kinds();
        return (string) ($kinds[(string) $kind] ?? $kind);
    }

    private function format_money($amount, $currency) {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return '';
        }

        $currency = Repository::effective_currency($currency);
        if (class_exists('\\MillionDollarScript\\Commerce\\Currency')) {
            return \MillionDollarScript\Commerce\Currency::format($amount, $currency);
        }

        return trim(sprintf('%s %s', $currency, number_format_i18n($amount, 2)));
    }

    private function rate_limited($tool_id, $limit) {
        $limit = min(500, max(1, absint($limit)));
        $window = HOUR_IN_SECONDS;
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $bucket = (string) floor(time() / $window);
        $hash = hash_hmac('sha256', ($ip ?: 'unknown') . '|tool_' . absint($tool_id) . '|' . $bucket, wp_salt('nonce'));
        $key = 'mds_sat_rate_' . substr($hash, 0, 32);
        $count = absint(get_transient($key));
        if ($count >= $limit) {
            return true;
        }

        set_transient($key, $count + 1, $window + MINUTE_IN_SECONDS);
        return false;
    }

    private function pagination(array $collection, $query_key, array $preserve = []) {
        $page = max(1, absint($collection['page'] ?? 1));
        $pages = max(1, absint($collection['pages'] ?? 1));
        if ($pages <= 1) {
            return;
        }
        ?>
        <nav class="mds-sponsorable-ai-tools-pagination" aria-label="<?php esc_attr_e('Pagination', 'mds-sponsorable-ai-tools'); ?>">
            <?php if ($page > 1) : ?><a class="button" href="<?php echo esc_url($this->admin_url(array_merge($preserve, [$query_key => $page - 1]))); ?>"><?php esc_html_e('Previous', 'mds-sponsorable-ai-tools'); ?></a><?php endif; ?>
            <span><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'mds-sponsorable-ai-tools'), $page, $pages)); ?></span>
            <?php if ($page < $pages) : ?><a class="button" href="<?php echo esc_url($this->admin_url(array_merge($preserve, [$query_key => $page + 1]))); ?>"><?php esc_html_e('Next', 'mds-sponsorable-ai-tools'); ?></a><?php endif; ?>
        </nav>
        <?php
    }

    private function public_pagination(array $collection, $query_key) {
        $page = max(1, absint($collection['page'] ?? 1));
        $pages = max(1, absint($collection['pages'] ?? 1));
        if ($pages <= 1) {
            return;
        }
        ?>
        <nav class="mds-sponsorable-ai-tools__pagination" aria-label="<?php esc_attr_e('Tool pages', 'mds-sponsorable-ai-tools'); ?>">
            <?php if ($page > 1) : ?><a href="<?php echo esc_url(add_query_arg($query_key, $page - 1)); ?>"><?php esc_html_e('Previous', 'mds-sponsorable-ai-tools'); ?></a><?php endif; ?>
            <span><?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'mds-sponsorable-ai-tools'), $page, $pages)); ?></span>
            <?php if ($page < $pages) : ?><a href="<?php echo esc_url(add_query_arg($query_key, $page + 1)); ?>"><?php esc_html_e('Next', 'mds-sponsorable-ai-tools'); ?></a><?php endif; ?>
        </nav>
        <?php
    }

    private function admin_url(array $args = []) {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            return \MillionDollarScript\Extensions\Support::admin_url('mds-sponsorable-ai-tools', $args);
        }

        return add_query_arg(array_merge(['page' => 'mds-sponsorable-ai-tools'], $args), admin_url('admin.php'));
    }

    private function redirect(array $args = []) {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            \MillionDollarScript\Extensions\Support::redirect_admin('mds-sponsorable-ai-tools', $args);
        }

        wp_safe_redirect($this->admin_url($args));
        exit;
    }

    private function safe_redirect($url, array $args = []) {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            \MillionDollarScript\Extensions\Support::safe_redirect($url, $args);
        }

        $url = $args ? add_query_arg($args, $url) : $url;
        wp_safe_redirect(esc_url_raw($url));
        exit;
    }

    private function checkout_redirect($url, $fallback_url = '') {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support') && method_exists('\\MillionDollarScript\\Extensions\\Support', 'external_redirect')) {
            \MillionDollarScript\Extensions\Support::external_redirect($url, $fallback_url ?: home_url('/'));
        }

        $target = esc_url_raw((string) $url, ['http', 'https']);
        $parts = wp_parse_url($target);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!$target || empty($parts['host']) || !in_array($scheme, ['http', 'https'], true)) {
            $this->safe_redirect($fallback_url ?: home_url('/'));
        }

        wp_redirect($target, 302, 'Million Dollar Script - Sponsorable AI Tools');
        exit;
    }

    private function admin_parent_slug() {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support')) {
            return \MillionDollarScript\Extensions\Support::admin_parent_slug();
        }

        global $admin_page_hooks;
        if (isset($admin_page_hooks['mds3'])) {
            return 'mds3';
        }
        if (isset($admin_page_hooks['million-dollar-script'])) {
            return 'million-dollar-script';
        }

        return 'options-general.php';
    }
}
