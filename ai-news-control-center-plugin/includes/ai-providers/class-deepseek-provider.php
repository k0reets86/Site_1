<?php
/**
 * DeepSeek AI Provider
 * Implementation for DeepSeek API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_DeepSeek_Provider implements AINCC_AI_Provider_Interface {

    private $api_key;
    private $model;
    private $base_url;

    /**
     * Constructor
     */
    public function __construct($config) {
        $this->api_key = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'deepseek-chat';
        $this->base_url = $config['base_url'] ?? 'https://api.deepseek.com';
    }

    /**
     * Get provider name
     */
    public function get_name() {
        return 'DeepSeek';
    }

    /**
     * Make API request
     */
    private function request($endpoint, $data) {
        $start_time = microtime(true);

        $url = rtrim($this->base_url, '/') . $endpoint;

        $response = wp_remote_post($url, [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
        ]);

        $duration = (microtime(true) - $start_time) * 1000;

        if (is_wp_error($response)) {
            AINCC_Logger::error('DeepSeek API Error', [
                'error' => $response->get_error_message(),
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        AINCC_Logger::api_request('DeepSeek', $endpoint, $data, $body, $duration);

        if ($status_code !== 200) {
            $error = $body['error']['message'] ?? 'Unknown API error';
            AINCC_Logger::error('DeepSeek API Error', [
                'status' => $status_code,
                'error' => $error,
            ]);

            return [
                'success' => false,
                'error' => $error,
            ];
        }

        return [
            'success' => true,
            'data' => $body,
        ];
    }

    /**
     * Generate text completion
     */
    public function complete($prompt, $system_prompt = '', $options = []) {
        $messages = [];

        if (!empty($system_prompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $system_prompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $data = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000,
        ];

        $response = $this->request('/v1/chat/completions', $data);

        if (!$response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'content' => $response['data']['choices'][0]['message']['content'] ?? '',
            'usage' => $response['data']['usage'] ?? [],
        ];
    }

    /**
     * Rewrite content
     */
    public function rewrite($content, $style, $language = 'de') {
        $lang_names = [
            'de' => 'German',
            'ua' => 'Ukrainian',
            'ru' => 'Russian',
            'en' => 'English',
        ];

        $lang_name = $lang_names[$language] ?? 'German';

        $system_prompt = <<<PROMPT
You are a professional news editor for a news platform serving Ukrainians in Munich/Bavaria/Germany.

Your task:
1. Completely rewrite the provided article in {$lang_name}
2. Create UNIQUE content - no plagiarism, no direct quotes (except very short official statements)
3. Use your own words and phrasing
4. Maintain factual accuracy
5. Target audience: Ukrainians in Germany (A2-B2 German level if writing in German)
6. Tone: Neutral, factual, easy to understand

Style guidelines:
{$style}

Output format:
- Write only the rewritten article
- Do not include any meta-commentary or explanations
- Structure: Title, Lead (2-3 sentences), Body with sections
PROMPT;

        $prompt = "Rewrite this article:\n\n" . $content;

        return $this->complete($prompt, $system_prompt, ['temperature' => 0.7, 'max_tokens' => 4000]);
    }

    /**
     * Translate content
     */
    public function translate($content, $source_lang, $target_lang, $glossary = []) {
        $lang_names = [
            'de' => 'German',
            'ua' => 'Ukrainian',
            'ru' => 'Russian',
            'en' => 'English',
        ];

        $source_name = $lang_names[$source_lang] ?? $source_lang;
        $target_name = $lang_names[$target_lang] ?? $target_lang;

        $glossary_text = '';
        if (!empty($glossary)) {
            $glossary_text = "\n\nGlossary - use these exact translations:\n";
            foreach ($glossary as $term => $translation) {
                $glossary_text .= "- {$term} = {$translation}\n";
            }
        }

        // Default glossary for Ukrainian audience
        $default_glossary = [
            'de' => [
                'Aufenthaltstitel' => 'residence permit (посвідка на проживання)',
                'BAMF' => 'BAMF (do not translate)',
                'Jobcenter' => 'Jobcenter (do not translate)',
                'Bürgergeld' => 'Bürgergeld / citizen\'s benefit',
                'Ausländerbehörde' => 'foreigners\' registration office',
            ],
            'ua' => [
                'Aufenthaltstitel' => 'посвідка на проживання',
                'BAMF' => 'BAMF',
                'Jobcenter' => 'Jobcenter (центр зайнятості)',
                'Bürgergeld' => 'Bürgergeld (допомога громадянам)',
                'Ausländerbehörde' => 'відділ у справах іноземців',
            ],
            'ru' => [
                'Aufenthaltstitel' => 'вид на жительство',
                'BAMF' => 'BAMF',
                'Jobcenter' => 'Jobcenter (центр занятости)',
                'Bürgergeld' => 'Bürgergeld (пособие)',
                'Ausländerbehörde' => 'ведомство по делам иностранцев',
            ],
        ];

        if (isset($default_glossary[$target_lang])) {
            $glossary = array_merge($default_glossary[$target_lang], $glossary);
            $glossary_text = "\n\nGlossary - use these exact translations:\n";
            foreach ($glossary as $term => $translation) {
                $glossary_text .= "- {$term} = {$translation}\n";
            }
        }

        $system_prompt = <<<PROMPT
You are a professional translator specializing in news content for Ukrainian audiences in Germany.

Translate from {$source_name} to {$target_name}.

Guidelines:
1. Maintain the original meaning and tone
2. Keep the HTML structure intact (preserve all tags)
3. Do NOT translate URLs, source names, or proper nouns that should stay original
4. Use natural, fluent {$target_name} - not word-for-word translation
5. Adapt idioms and expressions appropriately
6. Keep numbers and dates in the same format
{$glossary_text}

Output only the translated text, nothing else.
PROMPT;

        return $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 6000]);
    }

    /**
     * Generate SEO metadata
     */
    public function generate_seo($content, $language = 'de') {
        $lang_names = [
            'de' => 'German',
            'ua' => 'Ukrainian',
            'ru' => 'Russian',
            'en' => 'English',
        ];

        $lang_name = $lang_names[$language] ?? 'German';

        $system_prompt = <<<PROMPT
You are an SEO expert for a news website targeting Ukrainians in Germany.

Generate SEO metadata in {$lang_name} for the provided article.

Requirements:
1. SEO Title: Max 60 characters, include main keyword near beginning, compelling
2. Meta Description: Max 155 characters, include primary keyword, encourage clicks
3. Keywords: 5-10 relevant keywords/phrases
4. Slug: URL-friendly, lowercase, hyphens instead of spaces, max 60 chars

Output as JSON:
{
  "title": "SEO title here",
  "description": "Meta description here",
  "keywords": ["keyword1", "keyword2", ...],
  "slug": "url-slug-here"
}
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.5, 'max_tokens' => 500]);

        if (!$result['success']) {
            return $result;
        }

        // Parse JSON from response
        $json_content = $result['content'];

        // Try to extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $json_content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'title' => $parsed['title'] ?? '',
                    'description' => $parsed['description'] ?? '',
                    'keywords' => $parsed['keywords'] ?? [],
                    'slug' => $parsed['slug'] ?? '',
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to parse SEO response',
        ];
    }

    /**
     * Extract entities and keywords
     */
    public function extract_entities($content) {
        $system_prompt = <<<PROMPT
Analyze the provided news article and extract:
1. Named entities (persons, organizations, locations, dates)
2. Main topics/keywords
3. Geographic relevance
4. Category suggestion

Output as JSON:
{
  "entities": {
    "persons": ["name1", "name2"],
    "organizations": ["org1", "org2"],
    "locations": ["loc1", "loc2"],
    "dates": ["date1", "date2"]
  },
  "keywords": ["keyword1", "keyword2"],
  "geo": ["München", "Bayern"],
  "category": "politik",
  "sentiment": 0.0
}

Sentiment: -1 (very negative) to 1 (very positive), 0 is neutral
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 1000]);

        if (!$result['success']) {
            return $result;
        }

        // Parse JSON
        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'entities' => $parsed['entities'] ?? [],
                    'keywords' => $parsed['keywords'] ?? [],
                    'geo' => $parsed['geo'] ?? [],
                    'category' => $parsed['category'] ?? 'nachrichten',
                    'sentiment' => $parsed['sentiment'] ?? 0,
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to parse entities response',
        ];
    }

    /**
     * Classify content
     */
    public function classify($content, $categories) {
        $categories_list = implode(', ', array_keys($categories));

        $system_prompt = <<<PROMPT
Classify the provided news article into one of these categories:
{$categories_list}

Consider:
1. Main topic and subject matter
2. Target audience relevance
3. Content type (breaking news, analysis, guide, etc.)

Output as JSON:
{
  "category": "category_key",
  "confidence": 0.95,
  "subcategory": "optional_subcategory",
  "tags": ["tag1", "tag2"]
}
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 300]);

        if (!$result['success']) {
            return $result;
        }

        // Parse JSON
        if (preg_match('/\{[\s\S]*\}/', $result['content'], $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return [
                    'success' => true,
                    'category' => $parsed['category'] ?? 'nachrichten',
                    'confidence' => $parsed['confidence'] ?? 0.5,
                    'subcategory' => $parsed['subcategory'] ?? '',
                    'tags' => $parsed['tags'] ?? [],
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Failed to parse classification response',
        ];
    }

    /**
     * Summarize content
     */
    public function summarize($content, $max_length = 200, $language = 'de') {
        $lang_names = [
            'de' => 'German',
            'ua' => 'Ukrainian',
            'ru' => 'Russian',
            'en' => 'English',
        ];

        $lang_name = $lang_names[$language] ?? 'German';

        $system_prompt = <<<PROMPT
Summarize the provided article in {$lang_name}.

Requirements:
1. Maximum {$max_length} characters
2. Capture the main point and key facts
3. Neutral tone
4. Complete sentences
5. No meta-commentary

Output only the summary text.
PROMPT;

        $result = $this->complete($content, $system_prompt, ['temperature' => 0.3, 'max_tokens' => 300]);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'summary' => substr($result['content'], 0, $max_length),
        ];
    }

    /**
     * Test connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'API key not configured',
            ];
        }

        $result = $this->complete('Say "OK" if you can read this.', '', [
            'max_tokens' => 10,
            'temperature' => 0,
        ]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Connection successful',
                'model' => $this->model,
            ];
        }

        return [
            'success' => false,
            'message' => $result['error'] ?? 'Connection failed',
        ];
    }

    /**
     * Generate full article from facts
     */
    public function generate_article($facts, $sources, $language = 'de') {
        $sources_text = '';
        foreach ($sources as $source) {
            $sources_text .= "- {$source['name']} ({$source['date']}): {$source['title']}\n";
            if (!empty($source['summary'])) {
                $sources_text .= "  Summary: {$source['summary']}\n";
            }
        }

        $lang_configs = [
            'de' => [
                'name' => 'German',
                'what' => 'Was ist passiert?',
                'why' => 'Warum ist das wichtig für Ukrainer in Deutschland?',
                'action' => 'Was kann man tun?',
            ],
            'ua' => [
                'name' => 'Ukrainian',
                'what' => 'Що сталося?',
                'why' => 'Чому це важливо для українців у Німеччині?',
                'action' => 'Що можна зробити?',
            ],
            'ru' => [
                'name' => 'Russian',
                'what' => 'Что произошло?',
                'why' => 'Почему это важно для украинцев в Германии?',
                'action' => 'Что можно сделать?',
            ],
            'en' => [
                'name' => 'English',
                'what' => 'What happened?',
                'why' => 'Why is this important for Ukrainians in Germany?',
                'action' => 'What can you do?',
            ],
        ];

        $config = $lang_configs[$language] ?? $lang_configs['de'];

        $system_prompt = <<<PROMPT
You are a professional news editor for a platform serving Ukrainians in Munich/Bavaria/Germany.

Create a NEW, UNIQUE article in {$config['name']} based on the provided facts from multiple sources.

IMPORTANT RULES:
1. DO NOT copy text directly from sources - create completely original content
2. Only use very short direct quotes (max 10 words) in quotation marks
3. Verify facts appear in at least 2 sources before including
4. Write for Ukrainian audience (A2-B2 {$config['name']} level if not Ukrainian)
5. Be factual, neutral, helpful

STRUCTURE YOUR OUTPUT AS:
<title>[Compelling title, max 60 chars]</title>
<lead>[2-3 sentence summary of the main news]</lead>

<section id="what">
<h2>{$config['what']}</h2>
<p>[Explain what happened, the facts]</p>
</section>

<section id="why">
<h2>{$config['why']}</h2>
<p>[Explain relevance for Ukrainian community]</p>
</section>

<section id="action">
<h2>{$config['action']}</h2>
<p>[Practical advice, links, contacts]</p>
<ul>
<li>[Action item 1]</li>
<li>[Action item 2]</li>
</ul>
</section>

Article should be 300-500 words.
PROMPT;

        $prompt = "Create an article from these sources:\n\n{$sources_text}\n\nKey facts:\n{$facts}";

        return $this->complete($prompt, $system_prompt, ['temperature' => 0.7, 'max_tokens' => 4000]);
    }
}
