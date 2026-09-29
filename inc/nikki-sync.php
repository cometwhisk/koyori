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

if (!function_exists('koyori_nikki_snappy_uncompress')) {
    function koyori_nikki_snappy_uncompress($data) {
        $length = strlen($data);
        $pos = 0;
        $value = 0;
        $shift = 0;
        do {
            if ($pos >= $length || $shift > 28) {
                return new WP_Error('nikki_invalid_stats', '统计数据格式不正确。');
            }
            $byte = ord($data[$pos++]);
            $value |= ($byte & 0x7f) << $shift;
            $shift += 7;
        } while (($byte & 0x80) !== 0);

        $out = '';
        while ($pos < $length) {
            $tag = ord($data[$pos++]);
            $type = $tag & 0x03;
            if ($type === 0) {
                $literal_code = $tag >> 2;
                if ($literal_code < 60) {
                    $literal_length = $literal_code + 1;
                } else {
                    $extra = $literal_code - 59;
                    if ($extra < 1 || $extra > 4 || $pos + $extra > $length) {
                        return new WP_Error('nikki_invalid_stats', '统计数据格式不正确。');
                    }
                    $literal_length = 0;
                    for ($i = 0; $i < $extra; $i++) {
                        $literal_length |= ord($data[$pos++]) << ($i * 8);
                    }
                    $literal_length++;
                }
                if ($pos + $literal_length > $length) {
                    return new WP_Error('nikki_invalid_stats', '统计数据格式不正确。');
                }
                $out .= substr($data, $pos, $literal_length);
                $pos += $literal_length;
                continue;
            }

            if ($type === 1) {
                if ($pos >= $length) {
                    return new WP_Error('nikki_invalid_stats', '统计数据格式不正确。');
                }
                $copy_length = (($tag >> 2) & 0x07) + 4;
                $offset = (($tag & 0xe0) << 3) | ord($data[$pos++]);
            } elseif ($type === 2) {
                if ($pos + 2 > $length) {
                    return new WP_Error('nikki_invalid_stats', '统计数据格式不正确。');
                }
                $copy_length = ($tag >> 2) + 1;
                $offset = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
                $pos += 2;
            } else {
                if ($pos + 4 > $length) {
                    return new WP_Error('nikki_invalid_stats', '统计数据格式不正确。');
                }
                $copy_length = ($tag >> 2) + 1;
                $offset = ord($data[$pos]) | (ord($data[$pos + 1]) << 8) | (ord($data[$pos + 2]) << 16) | (ord($data[$pos + 3]) << 24);
                $pos += 4;
            }
            if ($offset < 1 || $offset > strlen($out)) {
                return new WP_Error('nikki_invalid_stats', '统计数据格式不正确。');
            }
            for ($i = 0; $i < $copy_length; $i++) {
                $out .= $out[strlen($out) - $offset];
            }
        }
        if (strlen($out) !== $value) {
            return new WP_Error('nikki_invalid_stats', '统计数据长度不正确。');
        }
        return $out;
    }
}

