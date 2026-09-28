<?php
/**
 * Nikki Whim-Log authenticated profile sync.
 */

if (!function_exists('koyori_nikki_parse_bundle')) {
    function koyori_nikki_parse_bundle($bundle) {
        $bundle = trim((string) $bundle);
        if (strlen($bundle) < 16 || strlen($bundle) > 20000 || strpos($bundle, 'NIKKI1.') !== 0) {
            return new WP_Error('nikki_invalid_bundle', '登录态文本格式不正确。');
        }
        $encoded = strtr(substr($bundle, 7), '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);
        $data = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($data)) {
            return new WP_Error('nikki_invalid_bundle', '登录态文本格式不正确。');
        }
        foreach (array('cookie', 'token', 'openid') as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '' || strlen($data[$key]) > 12000) {
                return new WP_Error('nikki_invalid_bundle', '登录态文本缺少必要字段。');
            }
        }
        return array(
            'cookie' => trim($data['cookie']),
            'token' => trim($data['token']),
            'openid' => trim($data['openid']),
        );
    }
}

if (!function_exists('koyori_nikki_sync_profile')) {
    function koyori_nikki_sync_profile($bundle) {
        $auth = koyori_nikki_parse_bundle($bundle);
        if (is_wp_error($auth)) {
            return $auth;
        }
        $cookie = $auth['cookie'];
        $token = $auth['token'];
        $openid = $auth['openid'];
        if ($cookie === '' || $token === '' || $openid === '') {
            return new WP_Error('nikki_missing_auth', '请完整填写 Cookie、Token 和 OpenID。');
        }

        $response = wp_remote_post('https://myl-api.nuanpaper.com/v1/strategy/user/info/get', array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Origin' => 'https://myl.nuanpaper.com',
                'Referer' => 'https://myl.nuanpaper.com/tools/journal',
                'Cookie' => $cookie,
            ),
            'body' => wp_json_encode(array(
                'client_id' => 1106,
                'token' => $token,
                'openid' => $openid,
            )),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('nikki_request_failed', '官方接口请求失败，请稍后重试。');
        }
        $status = wp_remote_retrieve_response_code($response);
        $payload = json_decode(wp_remote_retrieve_body($response), true);
        $role = is_array($payload['data']['role'] ?? null) ? $payload['data']['role'] : array();
        if ($status !== 200 || (int) ($payload['code'] ?? -1) !== 0 || empty($role)) {
            return new WP_Error('nikki_auth_failed', '登录态无效或已过期，请更新登录态字段。');
        }
        foreach (array('uid', 'nickname', 'avatar', 'level') as $key) {
            if (!isset($role[$key]) || (!is_string($role[$key]) && !is_int($role[$key])) || trim((string) $role[$key]) === '') {
                return new WP_Error('nikki_invalid_profile', '官方资料返回不完整，未更新现有资料。');
            }
        }

        $profile = array(
            'uid' => sanitize_text_field((string) $role['uid']),
            'nickname' => sanitize_text_field((string) $role['nickname']),
            'avatar' => esc_url_raw((string) $role['avatar']),
            'level' => sanitize_text_field((string) $role['level']),
            'updated_at' => sanitize_text_field((string) ($role['updated_at'] ?? '')),
            'synced_at' => current_time('mysql'),
        );
        $private = get_option('koyori_nikki_private', array());
        if (!is_array($private)) {
            $private = array();
        }
        $private['session_bundle'] = trim((string) $bundle);
        $private['profile_data'] = $profile;
        update_option('koyori_nikki_private', $private, false);
        $saved = get_option('koyori_nikki_private', array());
        if (!is_array($saved) || $saved['session_bundle'] !== $private['session_bundle'] || ($saved['profile_data']['uid'] ?? '') !== $profile['uid']) {
            return new WP_Error('nikki_save_failed', '资料写入失败，现有资料未更新。');
        }
        return $profile;
    }
}

if (!function_exists('koyori_nikki_sync_ajax')) {
    function koyori_nikki_sync_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '没有权限执行此操作。'), 403);
        }
        check_ajax_referer('koyori_nikki_sync', 'nonce');
        $profile = koyori_nikki_sync_profile(wp_unslash($_POST['bundle'] ?? ''));
        if (is_wp_error($profile)) {
            wp_send_json_error(array('message' => $profile->get_error_message()), 400);
        }
        wp_send_json_success(array(
            'message' => '资料同步成功。',
            'profile' => array(
                'nickname' => $profile['nickname'],
                'uid' => $profile['uid'],
                'level' => $profile['level'],
                'synced_at' => $profile['synced_at'],
            ),
        ));
    }
}
add_action('wp_ajax_koyori_nikki_sync_profile', 'koyori_nikki_sync_ajax');
