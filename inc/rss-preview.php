<?php
/**
 * Browser-friendly RSS preview for Koyori.
 * The underlying RSS XML remains standard and reader-compatible.
 */

function koyori_register_rss_preview_route(): void
{
    add_rewrite_rule(
        '^rss/feed\.xsl/?$',
        'index.php?koyori_rss_xsl=1',
        'top'
    );
}
add_action('init', 'koyori_register_rss_preview_route');

function koyori_register_rss_preview_query_var(array $vars): array
{
    $vars[] = 'koyori_rss_xsl';
    return $vars;
}
add_filter('query_vars', 'koyori_register_rss_preview_query_var');

function koyori_keep_rss_preview_url($redirect_url, $requested_url)
{
    if ((string) get_query_var('koyori_rss_xsl') === '1') {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'koyori_keep_rss_preview_url', 10, 2);

function koyori_add_rss_stylesheet_instruction(string $feed): string
{
    header('Content-Type: application/xml; charset=UTF-8');
    $href = esc_url(get_home_url(null, '/rss/feed.xsl'));
    $instruction = "\n<?xml-stylesheet type=\"text/xsl\" href=\"{$href}\"?>\n";

    if (strpos($feed, 'xml-stylesheet') !== false) {
        return $feed;
    }

    return preg_replace(
        '/^(<\?xml[^>]*\?>\s*)/s',
        '$1' . $instruction,
        $feed,
        1
    ) ?: $feed;
}

function koyori_start_rss_stylesheet_buffer(): void
{
    if (is_feed()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        ob_start('koyori_add_rss_stylesheet_instruction');
    }
}
add_action('template_redirect', 'koyori_start_rss_stylesheet_buffer', 0);

function koyori_render_rss_preview_stylesheet(): void
{
    if ((string) get_query_var('koyori_rss_xsl') !== '1') {
        return;
    }

    $site_title = esc_html(get_bloginfo('name'));
    $site_description = esc_html(get_bloginfo('description'));
    $site_url = esc_url(home_url('/'));
    $avatar = esc_url(
        iro_opt('personal_avatar')
            ?: iro_opt('iro_logo')
            ?: iro_opt('favicon_link')
            ?: get_site_icon_url(160)
    );
    $desktop_background = esc_url(iro_opt('random_graphs_link', ''));
    $mobile_background = esc_url(iro_opt('random_graphs_link_mobile', $desktop_background));
    $background_split = iro_opt('random_graphs_mts') ? '1' : '0';
    $darkmode_auto = iro_opt('theme_darkmode_auto') ? '1' : '0';
    $darkmode_strategy = esc_attr(iro_opt('theme_darkmode_strategy', 'time'));
    $css_url = esc_url(get_stylesheet_directory_uri() . '/rss/feed.css?ver=' . rawurlencode((string) IRO_VERSION . '-rss3'));

    $xsl = <<<'XSL'
<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:atom="http://www.w3.org/2005/Atom">
    <xsl:output method="html" encoding="UTF-8" indent="yes" />
    <xsl:template match="/">
        <html lang="zh-CN">
            <head>
                <meta charset="utf-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <title>__SITE_TITLE__ · RSS</title>
                <link rel="stylesheet" href="__CSS_URL__" />
            </head>
            <body data-desktop-background="__DESKTOP_BACKGROUND__" data-mobile-background="__MOBILE_BACKGROUND__" data-background-split="__BACKGROUND_SPLIT__" data-darkmode-auto="__DARKMODE_AUTO__" data-darkmode-strategy="__DARKMODE_STRATEGY__">
                <div class="rss-orbit orbit-one"></div>
                <div class="rss-orbit orbit-two"></div>
                <div class="rss-orbit orbit-three"></div>
                <main class="rss-shell">
                    <header class="rss-header">
                        <div class="rss-profile">
                            <div class="rss-avatar-wrap">
                                <img class="rss-avatar" src="__AVATAR__" alt="__SITE_TITLE__" />
                            </div>
                            <div class="rss-profile-copy">
                                <p class="rss-eyebrow">RSS · SUBSCRIPTION FEED</p>
                                <h1>__SITE_TITLE__</h1>
                                <p class="rss-description">__SITE_DESCRIPTION__</p>
                            </div>
                        </div>
                        <a class="rss-site-link" href="__SITE_URL__">访问博客 <span>→</span></a>
                    </header>

                    <section class="rss-intro">
                        <div>
                            <p class="rss-kicker">LATEST NOTES</p>
                            <h2>最近更新</h2>
                        </div>
                        <p class="rss-count"><xsl:value-of select="count(rss/channel/item)" /> 篇文章</p>
                    </section>

                    <section class="rss-grid">
                        <xsl:for-each select="rss/channel/item">
                            <article class="rss-card">
                                <div class="rss-card-topline"></div>
                                <div class="rss-card-body">
                                    <div class="rss-meta">
                                        <span class="rss-category">
                                            <xsl:choose>
                                                <xsl:when test="category"><xsl:value-of select="category[1]" /></xsl:when>
                                                <xsl:otherwise>文章</xsl:otherwise>
                                            </xsl:choose>
                                        </span>
                                        <span class="rss-date"><xsl:value-of select="substring(pubDate, 5, 12)" /></span>
                                    </div>
                                    <h3><a href="{link}"><xsl:value-of select="title" /></a></h3>
                                    <p class="rss-summary">
                                        <xsl:choose>
                                            <xsl:when test="contains(description, '&lt;/div&gt;')"><xsl:value-of select="normalize-space(substring-after(description, '&lt;/div&gt;'))" /></xsl:when>
                                            <xsl:otherwise><xsl:value-of select="normalize-space(description)" /></xsl:otherwise>
                                        </xsl:choose>
                                    </p>
                                </div>
                                <footer class="rss-card-footer">
                                    <a href="{link}">阅读全文 <span>↗</span></a>
                                </footer>
                            </article>
                        </xsl:for-each>
                    </section>

                    <footer class="rss-footer">
                        <span>保持好奇，持续记录</span>
                        <a href="{rss/channel/atom:link/@href}">订阅 RSS</a>
                    </footer>
                </main>
                <script>
                <![CDATA[
                (function () {
                    var body = document.body;
                    var root = document.documentElement;
                    var mobileQuery = window.matchMedia('(max-width: 720px)');
                    var systemQuery = window.matchMedia('(prefers-color-scheme: dark)');
                    var hasSplitBackground = body.dataset.backgroundSplit === '1';
                    var desktopBackground = body.dataset.desktopBackground;
                    var mobileBackground = body.dataset.mobileBackground || desktopBackground;

                    function setBackground() {
                        var image = hasSplitBackground && mobileQuery.matches ? mobileBackground : desktopBackground;
                        if (image) {
                            root.style.setProperty('--rss-bg-image', 'url("' + image.replace(/"/g, '\\"') + '")');
                        }
                    }

                    function isDark() {
                        var saved = localStorage.getItem('dark');
                        if (saved === '1') return true;
                        if (saved === '0') return false;
                        if (body.dataset.darkmodeAuto !== '1') return false;
                        if (body.dataset.darkmodeStrategy === 'client') return systemQuery.matches;
                        if (body.dataset.darkmodeStrategy === 'eien') return true;
                        var hour = new Date().getHours();
                        return hour > 21 || hour < 7;
                    }

                    function setTheme() {
                        root.dataset.rssTheme = isDark() ? 'dark' : 'light';
                    }

                    setBackground();
                    setTheme();
                    mobileQuery.addEventListener && mobileQuery.addEventListener('change', setBackground);
                    systemQuery.addEventListener && systemQuery.addEventListener('change', setTheme);
                    window.addEventListener('storage', function (event) {
                        if (event.key === 'dark') setTheme();
                    });
                    window.setInterval(setTheme, 60000);
                }());
                ]]>
                </script>
            </body>
        </html>
    </xsl:template>
    <xsl:template match="text()" mode="rss-summary"><xsl:value-of select="normalize-space(.)" /><xsl:text> </xsl:text></xsl:template>
    <xsl:template match="*" mode="rss-summary"><xsl:apply-templates select="node()" mode="rss-summary" /></xsl:template>
</xsl:stylesheet>
XSL;

    $xsl = strtr($xsl, array(
        '__SITE_TITLE__' => $site_title,
        '__SITE_DESCRIPTION__' => $site_description,
        '__SITE_URL__' => $site_url,
        '__AVATAR__' => $avatar,
        '__CSS_URL__' => $css_url,
        '__DESKTOP_BACKGROUND__' => $desktop_background,
        '__MOBILE_BACKGROUND__' => $mobile_background,
        '__BACKGROUND_SPLIT__' => $background_split,
        '__DARKMODE_AUTO__' => $darkmode_auto,
        '__DARKMODE_STRATEGY__' => $darkmode_strategy,
    ));

    status_header(200);
    header('Content-Type: text/xsl; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo $xsl;
    exit;
}
add_action('template_redirect', 'koyori_render_rss_preview_stylesheet');
