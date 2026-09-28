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
    $css_url = esc_url(get_stylesheet_directory_uri() . '/rss/feed.css?ver=' . rawurlencode((string) IRO_VERSION . '-rss2'));

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
            <body>
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
    ));

    status_header(200);
    header('Content-Type: text/xsl; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo $xsl;
    exit;
}
add_action('template_redirect', 'koyori_render_rss_preview_stylesheet');
