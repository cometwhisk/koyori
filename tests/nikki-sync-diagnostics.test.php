<?php
/** Offline diagnostics only: no WordPress bootstrap, credentials, network, or writes. */
if (defined('ABSPATH') || function_exists('wp_send_json_error')) {
    throw new RuntimeException('Run this test standalone, not inside WordPress.');
}

class WP_Error {
    public function __construct(private $code, private $message) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action(...$args) {}
function wp_json_encode($value) { return json_encode($value, JSON_THROW_ON_ERROR); }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function esc_url_raw($value) { return $value; }
function current_time($type) { return '2026-01-01 00:00:00'; }
function wp_remote_post($url, $options) {
    if (!str_starts_with($url, 'https://myl-api.nuanpaper.com/v1/strategy/') || $options['timeout'] !== 20) {
        throw new RuntimeException('Unexpected request configuration.');
    }
    if (!$GLOBALS['responses']) throw new RuntimeException('Unexpected mocked request.');
    return array_shift($GLOBALS['responses']);
}
function get_option(...$args) { throw new RuntimeException('Private state access is forbidden.'); }
function update_option(...$args) { throw new RuntimeException('Writes are forbidden.'); }
function set_transient(...$args) { throw new RuntimeException('Writes are forbidden.'); }

require __DIR__ . '/../inc/nikki-sync.php';

function expect_error($result, $code) {
    if (!is_wp_error($result) || $result->get_error_code() !== $code) {
        throw new RuntimeException('Expected error code: ' . $code);
    }
    foreach (array('MOCK_TOKEN', 'MOCK_OPENID', 'MOCK_UPSTREAM_ERROR', 'MOCK_TRANSPORT_DETAIL') as $private_value) {
        if (strpos($result->get_error_message(), $private_value) !== false) {
            throw new RuntimeException('Diagnostic message exposed private request/response content.');
        }
    }
    if ($GLOBALS['responses']) throw new RuntimeException('Unused mocked responses.');
}
function reply($status, $body) { return array('status' => $status, 'body' => $body); }
function literal_snappy($body) {
    $size = strlen($body);
    $remaining = $size;
    $header = '';
    do {
        $byte = $remaining & 0x7f;
        $remaining >>= 7;
        $header .= chr($byte | ($remaining ? 0x80 : 0));
    } while ($remaining);
    if ($size <= 60) return $header . chr(($size - 1) << 2) . $body;
    $length = $size - 1;
    $extra = '';
    do {
        $extra .= chr($length & 0xff);
        $length >>= 8;
    } while ($length);
    return $header . chr((59 + strlen($extra)) << 2) . $extra . $body;
}

$tests = array(
    'transport error has only a generic request code' => function () {
        $GLOBALS['responses'] = array(new WP_Error('http_request_failed', 'MOCK_TRANSPORT_DETAIL'));
        expect_error(koyori_nikki_sync_profile('123', 'MOCK_TOKEN', 'MOCK_OPENID'), 'nikki_request_failed');
    },
    'upstream HTTP 503 is distinguished from invalid auth' => function () {
        $GLOBALS['responses'] = array(reply(503, '<html>MOCK_UPSTREAM_ERROR</html>'));
        expect_error(koyori_nikki_sync_profile('123', 'MOCK_TOKEN', 'MOCK_OPENID'), 'nikki_upstream_http_error');
    },
    'upstream invalid JSON is distinguished from invalid auth' => function () {
        $GLOBALS['responses'] = array(reply(200, '{malformed'));
        expect_error(koyori_nikki_sync_profile('123', 'MOCK_TOKEN', 'MOCK_OPENID'), 'nikki_invalid_profile_response');
    },
    'upstream scalar JSON is rejected as an invalid response' => function () {
        $GLOBALS['responses'] = array(reply(200, '123'));
        expect_error(koyori_nikki_sync_profile('123', 'MOCK_TOKEN', 'MOCK_OPENID'), 'nikki_invalid_profile_response');
    },
    'upstream API rejection is distinguished from HTTP and JSON errors' => function () {
        $GLOBALS['responses'] = array(reply(200, '{"code":401,"data":{}}'));
        expect_error(koyori_nikki_sync_profile('123', 'MOCK_TOKEN', 'MOCK_OPENID'), 'nikki_upstream_rejected');
    },
    'incomplete profile data is rejected with a safe diagnostic' => function () {
        $GLOBALS['responses'] = array(reply(200, '{"code":0,"data":{"role":{}}}'));
        expect_error(koyori_nikki_sync_profile('123', 'MOCK_TOKEN', 'MOCK_OPENID'), 'nikki_invalid_profile');
    },
    'stats transport failure is coded without writing private state' => function () {
        $GLOBALS['responses'] = array(new WP_Error('http_request_failed', 'MOCK_TRANSPORT_DETAIL'));
        expect_error(koyori_nikki_fetch_stats(array('client_id' => '123', 'token' => 'MOCK_TOKEN', 'openid' => 'MOCK_OPENID')), 'nikki_stats_request_failed');
    },
    'invalid compressed stats are rejected before any write' => function () {
        $GLOBALS['responses'] = array(reply(200, ''));
        expect_error(koyori_nikki_fetch_stats(array('client_id' => '123', 'token' => 'MOCK_TOKEN', 'openid' => 'MOCK_OPENID')), 'nikki_invalid_stats');
    },
    'empty draw history does not emit warnings or write private state' => function () {
        $body = json_encode(array('info_from_gm' => array(), 'info_from_self' => array('gacha_list' => array(), 'suit_list' => array())));
        $compressed = literal_snappy($body);
        if (koyori_nikki_snappy_uncompress($compressed) !== $body) throw new RuntimeException('Invalid local fixture.');
        $GLOBALS['responses'] = array(reply(200, $compressed), reply(503, 'MOCK_UPSTREAM_ERROR'));
        $warnings = array();
        set_error_handler(function ($severity, $message, $file, $line) use (&$warnings) {
            $warnings[] = array('message' => $message, 'line' => $line);
            return true;
        });
        try {
            expect_error(koyori_nikki_fetch_stats(array('client_id' => '123', 'token' => 'MOCK_TOKEN', 'openid' => 'MOCK_OPENID')), 'nikki_suit_request_failed');
        } finally {
            restore_error_handler();
        }
        if ($warnings) {
            throw new RuntimeException('Empty draw history emitted PHP warnings.');
        }
    },
);
$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo 'PASS: ' . $name . "\n";
    } catch (Throwable $error) {
        $failed++;
        echo 'FAIL: ' . $name . ': ' . $error->getMessage() . "\n";
    }
}
printf("%d offline cases, %d failures. No real network or private state operations.\n", count($tests), $failed);
exit($failed ? 1 : 0);
