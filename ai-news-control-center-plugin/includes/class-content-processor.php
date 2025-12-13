<?php
/**
 * Content Processor
 * Handles AI-powered content generation, rewriting, translation, and SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Content_Processor {

    /**
     * Database instance
     */
    private $db;

    /**
     * AI Provider instance
     */
    private $ai;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new AINCC_Database();
        $this->ai = AINCC_AI_Provider_Factory::create();

        // Register cron hook
        add_action('aincc_process_queue', [$this, 'process_queue']);
    }

    /**
     * Process items in queue
     */
    public function process_queue() {
        AINCC_Logger::info('Starting content processing queue');

        $max_items = 5; // Process max 5 items per cycle
        $processed = 0;

        while ($processed < $max_items) {
            $job = $this->db->get_next_queue_job('process_item');

            if (!$job) {
                break;
            }

            // Mark as processing
            $this->db->update_queue_job($job['id'], [
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'attempts' => $job['attempts'] + 1,
            ]);

            try {
                $payload = json_decode($job['payload'], true);
                $result = $this->process_item($payload['raw_item_id']);

                $this->db->update_queue_job($job['id'], [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                ]);

                AINCC_Logger::info("Processed item {$payload['raw_item_id']}", $result);

            } catch (Exception $e) {
                AINCC_Logger::error("Processing failed for item", [
                    'job_id' => $job['id'],
                    'error' => $e->getMessage(),
                ]);

                $this->db->update_queue_job($job['id'], [
                    'status' => $job['attempts'] >= $job['max_attempts'] ? 'failed' : 'pending',
                    'error_message' => $e->getMessage(),
                ]);
            }

            $processed++;
        }

        AINCC_Logger::info("Queue processing complete", ['processed' => $processed]);
    }

    /**
     * Process a single raw item
     */
    public function process_item($raw_item_id) {
        global $wpdb;

        // Get raw item with source info
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ri.*, s.name as source_name, s.trust_score, s.category as source_category
                 FROM {$this->db->table('raw_items')} ri
                 LEFT JOIN {$this->db->table('sources')} s ON ri.source_id = s.id
                 WHERE ri.id = %d",
                $raw_item_id
            ),
            ARRAY_A
        );

        if (!$item) {
            throw new Exception("Raw item not found: {$raw_item_id}");
        }

        // Update status to processing
        $wpdb->update(
            $this->db->table('raw_items'),
            ['status' => 'processing'],
            ['id' => $raw_item_id]
        );

        // Step 1: Extract entities and classify
        $analysis = $this->analyze_content($item);

        // Update raw item with analysis
        $wpdb->update(
            $this->db->table('raw_items'),
            [
                'entities' => json_encode($analysis['entities']),
                'keywords' => json_encode($analysis['keywords']),
            ],
            ['id' => $raw_item_id]
        );

        // Step 2: Check for duplicates (content fingerprint)
        if ($this->is_duplicate($item, $analysis)) {
            $wpdb->update(
                $this->db->table('raw_items'),
                ['status' => 'duplicate'],
                ['id' => $raw_item_id]
            );
            return ['status' => 'duplicate'];
        }

        // Step 3: Simple fact check (cross-reference)
        $fact_check = $this->simple_fact_check($item, $analysis);

        // Step 4: Generate content for each target language
        $target_languages = AINCC_Settings::get('target_languages', ['de', 'ua', 'ru', 'en']);
        $source_lang = $item['lang'] ?: 'de';

        $drafts = [];
        foreach ($target_languages as $lang) {
            $draft = $this->generate_draft($item, $analysis, $source_lang, $lang, $fact_check);
            if ($draft) {
                $drafts[$lang] = $draft;
            }
        }

        // Step 5: Update raw item status
        $wpdb->update(
            $this->db->table('raw_items'),
            [
                'status' => 'processed',
                'fact_check_score' => $fact_check['score'],
            ],
            ['id' => $raw_item_id]
        );

        return [
            'status' => 'success',
            'drafts' => count($drafts),
            'languages' => array_keys($drafts),
            'fact_check_score' => $fact_check['score'],
        ];
    }

    /**
     * Analyze content (entities, keywords, classification)
     */
    private function analyze_content($item) {
        $content = $item['title'] . "\n\n" . ($item['body_html'] ?: $item['summary']);

        // Use AI to extract entities
        $result = $this->ai->extract_entities($content);

        if (!$result['success']) {
            AINCC_Logger::warning("Entity extraction failed, using fallback", [
                'item_id' => $item['id'],
            ]);

            // Fallback: basic keyword extraction
            return [
                'entities' => [
                    'persons' => [],
                    'organizations' => [],
                    'locations' => [],
                    'dates' => [],
                ],
                'keywords' => $this->extract_keywords_simple($content),
                'category' => $this->guess_category($content),
                'sentiment' => 0,
                'geo' => [],
            ];
        }

        return $result;
    }

    /**
     * Simple keyword extraction fallback
     */
    private function extract_keywords_simple($content) {
        // Remove HTML
        $text = wp_strip_all_tags($content);

        // Get words
        $words = str_word_count(strtolower($text), 1, 'äöüßÄÖÜ');

        // Filter stop words (basic German stop words)
        $stop_words = ['der', 'die', 'das', 'und', 'ist', 'in', 'von', 'mit', 'für',
            'auf', 'den', 'des', 'dem', 'ein', 'eine', 'einer', 'als', 'auch',
            'es', 'an', 'werden', 'aus', 'er', 'hat', 'dass', 'sie', 'nach',
            'wird', 'bei', 'einer', 'um', 'am', 'sind', 'noch', 'wie', 'einem',
            'über', 'einen', 'so', 'zum', 'kann', 'nur', 'sein', 'ich', 'nicht',
            'the', 'and', 'is', 'in', 'to', 'of', 'for', 'a', 'on', 'with'];

        $filtered = array_filter($words, function ($word) use ($stop_words) {
            return strlen($word) > 3 && !in_array($word, $stop_words);
        });

        // Count frequency
        $freq = array_count_values($filtered);
        arsort($freq);

        // Return top 10
        return array_slice(array_keys($freq), 0, 10);
    }

    /**
     * Guess category from content
     */
    private function guess_category($content) {
        $content_lower = strtolower($content);

        $category_keywords = [
            'politik' => ['bundestag', 'regierung', 'minister', 'partei', 'wahl', 'gesetz', 'politik'],
            'wirtschaft' => ['wirtschaft', 'unternehmen', 'aktie', 'börse', 'euro', 'inflation', 'arbeitsmarkt'],
            'migration' => ['aufenthaltstitel', 'bamf', 'flüchtling', 'asyl', 'integration', 'migration', 'ukrainer'],
            'gesellschaft' => ['bildung', 'schule', 'universität', 'kultur', 'sozial', 'gesellschaft'],
            'verkehr' => ['mvg', 'bahn', 'verkehr', 'stau', 'bus', 'u-bahn', 's-bahn', 'fahrplan'],
            'lokales' => ['münchen', 'bayern', 'rathaus', 'stadt', 'bezirk', 'gemeinde'],
            'wetter' => ['wetter', 'unwetter', 'warnung', 'sturm', 'regen', 'temperatur'],
        ];

        $scores = [];
        foreach ($category_keywords as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($content_lower, $keyword);
            }
            $scores[$category] = $score;
        }

        arsort($scores);
        $top = array_keys($scores)[0];

        return $scores[$top] > 0 ? $top : 'nachrichten';
    }

    /**
     * Check for duplicate content
     */
    private function is_duplicate($item, $analysis) {
        global $wpdb;

        // Check by title similarity in recent items (last 72 hours)
        $similar = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->db->table('raw_items')}
                 WHERE id != %d
                 AND fetched_at > DATE_SUB(NOW(), INTERVAL 72 HOUR)
                 AND (
                     title = %s
                     OR url = %s
                 )",
                $item['id'],
                $item['title'],
                $item['url']
            )
        );

        return $similar > 0;
    }

    /**
     * Simple fact check (cross-reference sources)
     */
    private function simple_fact_check($item, $analysis) {
        global $wpdb;

        $score = 0.5; // Base score
        $confirmations = 0;
        $sources_checked = 0;

        // Check if similar content exists from other sources
        $keywords = $analysis['keywords'] ?? [];
        $title_words = array_slice(explode(' ', $item['title']), 0, 5);

        if (!empty($keywords)) {
            // Search for similar items from different sources
            $keyword_pattern = implode('|', array_slice($keywords, 0, 3));

            $similar_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ri.source_id, s.trust_score
                     FROM {$this->db->table('raw_items')} ri
                     JOIN {$this->db->table('sources')} s ON ri.source_id = s.id
                     WHERE ri.id != %d
                     AND ri.source_id != %s
                     AND ri.fetched_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     AND (ri.title REGEXP %s OR ri.summary REGEXP %s)
                     GROUP BY ri.source_id",
                    $item['id'],
                    $item['source_id'],
                    $keyword_pattern,
                    $keyword_pattern
                ),
                ARRAY_A
            );

            $sources_checked = count($similar_items);
            foreach ($similar_items as $similar) {
                $confirmations++;
                // Add weighted score based on source trust
                $score += ($similar['trust_score'] * 0.1);
            }
        }

        // Factor in source trust
        $source_trust = $item['trust_score'] ?? 0.5;
        $score = ($score * 0.6) + ($source_trust * 0.4);

        // Normalize score
        $score = min(1.0, max(0.0, $score));

        // Store fact check result
        $wpdb->insert(
            $this->db->table('fact_checks'),
            [
                'raw_item_id' => $item['id'],
                'claims' => json_encode([]),
                'score' => $score,
                'sources_confirmed' => $confirmations,
            ]
        );

        return [
            'score' => round($score, 2),
            'confirmations' => $confirmations,
            'sources_checked' => $sources_checked,
        ];
    }

    /**
     * Generate draft for a specific language
     */
    private function generate_draft($item, $analysis, $source_lang, $target_lang, $fact_check) {
        $draft_id = 'draft_' . uniqid() . '_' . $target_lang;

        // Prepare source info
        $sources = [
            [
                'name' => $item['source_name'],
                'url' => $item['url'],
                'date' => date('d.m.Y', strtotime($item['published_at'])),
                'title' => $item['title'],
                'summary' => substr($item['summary'], 0, 500),
            ],
        ];

        // Step 1: Rewrite content (or translate if not source language)
        $content = $item['body_html'] ?: $item['summary'];

        if ($target_lang === $source_lang) {
            // Rewrite in same language
            $style = "News article for Ukrainian audience in Germany. Clear, factual, helpful.";
            $rewritten = $this->ai->rewrite($content, $style, $target_lang);
        } else {
            // First rewrite in source language, then translate
            $style = "News article for Ukrainian audience in Germany. Clear, factual, helpful.";
            $rewritten = $this->ai->rewrite($content, $style, $source_lang);

            if ($rewritten['success']) {
                $rewritten = $this->ai->translate($rewritten['content'], $source_lang, $target_lang);
            }
        }

        if (!$rewritten['success']) {
            AINCC_Logger::warning("Content generation failed", [
                'item_id' => $item['id'],
                'language' => $target_lang,
                'error' => $rewritten['error'] ?? 'Unknown',
            ]);
            return null;
        }

        // Parse generated content
        $parsed = $this->parse_generated_content($rewritten['content'], $target_lang);

        // Step 2: Generate SEO
        $seo = $this->ai->generate_seo($rewritten['content'], $target_lang);

        // Step 3: Determine status (auto or needs approval)
        $status = $this->determine_draft_status($item, $analysis, $fact_check);

        // Step 4: Save draft
        $draft_data = [
            'id' => $draft_id,
            'event_id' => $item['event_id'],
            'raw_item_id' => $item['id'],
            'lang' => $target_lang,
            'title' => $parsed['title'] ?: $item['title'],
            'lead' => $parsed['lead'],
            'body_html' => $parsed['body'],
            'sources' => json_encode($sources),
            'structured_data' => json_encode([
                'what' => $parsed['sections']['what'] ?? '',
                'why' => $parsed['sections']['why'] ?? '',
                'action' => $parsed['sections']['action'] ?? '',
            ]),
            'sentiment' => $analysis['sentiment'] ?? 0,
            'category' => $analysis['category'] ?? 'nachrichten',
            'tags' => json_encode($analysis['keywords'] ?? []),
            'geo_tags' => json_encode($analysis['geo'] ?? []),
            'risk_flags' => json_encode($this->get_risk_flags($item, $analysis)),
            'seo_title' => $seo['success'] ? $seo['title'] : $parsed['title'],
            'meta_description' => $seo['success'] ? $seo['description'] : $parsed['lead'],
            'slug' => $seo['success'] ? $seo['slug'] : sanitize_title($parsed['title']),
            'keywords' => $seo['success'] ? json_encode($seo['keywords']) : json_encode($analysis['keywords']),
            'status' => $status,
            'gate_reason' => $status === 'pending_ok' ? $this->get_gate_reason($item, $analysis, $fact_check) : null,
        ];

        $this->db->insert_draft($draft_data);

        return $draft_data;
    }

    /**
     * Parse AI-generated content
     */
    private function parse_generated_content($content, $language) {
        $result = [
            'title' => '',
            'lead' => '',
            'body' => $content,
            'sections' => [],
        ];

        // Try to extract title
        if (preg_match('/<title>(.*?)<\/title>/is', $content, $matches)) {
            $result['title'] = trim(strip_tags($matches[1]));
            $content = str_replace($matches[0], '', $content);
        }

        // Try to extract lead
        if (preg_match('/<lead>(.*?)<\/lead>/is', $content, $matches)) {
            $result['lead'] = trim(strip_tags($matches[1]));
            $content = str_replace($matches[0], '', $content);
        }

        // Extract sections
        if (preg_match('/<section[^>]*id="what"[^>]*>(.*?)<\/section>/is', $content, $matches)) {
            $result['sections']['what'] = trim($matches[1]);
        }
        if (preg_match('/<section[^>]*id="why"[^>]*>(.*?)<\/section>/is', $content, $matches)) {
            $result['sections']['why'] = trim($matches[1]);
        }
        if (preg_match('/<section[^>]*id="action"[^>]*>(.*?)<\/section>/is', $content, $matches)) {
            $result['sections']['action'] = trim($matches[1]);
        }

        // Clean remaining content for body
        $result['body'] = trim($content);

        // If no title was extracted, try to get first line
        if (empty($result['title'])) {
            $lines = explode("\n", strip_tags($content));
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) < 100) {
                    $result['title'] = $line;
                    break;
                }
            }
        }

        // If no lead, get first paragraph
        if (empty($result['lead'])) {
            if (preg_match('/<p>(.*?)<\/p>/is', $content, $matches)) {
                $result['lead'] = trim(strip_tags($matches[1]));
            }
        }

        return $result;
    }

    /**
     * Determine draft status
     */
    private function determine_draft_status($item, $analysis, $fact_check) {
        // Categories requiring approval
        $approval_categories = AINCC_Settings::get('categories_require_approval', ['politik', 'wirtschaft']);

        // Check if category requires approval
        if (in_array($analysis['category'], $approval_categories)) {
            return 'pending_ok';
        }

        // Low fact check score
        if ($fact_check['score'] < AINCC_Settings::get('fact_check_threshold', 0.6)) {
            return 'pending_ok';
        }

        // Low source trust
        if (($item['trust_score'] ?? 0.5) < AINCC_Settings::get('source_trust_threshold', 0.7)) {
            return 'pending_ok';
        }

        // Check for sensitive keywords
        $sensitive_keywords = ['krieg', 'konflikt', 'tod', 'unfall', 'krise', 'skandal'];
        $content_lower = strtolower($item['title'] . ' ' . $item['summary']);
        foreach ($sensitive_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                return 'pending_ok';
            }
        }

        // Auto-publish enabled?
        if (!AINCC_Settings::get('auto_publish_enabled', false)) {
            return 'pending_ok';
        }

        return 'auto_ready';
    }

    /**
     * Get risk flags for content
     */
    private function get_risk_flags($item, $analysis) {
        $flags = [];

        // Category-based flags
        if (in_array($analysis['category'], ['politik', 'wirtschaft'])) {
            $flags[] = $analysis['category'];
            $flags[] = 'sensitive';
        }

        // Low trust source
        if (($item['trust_score'] ?? 0.5) < 0.7) {
            $flags[] = 'low_trust_source';
        }

        // Sensitive content keywords
        $sensitive_keywords = ['krieg', 'konflikt', 'tod', 'unfall', 'krise', 'skandal', 'korruption'];
        $content_lower = strtolower($item['title'] . ' ' . $item['summary']);
        foreach ($sensitive_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $flags[] = 'sensitive_content';
                break;
            }
        }

        return array_unique($flags);
    }

    /**
     * Get reason for requiring approval
     */
    private function get_gate_reason($item, $analysis, $fact_check) {
        $reasons = [];

        if (in_array($analysis['category'], ['politik', 'wirtschaft'])) {
            $reasons[] = 'category_requires_review';
        }

        if ($fact_check['score'] < 0.6) {
            $reasons[] = 'low_fact_check_score';
        }

        if (($item['trust_score'] ?? 0.5) < 0.7) {
            $reasons[] = 'low_source_trust';
        }

        return implode(', ', $reasons) ?: 'manual_review';
    }

    /**
     * Process manual article submission
     */
    public function process_manual_article($data) {
        $draft_id_base = 'manual_' . uniqid();
        $source_lang = $data['source_lang'] ?? 'de';
        $target_langs = $data['target_langs'] ?? ['de', 'ua', 'ru', 'en'];

        $drafts = [];

        // First create draft in source language
        $source_draft_id = $draft_id_base . '_' . $source_lang;

        // Generate SEO for source language
        $content = $data['title'] . "\n\n" . $data['lead'] . "\n\n" . $data['body'];
        $seo = $this->ai->generate_seo($content, $source_lang);

        // Extract entities/keywords
        $analysis = $this->ai->extract_entities($content);

        $source_draft = [
            'id' => $source_draft_id,
            'event_id' => null,
            'raw_item_id' => null,
            'lang' => $source_lang,
            'title' => $data['title'],
            'lead' => $data['lead'],
            'body_html' => $data['body'],
            'sources' => json_encode($data['sources'] ?? []),
            'category' => $data['category'] ?? 'nachrichten',
            'tags' => json_encode($data['tags'] ?? []),
            'geo_tags' => json_encode($data['geo'] ?? []),
            'seo_title' => $seo['success'] ? $seo['title'] : substr($data['title'], 0, 60),
            'meta_description' => $seo['success'] ? $seo['description'] : substr($data['lead'], 0, 155),
            'slug' => $seo['success'] ? $seo['slug'] : sanitize_title($data['title']),
            'keywords' => $seo['success'] ? json_encode($seo['keywords']) : json_encode($analysis['keywords'] ?? []),
            'status' => 'pending_ok',
            'gate_reason' => 'manual_submission',
            'created_by' => get_current_user_id(),
        ];

        $this->db->insert_draft($source_draft);
        $drafts[$source_lang] = $source_draft_id;

        // Translate to other languages
        foreach ($target_langs as $target_lang) {
            if ($target_lang === $source_lang) {
                continue;
            }

            $translated_title = $this->ai->translate($data['title'], $source_lang, $target_lang);
            $translated_lead = $this->ai->translate($data['lead'], $source_lang, $target_lang);
            $translated_body = $this->ai->translate($data['body'], $source_lang, $target_lang);

            if (!$translated_title['success'] || !$translated_body['success']) {
                AINCC_Logger::warning("Translation failed for {$target_lang}");
                continue;
            }

            $target_draft_id = $draft_id_base . '_' . $target_lang;

            // Generate SEO for target language
            $target_content = $translated_title['content'] . "\n\n" . ($translated_lead['content'] ?? '') . "\n\n" . $translated_body['content'];
            $target_seo = $this->ai->generate_seo($target_content, $target_lang);

            $target_draft = [
                'id' => $target_draft_id,
                'event_id' => null,
                'raw_item_id' => null,
                'lang' => $target_lang,
                'title' => trim($translated_title['content']),
                'lead' => trim($translated_lead['content'] ?? ''),
                'body_html' => $translated_body['content'],
                'sources' => json_encode($data['sources'] ?? []),
                'category' => $data['category'] ?? 'nachrichten',
                'tags' => json_encode($data['tags'] ?? []),
                'geo_tags' => json_encode($data['geo'] ?? []),
                'seo_title' => $target_seo['success'] ? $target_seo['title'] : substr($translated_title['content'], 0, 60),
                'meta_description' => $target_seo['success'] ? $target_seo['description'] : substr($translated_lead['content'] ?? '', 0, 155),
                'slug' => $target_seo['success'] ? $target_seo['slug'] : sanitize_title($translated_title['content']),
                'keywords' => $target_seo['success'] ? json_encode($target_seo['keywords']) : json_encode([]),
                'status' => 'pending_ok',
                'gate_reason' => 'manual_submission',
                'created_by' => get_current_user_id(),
            ];

            $this->db->insert_draft($target_draft);
            $drafts[$target_lang] = $target_draft_id;
        }

        return [
            'success' => true,
            'draft_id' => $draft_id_base,
            'drafts' => $drafts,
        ];
    }

    /**
     * Process article from URL
     */
    public function process_article_from_url($url, $source_lang = 'de', $target_langs = ['de', 'ua', 'ru', 'en'], $category = 'nachrichten') {
        // Fetch content from URL
        $parser = new AINCC_RSS_Parser();
        $fetched = $parser->fetch_full_content($url);

        if (!$fetched || empty($fetched['text'])) {
            return [
                'success' => false,
                'error' => 'Не удалось получить содержимое страницы',
            ];
        }

        $original_content = $fetched['html'] ?: $fetched['text'];

        // Use AI to extract article data
        $extraction_prompt = "Проанализируй HTML-контент и извлеки статью в формате JSON:
{
  \"title\": \"заголовок статьи\",
  \"lead\": \"первый абзац или лид (1-2 предложения)\",
  \"body\": \"основной текст статьи в HTML формате\",
  \"author\": \"автор если есть\",
  \"date\": \"дата публикации если есть\"
}

Верни ТОЛЬКО JSON, никаких пояснений.";

        $extracted = $this->ai->complete($original_content, $extraction_prompt);

        if (!$extracted['success']) {
            // Fallback: use simple extraction
            $title = $this->extract_title_from_html($original_content);
            $body = $fetched['text'];
            $lead = substr($body, 0, 200) . '...';
        } else {
            // Parse JSON response
            $data = json_decode($extracted['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to extract JSON from response
                if (preg_match('/\{[\s\S]*\}/', $extracted['content'], $matches)) {
                    $data = json_decode($matches[0], true);
                }
            }

            if (!$data) {
                $title = $this->extract_title_from_html($original_content);
                $body = $fetched['text'];
                $lead = substr($body, 0, 200) . '...';
            } else {
                $title = $data['title'] ?? '';
                $lead = $data['lead'] ?? '';
                $body = $data['body'] ?? $fetched['text'];
            }
        }

        if (empty($title)) {
            return [
                'success' => false,
                'error' => 'Не удалось извлечь заголовок',
            ];
        }

        // Now rewrite the article using AI for better quality
        $full_content = $title . "\n\n" . $lead . "\n\n" . $body;
        $rewritten = $this->ai->rewrite($full_content, 'News article for Ukrainian audience in Germany. Professional, factual style like Deutsche Welle.', $source_lang);

        if ($rewritten['success']) {
            $parsed = $this->parse_generated_content($rewritten['content'], $source_lang);
            if ($parsed['title']) {
                $title = $parsed['title'];
            }
            if ($parsed['lead']) {
                $lead = $parsed['lead'];
            }
            if ($parsed['body']) {
                $body = $parsed['body'];
            }
        }

        // Create article data for manual processing
        $article_data = [
            'title' => $title,
            'lead' => $lead,
            'body' => $body,
            'source_lang' => $source_lang,
            'target_langs' => $target_langs,
            'category' => $category,
            'sources' => [
                [
                    'name' => parse_url($url, PHP_URL_HOST),
                    'url' => $url,
                    'date' => date('d.m.Y'),
                ]
            ],
        ];

        // Use existing manual article processor
        return $this->process_manual_article($article_data);
    }

    /**
     * Extract title from HTML
     */
    private function extract_title_from_html($html) {
        // Try <h1>
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return strip_tags($matches[1]);
        }
        // Try <title>
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return strip_tags($matches[1]);
        }
        // Try og:title
        if (preg_match('/property=["\']og:title["\'][^>]*content=["\']([^"\']+)/is', $html, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Regenerate content for a draft
     */
    public function regenerate_draft($draft_id, $what = 'all', $instructions = '') {
        $draft = $this->db->get_draft($draft_id);

        if (!$draft) {
            return ['success' => false, 'error' => 'Draft not found'];
        }

        $content = $draft['title'] . "\n\n" . $draft['lead'] . "\n\n" . $draft['body_html'];

        switch ($what) {
            case 'title':
                $prompt = "Generate a new, compelling title (max 60 chars) for this article:\n\n{$content}";
                if ($instructions) {
                    $prompt .= "\n\nAdditional instructions: {$instructions}";
                }
                $result = $this->ai->complete($prompt, "Generate only the title, nothing else.");

                if ($result['success']) {
                    $this->db->update_draft($draft_id, ['title' => trim($result['content'])]);
                }
                break;

            case 'seo':
                $seo = $this->ai->generate_seo($content, $draft['lang']);
                if ($seo['success']) {
                    $this->db->update_draft($draft_id, [
                        'seo_title' => $seo['title'],
                        'meta_description' => $seo['description'],
                        'slug' => $seo['slug'],
                        'keywords' => json_encode($seo['keywords']),
                    ]);
                }
                break;

            case 'translate':
                // Re-translate from original
                if ($draft['raw_item_id']) {
                    global $wpdb;
                    $raw_item = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$this->db->table('raw_items')} WHERE id = %d",
                            $draft['raw_item_id']
                        ),
                        ARRAY_A
                    );

                    if ($raw_item) {
                        $translated = $this->ai->translate(
                            $raw_item['body_html'] ?: $raw_item['summary'],
                            $raw_item['lang'],
                            $draft['lang']
                        );

                        if ($translated['success']) {
                            $parsed = $this->parse_generated_content($translated['content'], $draft['lang']);
                            $this->db->update_draft($draft_id, [
                                'title' => $parsed['title'] ?: $draft['title'],
                                'lead' => $parsed['lead'],
                                'body_html' => $parsed['body'],
                            ]);
                        }
                    }
                }
                break;

            case 'all':
            default:
                // Full rewrite
                $style = "News article for Ukrainian audience in Germany. {$instructions}";
                $rewritten = $this->ai->rewrite($content, $style, $draft['lang']);

                if ($rewritten['success']) {
                    $parsed = $this->parse_generated_content($rewritten['content'], $draft['lang']);
                    $seo = $this->ai->generate_seo($rewritten['content'], $draft['lang']);

                    $this->db->update_draft($draft_id, [
                        'title' => $parsed['title'] ?: $draft['title'],
                        'lead' => $parsed['lead'],
                        'body_html' => $parsed['body'],
                        'seo_title' => $seo['success'] ? $seo['title'] : $parsed['title'],
                        'meta_description' => $seo['success'] ? $seo['description'] : $parsed['lead'],
                        'slug' => $seo['success'] ? $seo['slug'] : sanitize_title($parsed['title']),
                        'keywords' => $seo['success'] ? json_encode($seo['keywords']) : $draft['keywords'],
                    ]);
                }
                break;
        }

        return ['success' => true];
    }
}
