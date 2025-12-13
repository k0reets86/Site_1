<?php
/**
 * REST API
 * Provides endpoints for the React admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'aincc/v1';

    /**
     * Constructor - routes are registered via init_rest_api in main plugin
     */
    public function __construct() {
        // Ensure all required classes are loaded
        $this->load_dependencies();
    }

    /**
     * Load required dependencies for API operations
     */
    private function load_dependencies() {
        $plugin_dir = AINCC_PLUGIN_DIR;

        // Load AI providers
        if (!interface_exists('AINCC_AI_Provider_Interface')) {
            require_once $plugin_dir . 'includes/ai-providers/interface-ai-provider.php';
        }
        if (!class_exists('AINCC_AI_Provider_Factory')) {
            require_once $plugin_dir . 'includes/ai-providers/class-ai-provider-factory.php';
        }
        if (!class_exists('AINCC_DeepSeek_Provider')) {
            require_once $plugin_dir . 'includes/ai-providers/class-deepseek-provider.php';
        }
        if (!class_exists('AINCC_OpenAI_Provider')) {
            require_once $plugin_dir . 'includes/ai-providers/class-openai-provider.php';
        }
        if (!class_exists('AINCC_Anthropic_Provider')) {
            require_once $plugin_dir . 'includes/ai-providers/class-anthropic-provider.php';
        }

        // Load other required classes
        if (!class_exists('AINCC_RSS_Parser')) {
            require_once $plugin_dir . 'includes/class-rss-parser.php';
        }
        if (!class_exists('AINCC_Content_Processor')) {
            require_once $plugin_dir . 'includes/class-content-processor.php';
        }
        if (!class_exists('AINCC_Image_Handler')) {
            require_once $plugin_dir . 'includes/class-image-handler.php';
        }
        if (!class_exists('AINCC_Publisher')) {
            require_once $plugin_dir . 'includes/class-publisher.php';
        }
        if (!class_exists('AINCC_Telegram')) {
            require_once $plugin_dir . 'includes/class-telegram.php';
        }
        if (!class_exists('AINCC_Scheduler')) {
            require_once $plugin_dir . 'includes/class-scheduler.php';
        }

        // Auto-initialize database if needed
        $this->ensure_database_initialized();
    }

    /**
     * Ensure database tables exist and have sources
     */
    private function ensure_database_initialized() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aincc_sources';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");

        if (!$table_exists) {
            // Tables don't exist - create them
            $db = new AINCC_Database();
            $db->create_tables();
            AINCC_Logger::info('Database auto-initialized via REST API');
        } else {
            // Table exists but check if sources are empty
            $source_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            if ($source_count == 0) {
                // Load default sources
                $db = new AINCC_Database();
                $db->load_default_sources();
                AINCC_Logger::info('Default sources loaded via REST API');
            }
        }
    }

    /**
     * Register all REST routes
     */
    public function register_routes() {
        // Dashboard/Queue
        register_rest_route(self::NAMESPACE, '/drafts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_drafts'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'status' => ['type' => 'string', 'default' => 'pending_ok,auto_ready'],
                'lang' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_draft'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_draft'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_draft'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Draft actions
        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_draft'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'reason' => ['type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publish_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'channels' => ['type' => 'array', 'default' => ['wordpress', 'telegram']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/schedule', [
            'methods' => 'POST',
            'callback' => [$this, 'schedule_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'scheduled_at' => ['type' => 'string', 'required' => true],
                'channels' => ['type' => 'array', 'default' => ['wordpress', 'telegram']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/regenerate', [
            'methods' => 'POST',
            'callback' => [$this, 'regenerate_draft'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'what' => ['type' => 'string', 'default' => 'all'],
                'instructions' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/drafts/(?P<id>[a-zA-Z0-9_]+)/image', [
            'methods' => 'POST',
            'callback' => [$this, 'assign_image'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'query' => ['type' => 'string'],
            ],
        ]);

        // Create manual article
        register_rest_route(self::NAMESPACE, '/articles/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_article'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Create article from URL
        register_rest_route(self::NAMESPACE, '/articles/from-url', [
            'methods' => 'POST',
            'callback' => [$this, 'create_article_from_url'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'url' => ['type' => 'string', 'required' => true],
                'source_lang' => ['type' => 'string', 'default' => 'de'],
                'target_langs' => ['type' => 'array', 'default' => ['de', 'ua', 'ru', 'en']],
                'category' => ['type' => 'string', 'default' => 'nachrichten'],
            ],
        ]);

        // Manual fetch trigger
        register_rest_route(self::NAMESPACE, '/fetch/now', [
            'methods' => 'POST',
            'callback' => [$this, 'trigger_fetch'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Sources
        register_rest_route(self::NAMESPACE, '/sources', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_sources'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_source'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/sources/(?P<id>[a-zA-Z0-9_]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_source'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_source'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/sources/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_source'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'url' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Settings
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        // Analytics
        register_rest_route(self::NAMESPACE, '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'period' => ['type' => 'string', 'default' => '7d'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/analytics/top-articles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_top_articles'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'period' => ['type' => 'string', 'default' => '7d'],
                'limit' => ['type' => 'integer', 'default' => 10],
            ],
        ]);

        // System
        register_rest_route(self::NAMESPACE, '/system/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_system_status'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/system/cron', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cron_status'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/system/cron/trigger', [
            'methods' => 'POST',
            'callback' => [$this, 'trigger_cron'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'hook' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/system/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'level' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 100],
            ],
        ]);

        // Test connections
        register_rest_route(self::NAMESPACE, '/test/ai', [
            'methods' => 'POST',
            'callback' => [$this, 'test_ai_connection'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/test/telegram', [
            'methods' => 'POST',
            'callback' => [$this, 'test_telegram_connection'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/test/pexels', [
            'methods' => 'POST',
            'callback' => [$this, 'test_pexels_connection'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // System reinitialize
        register_rest_route(self::NAMESPACE, '/system/reinitialize', [
            'methods' => 'POST',
            'callback' => [$this, 'reinitialize_plugin'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Image search
        register_rest_route(self::NAMESPACE, '/images/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_images'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'query' => ['type' => 'string', 'required' => true],
                'per_page' => ['type' => 'integer', 'default' => 10],
            ],
        ]);

        // AI check text
        register_rest_route(self::NAMESPACE, '/ai/check-text', [
            'methods' => 'POST',
            'callback' => [$this, 'check_text_with_ai'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'text' => ['type' => 'string', 'required' => true],
                'lang' => ['type' => 'string', 'default' => 'de'],
            ],
        ]);

        // Toggle source enabled status
        register_rest_route(self::NAMESPACE, '/sources/(?P<id>[a-zA-Z0-9_-]+)/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_source'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Check if user has editor permission
     */
    public function check_permission() {
        return current_user_can('edit_posts');
    }

    /**
     * Check if user has admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get drafts list
     */
    public function get_drafts($request) {
        $db = new AINCC_Database();

        $statuses = explode(',', $request->get_param('status'));
        $lang = $request->get_param('lang');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $offset = ($page - 1) * $per_page;

        $drafts = $db->get_drafts_by_status($statuses, $lang, $per_page, $offset);
        $total = $db->count_drafts_by_status($statuses);

        // Format for API response
        $items = array_map(function ($draft) {
            return $this->format_draft($draft);
        }, $drafts);

        return new WP_REST_Response([
            'items' => $items,
            'total' => (int) $total,
            'page' => (int) $page,
            'per_page' => (int) $per_page,
            'total_pages' => ceil($total / $per_page),
        ], 200);
    }

    /**
     * Get single draft
     */
    public function get_draft($request) {
        $db = new AINCC_Database();
        $draft = $db->get_draft($request->get_param('id'));

        if (!$draft) {
            return new WP_Error('not_found', 'Draft not found', ['status' => 404]);
        }

        return new WP_REST_Response($this->format_draft($draft, true), 200);
    }

    /**
     * Update draft
     */
    public function update_draft($request) {
        $db = new AINCC_Database();
        $id = $request->get_param('id');
        $data = $request->get_json_params();

        // Filter allowed fields
        $allowed = ['title', 'lead', 'body_html', 'seo_title', 'meta_description',
            'slug', 'category', 'tags', 'image_url', 'image_alt'];

        $update_data = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['tags'])) {
                    $update_data[$field] = json_encode($data[$field]);
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
            }
        }

        // Allow HTML in body
        if (isset($data['body_html'])) {
            $update_data['body_html'] = wp_kses_post($data['body_html']);
        }

        $update_data['edited_by'] = get_current_user_id();

        $db->update_draft($id, $update_data);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Delete draft
     */
    public function delete_draft($request) {
        global $wpdb;
        $db = new AINCC_Database();

        $wpdb->delete($db->table('drafts'), ['id' => $request->get_param('id')]);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Approve draft
     */
    public function approve_draft($request) {
        $publisher = new AINCC_Publisher();
        $result = $publisher->approve($request->get_param('id'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Reject draft
     */
    public function reject_draft($request) {
        $publisher = new AINCC_Publisher();
        $reason = $request->get_param('reason') ?: 'Rejected by editor';
        $result = $publisher->reject($request->get_param('id'), $reason);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Publish draft
     */
    public function publish_draft($request) {
        $publisher = new AINCC_Publisher();
        $channels = $request->get_param('channels');

        $result = $publisher->publish_all($request->get_param('id'), $channels);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Schedule draft
     */
    public function schedule_draft($request) {
        $publisher = new AINCC_Publisher();

        $scheduled_at = $request->get_param('scheduled_at');
        $channels = $request->get_param('channels');

        $result = $publisher->schedule($request->get_param('id'), $scheduled_at, $channels);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Regenerate draft content
     */
    public function regenerate_draft($request) {
        $processor = new AINCC_Content_Processor();

        $result = $processor->regenerate_draft(
            $request->get_param('id'),
            $request->get_param('what'),
            $request->get_param('instructions')
        );

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Assign/find image for draft
     */
    public function assign_image($request) {
        $image_handler = new AINCC_Image_Handler();
        $query = $request->get_param('query');

        if ($query) {
            $image = $image_handler->search_pexels($query);

            if ($image) {
                $db = new AINCC_Database();
                $db->update_draft($request->get_param('id'), [
                    'image_url' => $image['url'],
                    'image_author' => $image['author'],
                    'image_license' => $image['license'],
                    'image_alt' => $image['alt'],
                ]);

                return new WP_REST_Response(['success' => true, 'image' => $image], 200);
            }

            return new WP_REST_Response(['success' => false, 'error' => 'No images found'], 404);
        }

        $result = $image_handler->assign_to_draft($request->get_param('id'));
        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Create manual article
     */
    public function create_article($request) {
        $processor = new AINCC_Content_Processor();
        $data = $request->get_json_params();

        // Validate required fields
        if (empty($data['title']) || empty($data['body'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Заголовок и текст обязательны',
            ], 400);
        }

        try {
            $result = $processor->process_manual_article($data);
            return new WP_REST_Response($result, $result['success'] ? 201 : 400);
        } catch (Exception $e) {
            AINCC_Logger::error('Article creation failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка создания: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create article from URL
     */
    public function create_article_from_url($request) {
        $url = $request->get_param('url');
        $source_lang = $request->get_param('source_lang') ?: 'de';
        $target_langs = $request->get_param('target_langs') ?: ['de', 'ua', 'ru', 'en'];
        $category = $request->get_param('category') ?: 'nachrichten';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Некорректный URL',
            ], 400);
        }

        try {
            $processor = new AINCC_Content_Processor();
            $result = $processor->process_article_from_url($url, $source_lang, $target_langs, $category);

            return new WP_REST_Response($result, $result['success'] ? 201 : 400);
        } catch (Exception $e) {
            AINCC_Logger::error('URL article creation failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка обработки URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger manual fetch
     */
    public function trigger_fetch($request) {
        try {
            $parser = new AINCC_RSS_Parser();
            $parser->fetch_all_sources();

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Сбор новостей запущен',
            ], 200);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sources
     */
    public function get_sources($request) {
        $parser = new AINCC_RSS_Parser();
        $sources = $parser->get_all_sources();

        return new WP_REST_Response(['items' => $sources], 200);
    }

    /**
     * Create source
     */
    public function create_source($request) {
        $parser = new AINCC_RSS_Parser();
        $data = $request->get_json_params();

        $result = $parser->add_source($data);

        return new WP_REST_Response($result, $result['success'] ? 201 : 400);
    }

    /**
     * Update source
     */
    public function update_source($request) {
        $parser = new AINCC_RSS_Parser();
        $data = $request->get_json_params();

        $result = $parser->update_source($request->get_param('id'), $data);

        return new WP_REST_Response(['success' => $result], $result ? 200 : 400);
    }

    /**
     * Delete source
     */
    public function delete_source($request) {
        $parser = new AINCC_RSS_Parser();
        $result = $parser->delete_source($request->get_param('id'));

        return new WP_REST_Response(['success' => (bool) $result], $result ? 200 : 400);
    }

    /**
     * Test source URL
     */
    public function test_source($request) {
        $parser = new AINCC_RSS_Parser();
        $result = $parser->test_source($request->get_param('url'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get settings
     */
    public function get_settings($request) {
        $settings = AINCC_Settings::get_all();

        // Hide API keys
        $hidden_keys = ['deepseek_api_key', 'openai_api_key', 'anthropic_api_key',
            'pexels_api_key', 'telegram_bot_token', 'facebook_access_token'];

        foreach ($hidden_keys as $key) {
            if (!empty($settings[$key])) {
                $settings[$key] = '***' . substr($settings[$key], -4);
            }
        }

        return new WP_REST_Response($settings, 200);
    }

    /**
     * Update settings
     */
    public function update_settings($request) {
        $data = $request->get_json_params();

        // Don't overwrite keys if they're masked
        $key_fields = ['deepseek_api_key', 'openai_api_key', 'anthropic_api_key',
            'pexels_api_key', 'telegram_bot_token', 'facebook_access_token'];

        foreach ($key_fields as $field) {
            if (isset($data[$field]) && strpos($data[$field], '***') === 0) {
                unset($data[$field]);
            }
        }

        AINCC_Settings::update_all($data);

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Get analytics
     */
    public function get_analytics($request) {
        $db = new AINCC_Database();
        global $wpdb;

        $period = $request->get_param('period');
        $days = $this->period_to_days($period);

        // Get counts
        $stats = [
            'published' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$db->table('drafts')}
                     WHERE status = 'published' AND published_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            ),
            'pending' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'pending_ok'"
            ),
            'auto_ready' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$db->table('drafts')} WHERE status = 'auto_ready'"
            ),
            'rejected' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$db->table('drafts')}
                     WHERE status = 'rejected' AND updated_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            ),
        ];

        // Get category distribution
        $categories = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category, COUNT(*) as count FROM {$db->table('drafts')}
                 WHERE status = 'published' AND published_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY category ORDER BY count DESC",
                $days
            ),
            ARRAY_A
        );

        // Get source performance
        $sources = $wpdb->get_results(
            "SELECT s.name, s.trust_score, COUNT(d.id) as article_count
             FROM {$db->table('sources')} s
             LEFT JOIN {$db->table('raw_items')} ri ON s.id = ri.source_id
             LEFT JOIN {$db->table('drafts')} d ON ri.id = d.raw_item_id
             WHERE d.status = 'published'
             GROUP BY s.id ORDER BY article_count DESC LIMIT 10",
            ARRAY_A
        );

        // Daily published count for chart
        $daily = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(published_at) as date, COUNT(*) as count
                 FROM {$db->table('drafts')}
                 WHERE status = 'published' AND published_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY DATE(published_at) ORDER BY date ASC",
                $days
            ),
            ARRAY_A
        );

        return new WP_REST_Response([
            'stats' => $stats,
            'categories' => $categories,
            'sources' => $sources,
            'daily' => $daily,
            'period' => $period,
        ], 200);
    }

    /**
     * Get top articles
     */
    public function get_top_articles($request) {
        $db = new AINCC_Database();
        global $wpdb;

        $days = $this->period_to_days($request->get_param('period'));
        $limit = $request->get_param('limit');

        $articles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.title, d.wp_post_id, d.published_at, d.category
                 FROM {$db->table('drafts')} d
                 WHERE d.status = 'published'
                 AND d.published_at > DATE_SUB(NOW(), INTERVAL %d DAY)
                 AND d.lang = 'de'
                 ORDER BY d.published_at DESC
                 LIMIT %d",
                $days,
                $limit
            ),
            ARRAY_A
        );

        // Add post URLs
        foreach ($articles as &$article) {
            if ($article['wp_post_id']) {
                $article['url'] = get_permalink($article['wp_post_id']);
            }
        }

        return new WP_REST_Response(['items' => $articles], 200);
    }

    /**
     * Get system status
     */
    public function get_system_status($request) {
        $db = new AINCC_Database();

        // API key validation
        $api_errors = AINCC_Settings::validate_api_keys();

        // Database tables check
        global $wpdb;
        $tables = ['sources', 'raw_items', 'events', 'drafts', 'fact_checks',
            'publishes', 'social_posts', 'queue', 'logs'];

        $table_status = [];
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$db->table($table)}'");
            $table_status[$table] = (bool) $exists;
        }

        // Queue status
        $queue_pending = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$db->table('queue')} WHERE status = 'pending'"
        );
        $queue_failed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$db->table('queue')} WHERE status = 'failed'"
        );

        return new WP_REST_Response([
            'version' => AINCC_VERSION,
            'db_version' => get_option('aincc_db_version'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'api_errors' => $api_errors,
            'tables' => $table_status,
            'queue' => [
                'pending' => (int) $queue_pending,
                'failed' => (int) $queue_failed,
            ],
            'ai_provider' => AINCC_Settings::get('ai_provider'),
        ], 200);
    }

    /**
     * Get cron status
     */
    public function get_cron_status($request) {
        $scheduler = new AINCC_Scheduler();
        return new WP_REST_Response($scheduler->get_cron_status(), 200);
    }

    /**
     * Trigger cron manually
     */
    public function trigger_cron($request) {
        $scheduler = new AINCC_Scheduler();
        $result = $scheduler->trigger_cron($request->get_param('hook'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get logs
     */
    public function get_logs($request) {
        $db = new AINCC_Database();
        $logs = $db->get_logs(
            $request->get_param('level'),
            $request->get_param('limit')
        );

        return new WP_REST_Response(['items' => $logs], 200);
    }

    /**
     * Test AI connection
     */
    public function test_ai_connection($request) {
        try {
            $provider = AINCC_Settings::get('ai_provider', 'deepseek');
            $api_key = AINCC_Settings::get($provider . '_api_key');

            if (empty($api_key)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => "API ключ для {$provider} не указан. Введите ключ в настройках.",
                ], 400);
            }

            $ai = AINCC_AI_Provider_Factory::create();
            $result = $ai->test_connection();

            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        } catch (Exception $e) {
            AINCC_Logger::error('AI connection test failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Telegram connection
     */
    public function test_telegram_connection($request) {
        try {
            $bot_token = AINCC_Settings::get('telegram_bot_token');

            if (empty($bot_token)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Telegram Bot Token не указан. Введите токен в настройках.',
                ], 400);
            }

            $telegram = new AINCC_Telegram();
            $result = $telegram->test_connection();

            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Pexels connection
     */
    public function test_pexels_connection($request) {
        try {
            $api_key = AINCC_Settings::get('pexels_api_key');

            if (empty($api_key)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Pexels API ключ не указан. Введите ключ в настройках.',
                ], 400);
            }

            $image_handler = new AINCC_Image_Handler();
            $result = $image_handler->search_pexels('nature');

            if ($result) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Pexels API подключен успешно!',
                    'sample_image' => $result['url'] ?? null,
                ], 200);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Не удалось подключиться к Pexels. Проверьте API ключ.',
            ], 400);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reinitialize plugin (create tables, load sources)
     */
    public function reinitialize_plugin($request) {
        try {
            // Create database tables
            $db = new AINCC_Database();
            $db->create_tables();

            // Schedule cron jobs
            if (!wp_next_scheduled('aincc_fetch_sources')) {
                wp_schedule_event(time() + 60, 'every_5_minutes', 'aincc_fetch_sources');
            }
            if (!wp_next_scheduled('aincc_process_queue')) {
                wp_schedule_event(time() + 120, 'every_2_minutes', 'aincc_process_queue');
            }

            // Get source count
            global $wpdb;
            $source_count = $wpdb->get_var("SELECT COUNT(*) FROM {$db->table('sources')}");

            AINCC_Logger::info('Plugin reinitialized', ['sources' => $source_count]);

            return new WP_REST_Response([
                'success' => true,
                'message' => "Плагин переинициализирован. Загружено источников: {$source_count}",
                'sources_count' => (int) $source_count,
            ], 200);
        } catch (Exception $e) {
            AINCC_Logger::error('Reinitialize failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search images
     */
    public function search_images($request) {
        $image_handler = new AINCC_Image_Handler();

        // Search multiple images
        $api_key = AINCC_Settings::get('pexels_api_key');

        $url = add_query_arg([
            'query' => urlencode($request->get_param('query')),
            'per_page' => $request->get_param('per_page'),
            'orientation' => 'landscape',
        ], 'https://api.pexels.com/v1/search');

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => $api_key],
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response(['success' => false, 'error' => 'API error'], 400);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        $images = [];
        if (!empty($body['photos'])) {
            foreach ($body['photos'] as $photo) {
                $images[] = [
                    'id' => $photo['id'],
                    'url' => $photo['src']['large'],
                    'url_small' => $photo['src']['medium'],
                    'author' => $photo['photographer'],
                    'source' => 'Pexels',
                    'license' => 'Pexels License',
                    'alt' => $photo['alt'] ?? '',
                ];
            }
        }

        return new WP_REST_Response(['items' => $images], 200);
    }

    /**
     * Format draft for API response
     */
    private function format_draft($draft, $full = false) {
        $formatted = [
            'id' => $draft['id'],
            'lang' => $draft['lang'],
            'status' => $draft['status'],
            'title' => $draft['title'],
            'category' => $draft['category'],
            'source_name' => $draft['source_name'] ?? null,
            'source_trust' => $draft['source_trust'] ?? null,
            'created_at' => $draft['created_at'],
            'updated_at' => $draft['updated_at'],
            'risk_flags' => json_decode($draft['risk_flags'] ?? '[]', true),
        ];

        if ($full) {
            $formatted = array_merge($formatted, [
                'lead' => $draft['lead'],
                'body_html' => $draft['body_html'],
                'sources' => json_decode($draft['sources'] ?? '[]', true),
                'seo_title' => $draft['seo_title'],
                'meta_description' => $draft['meta_description'],
                'slug' => $draft['slug'],
                'keywords' => json_decode($draft['keywords'] ?? '[]', true),
                'tags' => json_decode($draft['tags'] ?? '[]', true),
                'geo_tags' => json_decode($draft['geo_tags'] ?? '[]', true),
                'image_url' => $draft['image_url'],
                'image_author' => $draft['image_author'],
                'image_alt' => $draft['image_alt'],
                'sentiment' => $draft['sentiment'],
                'gate_reason' => $draft['gate_reason'],
                'scheduled_at' => $draft['scheduled_at'],
                'published_at' => $draft['published_at'],
                'wp_post_id' => $draft['wp_post_id'],
            ]);
        }

        return $formatted;
    }

    /**
     * Convert period string to days
     */
    private function period_to_days($period) {
        $map = [
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
        ];

        return $map[$period] ?? 7;
    }

    /**
     * Check and improve text with AI
     */
    public function check_text_with_ai($request) {
        $text = $request->get_param('text');
        $lang = $request->get_param('lang') ?: 'de';

        if (empty($text)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Текст не указан',
            ], 400);
        }

        try {
            $ai = AINCC_AI_Provider_Factory::create();

            $prompt = "Улучши этот текст, исправь грамматические ошибки, сделай его более читаемым и профессиональным. Сохрани смысл и факты. Верни ТОЛЬКО улучшенный текст без пояснений:";

            $result = $ai->complete($text, $prompt);

            if ($result['success']) {
                return new WP_REST_Response([
                    'success' => true,
                    'improved' => trim($result['content']),
                    'original' => $text,
                ], 200);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'AI недоступен',
            ], 400);

        } catch (Exception $e) {
            AINCC_Logger::error('AI check text failed', ['error' => $e->getMessage()]);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ошибка AI: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle source enabled status
     */
    public function toggle_source($request) {
        global $wpdb;
        $id = $request->get_param('id');

        $db = new AINCC_Database();
        $source = $db->get_source($id);

        if (!$source) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Источник не найден',
            ], 404);
        }

        $new_status = $source['enabled'] ? 0 : 1;

        $result = $wpdb->update(
            $db->table('sources'),
            ['enabled' => $new_status],
            ['id' => $id]
        );

        if ($result !== false) {
            AINCC_Logger::info('Source toggled', ['id' => $id, 'enabled' => $new_status]);
            return new WP_REST_Response([
                'success' => true,
                'enabled' => (bool) $new_status,
                'message' => $new_status ? 'Источник включен' : 'Источник отключен',
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Ошибка обновления',
        ], 500);
    }
}
