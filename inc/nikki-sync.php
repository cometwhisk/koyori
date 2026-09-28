<?php
/**
 * Nikki Whim-Log authenticated profile sync.
 */

if (!function_exists('koyori_nikki_normalize_fields')) {
    function koyori_nikki_normalize_fields($client_id, $token, $openid) {
        $client_id = trim((string) $client_id);
        $token = trim((string) $token);
        $openid = trim((string) $openid);
        if ($client_id === '' || !preg_match('/^[0-9]+$/', $client_id) || strlen($client_id) > 20) {
            return new WP_Error('nikki_invalid_client_id', 'client_id 格式不正确。');
        }
        if ($token === '' || strlen($token) > 12000 || $openid === '' || strlen($openid) > 12000) {
            return new WP_Error('nikki_missing_auth', '请填写完整的 Token 和 OpenID。');
        }
        return array('client_id' => $client_id, 'token' => $token, 'openid' => $openid);
    }
}

if (!function_exists('koyori_nikki_sync_profile')) {
    function koyori_nikki_sync_profile($client_id, $token, $openid) {
        $auth = koyori_nikki_normalize_fields($client_id, $token, $openid);
        if (is_wp_error($auth)) {
            return $auth;
        }

        $response = wp_remote_post('https://myl-api.nuanpaper.com/v1/strategy/user/info/get', array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Origin' => 'https://myl.nuanpaper.com',
                'Referer' => 'https://myl.nuanpaper.com/tools/journal',
            ),
            'body' => wp_json_encode(array(
                'client_id' => (int) $auth['client_id'],
                'token' => $auth['token'],
                'openid' => $auth['openid'],
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
        unset($private['session_bundle']);
        $private['client_id'] = $auth['client_id'];
        $private['token'] = $auth['token'];
        $private['openid'] = $auth['openid'];
        $private['profile_data'] = $profile;
        update_option('koyori_nikki_private', $private, false);
        $saved = get_option('koyori_nikki_private', array());
        if (!is_array($saved) || $saved['client_id'] !== $auth['client_id'] || $saved['token'] !== $auth['token'] || $saved['openid'] !== $auth['openid'] || ($saved['profile_data']['uid'] ?? '') !== $profile['uid']) {
            return new WP_Error('nikki_save_failed', '资料写入失败，现有资料未更新。');
        }
        set_transient('koyori_nikki_auto_sync_lock', 1, HOUR_IN_SECONDS);
        return $profile;
    }
}

if (!function_exists('koyori_nikki_maybe_auto_sync_profile')) {
    function koyori_nikki_maybe_auto_sync_profile() {
        if (false !== get_transient('koyori_nikki_auto_sync_lock')) {
            return null;
        }
        $private = get_option('koyori_nikki_private', array());
        if (!is_array($private) || empty($private['client_id']) || empty($private['token']) || empty($private['openid'])) {
            return null;
        }
        $result = koyori_nikki_sync_profile($private['client_id'], $private['token'], $private['openid']);
        if (is_wp_error($result)) {
            set_transient('koyori_nikki_auto_sync_lock', 1, HOUR_IN_SECONDS);
        }
        return $result;
    }
}

if (!function_exists('koyori_nikki_sync_ajax')) {
    function koyori_nikki_sync_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '没有权限执行此操作。'), 403);
        }
        check_ajax_referer('koyori_nikki_sync', 'nonce');
        $profile = koyori_nikki_sync_profile(
            wp_unslash($_POST['client_id'] ?? ''),
            wp_unslash($_POST['token'] ?? ''),
            wp_unslash($_POST['openid'] ?? '')
        );
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
