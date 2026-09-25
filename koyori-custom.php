<?php
/**
 * Koyori customizations for the Smallmaple site.
 *
 * @package Koyori
 */

add_action('wp_head', static function (): void {
    echo '<style id="koyori-nav-alignment">.nav-search-wrapper nav{height:100%;align-items:center}.nav-search-wrapper nav ul{height:100%;align-items:center}.nav-search-wrapper nav ul li{padding:0;display:flex;align-items:center}.nav-search-wrapper nav ul li a{height:auto}</style>', PHP_EOL;
}, 99);

add_action('login_footer', static function (): void {
    echo '<style id="koyori-login-fixes">body.login #loginform #rememberme{appearance:auto!important;-webkit-appearance:checkbox!important;width:16px!important;height:16px!important;margin:0 6px 0 0!important;accent-color:#666;cursor:pointer;vertical-align:middle}body.login #loginform .forgetmenot{display:flex!important;align-items:center!important;float:left!important;margin:6px 0 0!important}body.login #nav{clear:both!important;width:auto!important;margin:14px 0 24px!important;padding:0!important;text-align:center!important;background:transparent!important;background-image:none!important;background-color:transparent!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;box-shadow:none!important}body.login #nav a{display:inline-block!important;padding:6px 10px!important;line-height:18px!important;background:rgba(255,255,255,.7)!important;border-radius:8px!important}</style>', PHP_EOL;
});

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
