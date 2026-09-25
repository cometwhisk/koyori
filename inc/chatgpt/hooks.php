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


    function chatgpt_completion_endpoint($base_url)
    {
        $base_url = trim((string) $base_url);
        if ($base_url === '') {
            return '';
        }

        return rtrim($base_url, '/') . '/chat/completions';
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

        if (!$endpoint || !$token || !$model || !wp_http_validate_url($endpoint)) {
            wp_send_json_error(['message' => '接口地址、API Key 或模型无效。'], 400);
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Reply with OK.'],
                ],
                'max_tokens' => 64,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => '请求失败，请检查接口地址和服务器网络。'], 502);
        }

        $status = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || empty($decoded['choices'][0]['message']['content'])) {
            wp_send_json_error(['message' => '接口返回异常，请检查 API Key 和模型名称。'], 502);
        }

        wp_send_json_success(['message' => '连接成功。']);
    }

    add_action('wp_ajax_koyori_test_chatgpt_connection', __NAMESPACE__ . '\\test_chatgpt_connection');

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
        $chatgpt_endpoint = chatgpt_completion_endpoint(iro_opt('chatgpt_endpoint'));
        $chatGPT_access_token = iro_opt('chatgpt_access_token');
        $chatGPT_prompt_init = iro_opt('chatgpt_init_prompt', DEFAULT_INIT_PROMPT);
        $chatGPT_model = iro_opt('chatgpt_model', DEFAULT_MODEL);

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

        // 输出 API 原始响应调试信息
        error_log("GPT error: " . $chat);

        $decoded_chat = json_decode($chat);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        if (is_null($decoded_chat) || isset($decoded_chat->error)) {
            throw new Exception("ChatGPT error: " . json_encode($decoded_chat));
        }

        return $decoded_chat->choices[0]->message->content;
    }
}
