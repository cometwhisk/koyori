<?php
/**
 * Koyori customizations for the Smallmaple site.
 *
 * @package Koyori
 */

add_action('wp_head', static function (): void {
    echo '<style id="koyori-nav-alignment">.nav-search-wrapper nav{height:100%;align-items:center}.nav-search-wrapper nav ul{height:100%;align-items:center}.nav-search-wrapper nav ul li{padding:0;display:flex;align-items:center}.nav-search-wrapper nav ul li a{height:auto}</style>', PHP_EOL;
}, 99);

add_action('init', static function (): void {
    add_rewrite_rule('^login/?$', 'index.php?koyori_login=1', 'top');
    if (get_option('koyori_login_rewrite_version') !== '2') {
        flush_rewrite_rules(false);
        update_option('koyori_login_rewrite_version', '2', false);
    }
});

add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'koyori_login';
    return $vars;
});

add_filter('redirect_canonical', static function ($redirect_url, $requested_url) {
    if (get_query_var('koyori_login')) {
        return false;
    }
    return $redirect_url;
}, 10, 2);

add_action('template_redirect', static function (): void {
    if (!get_query_var('koyori_login')) {
        return;
    }

    $action = sanitize_key((string)($_REQUEST['action'] ?? 'login'));
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || $action !== 'login') {
        $user_login = '';
        $error = '';
        require ABSPATH . 'wp-login.php';
        exit;
    }

    include get_template_directory() . '/user/page-login.php';
    exit;
}, 0);

add_filter('site_url', static function ($url, $path, $scheme, $blog_id) {
    if (is_string($path) && preg_match('#^wp-login\\.php(?:\\?|$)#', $path)) {
        $parts = explode('?', $path, 2);
        $login_url = home_url('/login');
        return isset($parts[1]) && $parts[1] !== '' ? $login_url . '?' . $parts[1] : $login_url;
    }
    return $url;
}, 10, 4);

add_action('login_init', static function (): void {
    if (is_user_logged_in() || strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (untrailingslashit((string)$path) !== '/wp-login.php') {
        return;
    }
    $target = home_url('/login');
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') {
        $target .= '?' . $query;
    }
    wp_safe_redirect($target, 302);
    exit;
});

add_action('login_footer', static function (): void {
    echo '<style id="koyori-login-fixes">body.login #loginform .cf-turnstile{width:300px!important;max-width:none!important;transform:scale(.9)!important;transform-origin:left top!important}body.login #loginform iframe{max-width:none!important}body.login #loginform #rememberme{appearance:auto!important;-webkit-appearance:checkbox!important;width:16px!important;height:16px!important;margin:0 6px 0 0!important;accent-color:#666;cursor:pointer;vertical-align:middle}body.login #loginform .forgetmenot{display:flex!important;align-items:center!important;float:left!important;margin:6px 0 0!important}body.login #nav{clear:both!important;width:auto!important;margin:14px 0 24px!important;padding:0!important;text-align:center!important;background:transparent!important;background-image:none!important;background-color:transparent!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;box-shadow:none!important}body.login #nav a{display:inline-block!important;padding:6px 10px!important;line-height:18px!important;background:rgba(255,255,255,.7)!important;border-radius:8px!important}</style>', PHP_EOL;
});

add_action('wp_footer', static function (): void {
    echo <<<'HTML'
<script id="koyori-comment-turnstile-theme">
(function () {
    'use strict';
    if (window.__koyoriTurnstileThemeBound) return;
    window.__koyoriTurnstileThemeBound = true;

    function rerenderCommentTurnstile() {
        var container = document.querySelector('.comment-form .cfturnstile');
        var response = document.querySelector('.comment-form .cf-turnstile-response');
        if (!container || !window.turnstile || !container.dataset.key) return;

        container.innerHTML = '';
        if (response) response.value = '';
        window.setTimeout(function () {
            if (!document.body.contains(container)) return;
            window.turnstile.render(container, {
                sitekey: container.dataset.key,
                theme: document.body.classList.contains('dark') ? 'dark' : 'light',
                callback: function (token) {
                    if (response) response.value = token;
                }
            });
        }, 50);
    }

    document.addEventListener('darkmode', function () {
        rerenderCommentTurnstile();
    });
}());
</script>
HTML;
}, 99);

add_action('wp_enqueue_scripts', static function (): void {
    $script = <<<'JS'
(function () {
    'use strict';
    function markLoginLinksNoPjax() {
        document.querySelectorAll('a[href]').forEach(function (link) {
            try {
                var url = new URL(link.href, document.baseURI);
                if (url.origin === window.location.origin && url.pathname === '/wp-login.php') {
                    link.setAttribute('data-no-pjax', '');
                }
            } catch (error) {
                // Ignore malformed URLs; the browser will handle them normally.
            }
        });
    }
    markLoginLinksNoPjax();
    document.addEventListener('pjax:complete', markLoginLinksNoPjax);

    document.addEventListener('click', function (event) {
        var tiledBackground = event.target.closest('#diy1-bg, #diy2-bg, #diy3-bg, #diy4-bg');
        var regularBackground = event.target.closest('#white-bg, #dark-bg');
        if (tiledBackground) {
            document.body.style.backgroundRepeat = 'repeat';
            document.body.style.backgroundSize = 'auto';
        } else if (regularBackground) {
            document.body.style.backgroundRepeat = 'no-repeat';
            document.body.style.backgroundSize = '';
        }
    }, true);

}());
JS;
    wp_add_inline_script('app', $script, 'before');
}, 99);