if (!function_exists('koyori_nikki_fetch_stats')) {
    function koyori_nikki_fetch_stats($auth) {
        $response = wp_remote_post('https://myl-api.nuanpaper.com/v1/strategy/user/note/book/info', array(
            'timeout' => 20,
            'headers' => array(
                'Accept' => 'application/octet-stream',
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
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('nikki_stats_request_failed', '统计数据请求失败。');
        }
        $decoded = koyori_nikki_snappy_uncompress(wp_remote_retrieve_body($response));
        if (is_wp_error($decoded)) {
            return $decoded;
        }
        $payload = json_decode($decoded, true);
        $gm = is_array($payload['info_from_gm'] ?? null) ? $payload['info_from_gm'] : array();
        $self = is_array($payload['info_from_self'] ?? null) ? $payload['info_from_self'] : array();
        $limited_five = 0;
        $limited_four = 0;
        $standard_five = 0;
        $four_star = 0;
        $wish_total = array('periodic5' => 0, 'periodic4' => 0, 'permanent5' => 0);
        $wish_owned = array('periodic5' => 0, 'periodic4' => 0, 'permanent5' => 0);
        $wish_draws = array('periodic5' => 0, 'periodic4' => 0, 'permanent5' => 0);
        $gacha_by_pool_rarity_result = array();
        foreach (is_array($self['gacha_list'] ?? null) ? $self['gacha_list'] : array() as $draw) {
            $pool_id = (string) ($draw['card_pool_id'] ?? '');
            $rarity = (string) ($draw['rarity'] ?? '');
            $result_id = (string) ($draw['result'] ?? '');
            if ($pool_id === '' || $rarity === '' || $result_id === '') {
                continue;
            }
            $gacha_by_pool_rarity_result[$pool_id][$rarity][$result_id][] = $draw;
        }
        $draw_by_pool_rarity = array();
        foreach ($gacha_by_pool_rarity_result as $pool_id => $rarities) {
            foreach ($rarities as $rarity => $results) {
                $draw_field = (string) $rarity === '5' ? 'times_from_last_five_stars' : 'times_from_last_four_stars';
                foreach ($results as $result_id => $draws) {
                    usort($draws, function ($left, $right) {
                        return (int) ($left['pool_cnt'] ?? 0) <=> (int) ($right['pool_cnt'] ?? 0);
                    });
                    foreach (array_slice($draws, 0, 2) as $index => $draw) {
                        $group = $index === 0 ? 'first' : 'second';
                        $draw_by_pool_rarity[$pool_id][$rarity][$group][$result_id] = (int) ($draw[$draw_field] ?? 0) + 1;
                    }
                }
            }
        }
        $suit_response = wp_remote_post('https://myl-api.nuanpaper.com/v1/strategy/main/suit/list', array(
            'timeout' => 20,
            'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json', 'Origin' => 'https://myl.nuanpaper.com', 'Referer' => 'https://myl.nuanpaper.com/tools/journal'),
            'body' => wp_json_encode(array('client_id' => (int) $auth['client_id'], 'token' => $auth['token'], 'openid' => $auth['openid'])),
        ));
        $suit_list = !is_wp_error($suit_response) ? json_decode(wp_remote_retrieve_body($suit_response), true) : array();
        foreach (is_array($suit_list['data']['list'] ?? null) ? $suit_list['data']['list'] : array() as $suit) {
            $type = (int) ($suit['card_type'] ?? 0);
            $level = (int) ($suit['level'] ?? 0);
            $wish_key = $type === 2 && $level === 5 ? 'periodic5' : ($type === 2 && $level === 4 ? 'periodic4' : ($type === 1 && $level === 5 ? 'permanent5' : ''));
            $cloths = is_string($suit['cloths'] ?? null) ? json_decode($suit['cloths'], true) : ($suit['cloths'] ?? array());
            if ($wish_key !== '') {
                $wish_total[$wish_key] += $level === 5 ? 4 : 2;
            }
            $owned = 0;
            $draws = 0;
            foreach (array('first', 'second') as $group) {
                $draw_map = $draw_by_pool_rarity[(string) ($suit['card_pool_id'] ?? '')][(string) $level][$group] ?? array();
                foreach (is_array($cloths) ? $cloths : array() as $cloth) {
                    $cloth_id = (string) ($cloth['cloth_id'] ?? '');
                    $draw_num = (int) ($draw_map[$cloth_id] ?? 0);
                    if ($draw_num > 0) {
                        $owned++;
                        $draws += $draw_num;
                    }
                }
            }
            if ($wish_key !== '') {
                $wish_owned[$wish_key] += $owned;
                $wish_draws[$wish_key] += $draws;
            }
            if ($type === 2 && $level === 5) {
                $limited_five += $owned;
            } elseif ($type === 2 && $level === 4) {
                $limited_four += $owned;
            } elseif ($type === 1 && $level === 5) {
                $standard_five += $owned;
            } elseif ($type === 1 && $level === 4) {
                $four_star += $owned;
            }
        }
        $dewdrop = 0;
        foreach (is_array($gm['currency_count'] ?? null) ? $gm['currency_count'] : array() as $currency) {
            if ((int) ($currency['item_id'] ?? 0) === 24) {
                $dewdrop = (int) ($currency['count'] ?? 0);
            }
        }
        foreach (array('login_days' => $self, 'total_play_time' => $self, 'cloth_num' => $gm, 'momo_num' => $gm, 'designdrawing_num' => $gm) as $field => $source) {
            if (!isset($source[$field]) || !is_numeric($source[$field])) {
                return new WP_Error('nikki_invalid_stats', '统计数据返回不完整。');
            }
        }
        return array(
            'login_days' => (string) $self['login_days'],
            'play_time' => number_format(((float) $self['total_play_time']) / 3600, 2, '.', '') . 'h',
            'clothes' => (string) $gm['cloth_num'],
            'designs' => (string) $gm['designdrawing_num'],
            'momo' => (string) $gm['momo_num'],
            'dewdrop' => (string) $dewdrop . ' / 3203',
            'pillar' => (string) ($gm['pillar_num'] ?? 0) . ' / 240',
            'limited_five' => (string) $limited_five,
            'limited_four' => (string) $limited_four,
            'standard_five' => (string) $standard_five,
            'four_star' => (string) ($limited_four + $four_star),
            'resonance' => (string) ($gm['draw_num'] ?? 0),
            'suits' => (string) (is_array($self['suit_list'] ?? null) ? count($self['suit_list']) : 0) . ' / 128',
            'wish_resonance' => array(
                'periodic5' => array('owned' => $wish_owned['periodic5'], 'total' => $wish_total['periodic5'], 'average' => $wish_owned['periodic5'] > 0 ? number_format($wish_draws['periodic5'] / $wish_owned['periodic5'], 1, '.', '') : '0'),
                'periodic4' => array('owned' => $wish_owned['periodic4'], 'total' => $wish_total['periodic4'], 'average' => $wish_owned['periodic4'] > 0 ? number_format($wish_draws['periodic4'] / $wish_owned['periodic4'], 1, '.', '') : '0'),
                'permanent5' => array('owned' => $wish_owned['permanent5'], 'total' => $wish_total['permanent5'], 'average' => $wish_owned['permanent5'] > 0 ? number_format($wish_draws['permanent5'] / $wish_owned['permanent5'], 1, '.', '') : '0'),
            ),
            'crown' => (string) ($gm['permanent_tower'] ?? 0) . ' / 15 层',
            'crown_peak' => (string) ($gm['periodic_tower'] ?? 0) . ' / 8 层',
            'stats_synced_at' => current_time('mysql'),
        );
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
        $stats = koyori_nikki_fetch_stats($auth);
        if (!is_wp_error($stats)) {
            $private['stats_data'] = $stats;
        }
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
