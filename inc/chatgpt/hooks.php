<?php

namespace IROChatGPT {

    use Exception;
    use WP_Post;

    define("DEFAULT_INIT_PROMPT", "请以作者的身份，以激发好奇吸引阅读为目的，结合文章核心观点来提取的文章中最吸引人的内容，为以下文章编写一个用词精炼简短、90字以内、与文章语言一致的引言。");
    define("DEFAULT_MODEL", "gpt-4o-mini");

    function generate_post_summary(WP_Post $post)
    {
        $exclude_ids = iro_opt('chatgpt_exclude_ids', '');
        if (in_array($post->ID, explode(",", $exclude_ids), false)) {
            return;
        }

        try {
            $excerpt = summon_article_excerpt($post);
            return $excerpt;
        } catch (\Throwable $th) {
            error_log('ChatGPT-excerpt-err:' . $th);
            return false;
        }
    }


    function chatgpt_get_legacy_config()
    {
        return [
            'name' => (string) iro_opt('chatgpt_model', DEFAULT_MODEL),
            'endpoint' => (string) iro_opt('chatgpt_endpoint', ''),
            'model' => (string) iro_opt('chatgpt_model', DEFAULT_MODEL),
            'access_token' => (string) iro_opt('chatgpt_access_token', ''),
        ];
    }

    function chatgpt_migrate_profiles()
    {
        $options = get_option('iro_options', []);
        if (!is_array($options) || !empty($options['chatgpt_profiles'])) {
            return;
        }

        $endpoint = trim((string)($options['chatgpt_endpoint'] ?? ''));
        $token = trim((string)($options['chatgpt_access_token'] ?? ''));
        $model = trim((string)($options['chatgpt_model'] ?? DEFAULT_MODEL));
        if ($endpoint === '' && $token === '') {
            return;
        }

        $options['chatgpt_profiles'] = [[
            'name' => $model !== '' ? $model : '当前配置',
            'endpoint' => $endpoint,
            'model' => $model,
            'access_token' => $token,
        ]];
        $options['chatgpt_active_profile'] = 0;
        update_option('iro_options', $options);
    }

    function chatgpt_get_active_config()
    {
        $profiles = iro_opt('chatgpt_profiles', []);
        if (is_array($profiles) && !empty($profiles)) {
            $index = max(0, (int) iro_opt('chatgpt_active_profile', 0));
            $profile = $profiles[$index] ?? reset($profiles);
            if (is_array($profile)) {
                return $profile;
            }
        }

        return chatgpt_get_legacy_config();
    }

    chatgpt_migrate_profiles();

    function chatgpt_completion_endpoint($base_url)
    {
        $base_url = trim((string) $base_url);
        if ($base_url === '') {
            return '';
        }

        return rtrim($base_url, '/') . '/chat/completions';
    }

    function chatgpt_provider_error_message($status, $decoded = [])
    {
        if ((int) $status === 401 || (int) $status === 403) {
            return '认证失败，请检查 API Key。';
        }
        if ((int) $status === 404) {
            return '接口或模型不存在，请检查基础地址和模型 ID。';
        }
        if ((int) $status === 429) {
            return '请求过于频繁，已触发服务商速率限制。';
        }
        if ((int) $status >= 500) {
            return '服务商暂时不可用（HTTP ' . (int) $status . '）。';
        }
        if (is_array($decoded) && !empty($decoded['error'])) {
            return '服务商返回错误，请检查模型和请求参数。';
        }
        return '接口返回异常（HTTP ' . (int) $status . '）。';
    }

