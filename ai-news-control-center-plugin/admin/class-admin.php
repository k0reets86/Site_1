<?php
/**
 * Admin Class
 * Handles WordPress admin interface for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'register_settings']);

        // Add admin bar menu
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);

        // Ajax handlers
        add_action('wp_ajax_aincc_quick_stats', [$this, 'ajax_quick_stats']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('AI News Center', 'ai-news-center'),
            __('AI News Center', 'ai-news-center'),
            'edit_posts',
            'ai-news-center',
            [$this, 'render_main_page'],
            'dashicons-rss',
            26
        );

        // Submenus
        add_submenu_page(
            'ai-news-center',
            __('Dashboard', 'ai-news-center'),
            __('Dashboard', 'ai-news-center'),
            'edit_posts',
            'ai-news-center',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            __('Create Article', 'ai-news-center'),
            __('Create Article', 'ai-news-center'),
            'edit_posts',
            'ai-news-center-create',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            __('Analytics', 'ai-news-center'),
            __('Analytics', 'ai-news-center'),
            'edit_posts',
            'ai-news-center-analytics',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            __('Sources', 'ai-news-center'),
            __('Sources', 'ai-news-center'),
            'manage_options',
            'ai-news-center-sources',
            [$this, 'render_main_page']
        );

        add_submenu_page(
            'ai-news-center',
            __('Settings', 'ai-news-center'),
            __('Settings', 'ai-news-center'),
            'manage_options',
            'ai-news-center-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our pages
        if (strpos($hook, 'ai-news-center') === false) {
            return;
        }

        // Get current page for React router
        $current_page = 'dashboard';
        if (strpos($hook, '-create') !== false) {
            $current_page = 'create';
        } elseif (strpos($hook, '-analytics') !== false) {
            $current_page = 'analytics';
        } elseif (strpos($hook, '-sources') !== false) {
            $current_page = 'settings';
        } elseif (strpos($hook, '-settings') !== false) {
            $current_page = 'settings';
        }

        // Main React app CSS
        wp_enqueue_style(
            'aincc-admin',
            AINCC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AINCC_VERSION
        );

        // Google Fonts
        wp_enqueue_style(
            'aincc-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );

        // Tailwind CSS (from CDN for now)
        wp_enqueue_script(
            'aincc-tailwind',
            'https://cdn.tailwindcss.com',
            [],
            null,
            false
        );

        // React and ReactDOM
        wp_enqueue_script(
            'aincc-react',
            'https://unpkg.com/react@18/umd/react.production.min.js',
            [],
            '18',
            true
        );

        wp_enqueue_script(
            'aincc-react-dom',
            'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
            ['aincc-react'],
            '18',
            true
        );

        // Main admin app
        wp_enqueue_script(
            'aincc-admin-app',
            AINCC_PLUGIN_URL . 'assets/js/admin-app.js',
            ['aincc-react', 'aincc-react-dom'],
            AINCC_VERSION,
            true
        );

        // Pass data to JS
        wp_localize_script('aincc-admin-app', 'ainccData', [
            'apiUrl' => rest_url('aincc/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'pluginUrl' => AINCC_PLUGIN_URL,
            'currentPage' => $current_page,
            'currentUser' => [
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
                'isAdmin' => current_user_can('manage_options'),
            ],
            'settings' => [
                'languages' => AINCC_Settings::get_language_config(),
                'categories' => AINCC_Settings::get_categories(),
                'aiProvider' => AINCC_Settings::get('ai_provider'),
                'autoPublishEnabled' => AINCC_Settings::get('auto_publish_enabled'),
            ],
            'i18n' => [
                'dashboard' => __('Dashboard', 'ai-news-center'),
                'create' => __('Create Article', 'ai-news-center'),
                'analytics' => __('Analytics', 'ai-news-center'),
                'settings' => __('Settings', 'ai-news-center'),
                'sources' => __('Sources', 'ai-news-center'),
                'moderation' => __('Moderation', 'ai-news-center'),
                'calendar' => __('Calendar', 'ai-news-center'),
                'approve' => __('Approve', 'ai-news-center'),
                'reject' => __('Reject', 'ai-news-center'),
                'publish' => __('Publish', 'ai-news-center'),
                'schedule' => __('Schedule', 'ai-news-center'),
                'edit' => __('Edit', 'ai-news-center'),
                'delete' => __('Delete', 'ai-news-center'),
                'save' => __('Save', 'ai-news-center'),
                'cancel' => __('Cancel', 'ai-news-center'),
                'loading' => __('Loading...', 'ai-news-center'),
            ],
        ]);
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // AI Provider settings
        register_setting('aincc_settings', 'aincc_ai_provider');
        register_setting('aincc_settings', 'aincc_deepseek_api_key');
        register_setting('aincc_settings', 'aincc_deepseek_model');
        register_setting('aincc_settings', 'aincc_openai_api_key');
        register_setting('aincc_settings', 'aincc_anthropic_api_key');

        // Media settings
        register_setting('aincc_settings', 'aincc_pexels_api_key');

        // Social media settings
        register_setting('aincc_settings', 'aincc_telegram_bot_token');
        register_setting('aincc_settings', 'aincc_telegram_channel_id');
        register_setting('aincc_settings', 'aincc_telegram_enabled');

        // Automation settings
        register_setting('aincc_settings', 'aincc_auto_publish_enabled');
        register_setting('aincc_settings', 'aincc_auto_publish_delay');
        register_setting('aincc_settings', 'aincc_fetch_interval');
    }

    /**
     * Render main page (React app container)
     */
    public function render_main_page() {
        ?>
        <div id="aincc-root" class="aincc-app">
            <div class="aincc-loading">
                <div class="aincc-spinner"></div>
                <p><?php _e('Loading AI News Control Center...', 'ai-news-center'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page (classic WP settings)
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form submitted
        if (isset($_POST['aincc_save_settings']) && check_admin_referer('aincc_settings_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'ai-news-center') . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php _e('AI News Control Center Settings', 'ai-news-center'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('aincc_settings_nonce'); ?>

                <h2 class="title"><?php _e('AI Provider', 'ai-news-center'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Provider', 'ai-news-center'); ?></th>
                        <td>
                            <select name="ai_provider">
                                <option value="deepseek" <?php selected(AINCC_Settings::get('ai_provider'), 'deepseek'); ?>>DeepSeek</option>
                                <option value="openai" <?php selected(AINCC_Settings::get('ai_provider'), 'openai'); ?>>OpenAI</option>
                                <option value="anthropic" <?php selected(AINCC_Settings::get('ai_provider'), 'anthropic'); ?>>Anthropic Claude</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('DeepSeek API Key', 'ai-news-center'); ?></th>
                        <td>
                            <input type="password" name="deepseek_api_key" value="<?php echo esc_attr(AINCC_Settings::get('deepseek_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('DeepSeek Model', 'ai-news-center'); ?></th>
                        <td>
                            <select name="deepseek_model">
                                <option value="deepseek-chat" <?php selected(AINCC_Settings::get('deepseek_model'), 'deepseek-chat'); ?>>DeepSeek Chat (Fast)</option>
                                <option value="deepseek-reasoner" <?php selected(AINCC_Settings::get('deepseek_model'), 'deepseek-reasoner'); ?>>DeepSeek Reasoner (Smart)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('OpenAI API Key', 'ai-news-center'); ?></th>
                        <td>
                            <input type="password" name="openai_api_key" value="<?php echo esc_attr(AINCC_Settings::get('openai_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Anthropic API Key', 'ai-news-center'); ?></th>
                        <td>
                            <input type="password" name="anthropic_api_key" value="<?php echo esc_attr(AINCC_Settings::get('anthropic_api_key')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php _e('Media', 'ai-news-center'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Pexels API Key', 'ai-news-center'); ?></th>
                        <td>
                            <input type="password" name="pexels_api_key" value="<?php echo esc_attr(AINCC_Settings::get('pexels_api_key')); ?>" class="regular-text">
                            <p class="description"><?php _e('Get free API key at pexels.com', 'ai-news-center'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php _e('Telegram', 'ai-news-center'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Telegram', 'ai-news-center'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="telegram_enabled" value="1" <?php checked(AINCC_Settings::get('telegram_enabled')); ?>>
                                <?php _e('Post articles to Telegram channel', 'ai-news-center'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Bot Token', 'ai-news-center'); ?></th>
                        <td>
                            <input type="password" name="telegram_bot_token" value="<?php echo esc_attr(AINCC_Settings::get('telegram_bot_token')); ?>" class="regular-text">
                            <p class="description"><?php _e('Create bot via @BotFather', 'ai-news-center'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Channel ID', 'ai-news-center'); ?></th>
                        <td>
                            <input type="text" name="telegram_channel_id" value="<?php echo esc_attr(AINCC_Settings::get('telegram_channel_id')); ?>" class="regular-text">
                            <p class="description"><?php _e('e.g., @yourchannel or -100XXXXXXXXXX', 'ai-news-center'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php _e('Automation', 'ai-news-center'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto-Publish', 'ai-news-center'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_publish_enabled" value="1" <?php checked(AINCC_Settings::get('auto_publish_enabled')); ?>>
                                <?php _e('Automatically publish approved content', 'ai-news-center'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Auto-Publish Delay', 'ai-news-center'); ?></th>
                        <td>
                            <input type="number" name="auto_publish_delay" value="<?php echo esc_attr(AINCC_Settings::get('auto_publish_delay', 10)); ?>" min="1" max="60" class="small-text">
                            <?php _e('minutes', 'ai-news-center'); ?>
                            <p class="description"><?php _e('Wait time before auto-publishing (allows editor to cancel)', 'ai-news-center'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Fetch Interval', 'ai-news-center'); ?></th>
                        <td>
                            <input type="number" name="fetch_interval" value="<?php echo esc_attr(AINCC_Settings::get('fetch_interval', 5)); ?>" min="2" max="60" class="small-text">
                            <?php _e('minutes', 'ai-news-center'); ?>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php _e('Quality Thresholds', 'ai-news-center'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Fact Check Threshold', 'ai-news-center'); ?></th>
                        <td>
                            <input type="number" name="fact_check_threshold" value="<?php echo esc_attr(AINCC_Settings::get('fact_check_threshold', 0.6)); ?>" min="0" max="1" step="0.1" class="small-text">
                            <p class="description"><?php _e('Articles below this score require manual approval (0-1)', 'ai-news-center'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Source Trust Threshold', 'ai-news-center'); ?></th>
                        <td>
                            <input type="number" name="source_trust_threshold" value="<?php echo esc_attr(AINCC_Settings::get('source_trust_threshold', 0.7)); ?>" min="0" max="1" step="0.1" class="small-text">
                            <p class="description"><?php _e('Sources below this trust score require manual approval (0-1)', 'ai-news-center'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="aincc_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'ai-news-center'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=ai-news-center'); ?>" class="button"><?php _e('Back to Dashboard', 'ai-news-center'); ?></a>
                </p>
            </form>

            <hr>

            <h2><?php _e('Connection Tests', 'ai-news-center'); ?></h2>
            <p>
                <button type="button" class="button" id="test-ai"><?php _e('Test AI Connection', 'ai-news-center'); ?></button>
                <button type="button" class="button" id="test-telegram"><?php _e('Test Telegram', 'ai-news-center'); ?></button>
                <button type="button" class="button" id="test-pexels"><?php _e('Test Pexels', 'ai-news-center'); ?></button>
            </p>
            <div id="test-results"></div>

            <script>
            jQuery(function($) {
                function testConnection(endpoint, button) {
                    $(button).prop('disabled', true).text('Testing...');
                    $.post('<?php echo rest_url('aincc/v1/'); ?>' + endpoint, {}, function(response) {
                        $('#test-results').html('<div class="notice notice-' + (response.success ? 'success' : 'error') + '"><p>' + response.message + '</p></div>');
                    }).fail(function(xhr) {
                        $('#test-results').html('<div class="notice notice-error"><p>Error: ' + xhr.responseJSON?.message || 'Connection failed' + '</p></div>');
                    }).always(function() {
                        $(button).prop('disabled', false).text($(button).data('original-text'));
                    });
                }

                $('#test-ai').data('original-text', '<?php _e('Test AI Connection', 'ai-news-center'); ?>').click(function() { testConnection('test/ai', this); });
                $('#test-telegram').data('original-text', '<?php _e('Test Telegram', 'ai-news-center'); ?>').click(function() { testConnection('test/telegram', this); });
                $('#test-pexels').data('original-text', '<?php _e('Test Pexels', 'ai-news-center'); ?>').click(function() { testConnection('test/pexels', this); });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Save settings from form
     */
    private function save_settings() {
        $fields = [
            'ai_provider',
            'deepseek_api_key',
            'deepseek_model',
            'openai_api_key',
            'anthropic_api_key',
            'pexels_api_key',
            'telegram_bot_token',
            'telegram_channel_id',
            'auto_publish_delay',
            'fetch_interval',
            'fact_check_threshold',
            'source_trust_threshold',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                AINCC_Settings::set($field, sanitize_text_field($_POST[$field]));
            }
        }

        // Checkboxes
        AINCC_Settings::set('telegram_enabled', isset($_POST['telegram_enabled']));
        AINCC_Settings::set('auto_publish_enabled', isset($_POST['auto_publish_enabled']));
    }

    /**
     * Add admin bar menu
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $db = new AINCC_Database();
        $pending = $db->count_drafts_by_status(['pending_ok']);

        $title = __('AI News', 'ai-news-center');
        if ($pending > 0) {
            $title .= sprintf(' <span class="aincc-badge">%d</span>', $pending);
        }

        $wp_admin_bar->add_node([
            'id' => 'aincc-admin-bar',
            'title' => $title,
            'href' => admin_url('admin.php?page=ai-news-center'),
        ]);

        $wp_admin_bar->add_node([
            'id' => 'aincc-pending',
            'parent' => 'aincc-admin-bar',
            'title' => sprintf(__('Pending Review (%d)', 'ai-news-center'), $pending),
            'href' => admin_url('admin.php?page=ai-news-center&status=pending_ok'),
        ]);

        $wp_admin_bar->add_node([
            'id' => 'aincc-create',
            'parent' => 'aincc-admin-bar',
            'title' => __('Create Article', 'ai-news-center'),
            'href' => admin_url('admin.php?page=ai-news-center-create'),
        ]);
    }

    /**
     * Ajax quick stats
     */
    public function ajax_quick_stats() {
        check_ajax_referer('aincc_nonce', 'nonce');

        $db = new AINCC_Database();

        wp_send_json([
            'pending' => $db->count_drafts_by_status(['pending_ok']),
            'auto_ready' => $db->count_drafts_by_status(['auto_ready']),
            'published_today' => $db->count_drafts_by_status(['published']),
        ]);
    }
}