    function test_chatgpt_connection()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '无权执行此操作。'], 403);
        }

        check_ajax_referer('koyori_test_chatgpt_connection', 'nonce');

        $endpoint = chatgpt_completion_endpoint(wp_unslash($_POST['endpoint'] ?? ''));
        $token = trim((string) wp_unslash($_POST['token'] ?? ''));
        $model = trim((string) wp_unslash($_POST['model'] ?? ''));
        $reasoning_effort = trim((string) wp_unslash($_POST['reasoning_effort'] ?? ''));

        if (!$endpoint || !$token || !$model || !wp_http_validate_url($endpoint)) {
            wp_send_json_error(['message' => '接口地址、API Key 或模型无效。'], 400);
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode(array_filter([
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Reply with OK.'],
                ],
                'max_tokens' => 64,
                'reasoning_effort' => in_array($reasoning_effort, ['low', 'medium', 'high'], true) ? $reasoning_effort : null,
            ], static function ($value) {
                return $value !== null;
            }), JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => '网络请求失败，请稍后重试。'], 502);
        }

        $status = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if ($status < 200 || $status >= 300 || !is_string($content) || trim($content) === '') {
            wp_send_json_error([
                'message' => chatgpt_provider_error_message($status, $decoded),
                'status' => (int) $status,
            ], $status >= 400 ? $status : 502);
        }

        wp_send_json_success(['message' => '连接成功（HTTP ' . (int) $status . '）。', 'status' => (int) $status]);
    }

    add_action('wp_ajax_koyori_test_chatgpt_connection', __NAMESPACE__ . '\\test_chatgpt_connection');

    function switch_chatgpt_profile()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '无权执行此操作。'], 403);
        }

        check_ajax_referer('koyori_switch_chatgpt_profile', 'nonce');
        $index = filter_var(wp_unslash($_POST['profile'] ?? ''), FILTER_VALIDATE_INT);
        $options = get_option('iro_options', []);
        $profiles = is_array($options) ? ($options['chatgpt_profiles'] ?? []) : [];
        if ($index === false || !is_array($profiles) || !array_key_exists($index, $profiles) || !is_array($profiles[$index])) {
            wp_send_json_error(['message' => '目标配置不存在。'], 400);
        }

        $options['chatgpt_active_profile'] = (int) $index;
        if (!update_option('iro_options', $options)) {
            $saved_options = get_option('iro_options', []);
            if (!is_array($saved_options) || (int)($saved_options['chatgpt_active_profile'] ?? -1) !== (int) $index) {
                wp_send_json_error(['message' => '当前配置保存失败，请重试。'], 500);
            }
        }
        $saved_options = get_option('iro_options', []);
        if (!is_array($saved_options) || (int)($saved_options['chatgpt_active_profile'] ?? -1) !== (int) $index) {
            wp_send_json_error(['message' => '当前配置保存失败，请重试。'], 500);
        }
        wp_send_json_success(['active_profile' => (int) $index]);
    }

    add_action('wp_ajax_koyori_switch_chatgpt_profile', __NAMESPACE__ . '\\switch_chatgpt_profile');

    function fetch_chatgpt_models()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '无权执行此操作。'], 403);
        }

        check_ajax_referer('koyori_fetch_chatgpt_models', 'nonce');
        $base_url = rtrim(trim((string) wp_unslash($_POST['endpoint'] ?? '')), '/');
        $token = trim((string) wp_unslash($_POST['token'] ?? ''));
        if (!$base_url || !$token || !wp_http_validate_url($base_url)) {
            wp_send_json_error(['message' => '请先填写有效的基础地址和 API Key。'], 400);
        }

        $response = wp_remote_get($base_url . '/models', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => '获取模型列表失败，请稍后重试。'], 502);
        }

        $status = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            wp_send_json_error(['message' => chatgpt_provider_error_message($status, $decoded), 'status' => (int) $status], $status >= 400 ? $status : 502);
        }

        $models = [];
        foreach ((array) ($decoded['data'] ?? []) as $model) {
            $id = is_array($model) ? trim((string) ($model['id'] ?? '')) : '';
            if ($id !== '') {
                $models[] = $id;
            }
        }
        sort($models, SORT_NATURAL | SORT_FLAG_CASE);
        if (!$models) {
            wp_send_json_error(['message' => '接口没有返回可用模型列表。'], 502);
        }
        wp_send_json_success(['models' => array_values(array_unique($models)), 'count' => count($models)]);
    }

    add_action('wp_ajax_koyori_fetch_chatgpt_models', __NAMESPACE__ . '\\fetch_chatgpt_models');

    function apply_chatgpt_hook()
    {
        if (iro_opt('chatgpt_auto_article_summarize')) {
            $exclude_ids = iro_opt('chatgpt_exclude_ids', '');
            add_action('save_post_post', function (int $post_id, WP_Post $post, bool $update) use ($exclude_ids) {
                // Prevent duplicate execution during autosaves
                if (wp_is_post_autosave($post_id)) {
                    return;
                }
                
                // Check if this execution is already in progress using a transient
                $transient_key = 'chatgpt_excerpt_generating_' . $post_id;
                if (get_transient($transient_key)) {
                    // Already processing this post, skip
                    return;
                }
                
                if (!has_excerpt($post_id) && !in_array($post_id, explode(",", $exclude_ids), false)) {
                    // Set transient to prevent duplicate calls (expires in 60 seconds)
                    set_transient($transient_key, true, 60);
                    
                    try {
                        $excerpt = generate_post_summary($post);
                        update_post_meta($post_id, "ai_summon_excerpt", $excerpt);
                    } catch (\Throwable $th) {
                        error_log('ChatGPT-excerpt-err:' . $th);
                    } finally {
                        // Clean up transient after execution
                        delete_transient($transient_key);
                    }
                }
            }, 10, 3);
        }

        add_filter('the_excerpt', function (string $post_excerpt) {
            global $post;
            if (has_excerpt($post)) {
                return $post_excerpt;
            } else {
                $ai_excerpt =  get_post_meta($post->ID, "ai_summon_excerpt", true);
                return $ai_excerpt ? $ai_excerpt : $post_excerpt;
            }
        });
        
    }

    function summon_article_excerpt(WP_Post $post)
    {
        $config = chatgpt_get_active_config();
        $chatgpt_endpoint = chatgpt_completion_endpoint($config['endpoint'] ?? '');
        $chatGPT_access_token = (string)($config['access_token'] ?? '');
        $chatGPT_prompt_init = iro_opt('chatgpt_init_prompt', DEFAULT_INIT_PROMPT);
        $chatGPT_model = (string)($config['model'] ?? DEFAULT_MODEL);
        $reasoning_effort = trim((string)($config['reasoning_effort'] ?? ''));

        if (empty($chatgpt_endpoint) || empty($chatGPT_access_token) || empty($chatGPT_prompt_init) || empty($chatGPT_model)) {
            throw new Exception("Missing required ChatGPT configuration.");
        }


        // 构造请求 payload
        $payload = [
            "model"    => $chatGPT_model,
            "messages" => [
                [
                    "role"    => "system",
                    "content" => $chatGPT_prompt_init,
                ],
                [
                    "role"    => "user",
                    "content" => "Title：" . $post->post_title . "\n\n" .
                        "Content：" . mb_substr(
                            preg_replace(
                                "/(\\s)\\s{2,}/",
                                "$1",
                                wp_strip_all_tags(apply_filters('the_content', $post->post_content))
                            ),
                            0,
                            iro_opt("chatgpt_max_tokens", 7000)
                        ),
                ],
            ],
        ];
        if (in_array($reasoning_effort, ['low', 'medium', 'high'], true)) {
            $payload['reasoning_effort'] = $reasoning_effort;
        }

        // === 替换开始：使用 cURL 发出请求 ===
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $chatgpt_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, iro_opt('chatgpt_api_request_timeout', 30));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $chatGPT_access_token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $chat = curl_exec($ch);
        if ($chat === false) {
            throw new Exception("cURL error: " . curl_error($ch));
        }
        curl_close($ch);
        // === 替换结束 ===

        $decoded_chat = json_decode($chat, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        if (!is_array($decoded_chat)) {
            throw new Exception("ChatGPT returned an invalid response.");
        }

        if (!empty($decoded_chat['error'])) {
            throw new Exception("ChatGPT returned an API error.");
        }

        $content = $decoded_chat['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new Exception("ChatGPT response did not contain summary content.");
        }

        return $content;
    }
}
