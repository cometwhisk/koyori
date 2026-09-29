<?php
/*
  Template Name: 无限暖暖模板
*/

$nikki = array(
    'nickname' => '小枫叶',
    'uid' => '115344663',
    'avatar' => 'https://tds-cdn.papegames.com/o/103/t/111-72c52430d5ebd91bad78c39c4a12b52c.jpg',
    'level' => '53',
    'login_days' => '42',
    'play_time' => '179.20h',
    'clothes' => '879',
    'designs' => '507',
    'dewdrop' => '676 / 3203',
    'pillar' => '221 / 240',
    'resonance' => '671',
    'limited_five' => '20',
    'limited_four' => '19',
    'standard_five' => '16',
    'four_star' => '53',
    'suits' => '4 / 128',
    'crown' => '15 / 15 层',
    'crown_peak' => '8 / 8 层',
);

$nikki_private = get_option('koyori_nikki_private', array());
if (function_exists('koyori_nikki_maybe_auto_sync_profile')) {
    koyori_nikki_maybe_auto_sync_profile();
    $nikki_private = get_option('koyori_nikki_private', array());
}
$nikki_saved = is_array($nikki_private) && is_array($nikki_private['profile_data'] ?? null) ? $nikki_private['profile_data'] : array();
$nikki_stats_saved = is_array($nikki_private) && is_array($nikki_private['stats_data'] ?? null) ? $nikki_private['stats_data'] : array();
$nikki['nickname'] = !empty($nikki_saved['nickname']) ? (string) $nikki_saved['nickname'] : $nikki['nickname'];
$nikki['uid'] = !empty($nikki_saved['uid']) ? (string) $nikki_saved['uid'] : $nikki['uid'];
$nikki['avatar'] = !empty($nikki_saved['avatar']) ? (string) $nikki_saved['avatar'] : $nikki['avatar'];
$nikki['level'] = !empty($nikki_saved['level']) ? (string) $nikki_saved['level'] : $nikki['level'];
foreach (array('login_days', 'play_time', 'clothes', 'designs', 'momo', 'resonance', 'suits', 'crown', 'crown_peak') as $key) {
    if (!empty($nikki_stats_saved[$key])) {
        $nikki[$key] = (string) $nikki_stats_saved[$key];
    }
}

$nikki_stats = array(
    array('icon' => 'fa-calendar-days', 'label' => '登录天数', 'value' => $nikki['login_days'] . ' 天'),
    array('icon' => 'fa-clock', 'label' => '游戏时长', 'value' => $nikki['play_time']),
    array('icon' => 'fa-shirt', 'label' => '服装数量', 'value' => $nikki['clothes']),
    array('icon' => 'fa-scroll', 'label' => '设计图', 'value' => $nikki['designs']),
    array('icon' => 'fa-star', 'label' => '共鸣次数', 'value' => $nikki['resonance']),
    array('icon' => 'fa-layer-group', 'label' => '集齐套装', 'value' => $nikki['suits']),
    array('icon' => 'fa-trophy', 'label' => '奇迹之冠', 'value' => $nikki['crown']),
    array('icon' => 'fa-ranking-star', 'label' => '巅峰赛', 'value' => $nikki['crown_peak']),
);

get_header();
?>

<style>
.nikki-page {
    --nikki-card: rgba(255, 255, 255, .68);
    --nikki-card-strong: rgba(255, 255, 255, .82);
    --nikki-border: rgba(255, 255, 255, .84);
    --nikki-muted: #77727b;
    max-width: 1120px;
    margin: 0 auto 80px;
    padding: 30px 18px 0;
}
.nikki-page * { box-sizing: border-box; }
.nikki-profile {
    display: grid;
    grid-template-columns: minmax(260px, .8fr) minmax(0, 1.7fr);
    gap: 20px;
    align-items: stretch;
}
.nikki-identity,
.nikki-stats,
.nikki-section {
    border: 1px solid var(--nikki-border);
    border-radius: 22px;
    background: var(--nikki-card);
    box-shadow: 0 12px 34px rgba(68, 43, 73, .09);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}
.nikki-identity {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    padding: 30px 22px;
    text-align: center;
}
.nikki-avatar {
    width: 112px;
    height: 112px;
    margin-bottom: 18px;
    border: 4px solid rgba(255,255,255,.9);
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 8px 24px rgba(83, 51, 93, .18);
}
.nikki-name {
    margin: 0;
    color: var(--global-font-color);
    font-size: 25px;
    font-weight: 600;
}
.nikki-uid {
    margin: 8px 0 18px;
    color: var(--nikki-muted);
    font-size: 13px;
}
.nikki-level {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 15px;
    border-radius: 999px;
    color: #fff;
    background: var(--theme-skin-matching, #b58bd2);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--theme-skin-matching, #b58bd2) 28%, transparent);
    font-size: 14px;
    font-weight: 600;
}
.nikki-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 16px;
}
.nikki-stat {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 84px;
    padding: 15px;
    border: 1px solid rgba(255,255,255,.72);
    border-radius: 16px;
    background: var(--nikki-card-strong);
}
.nikki-stat i {
    flex: 0 0 35px;
    color: var(--theme-skin-matching);
    font-size: 22px;
    text-align: center;
}
.nikki-stat-value {
    display: block;
    color: var(--global-font-color);
    font-size: 20px;
    font-weight: 600;
    line-height: 1.2;
}
.nikki-stat-label {
    display: block;
    margin-top: 5px;
    color: var(--nikki-muted);
    font-size: 12px;
}
.nikki-section {
    margin-top: 20px;
    padding: 22px;
}
.nikki-section-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 16px;
    color: var(--global-font-color);
    font-size: 17px;
    font-weight: 600;
}
.nikki-section-title i { color: var(--theme-skin-matching); }
.nikki-detail-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
.nikki-detail {
    padding: 14px 15px;
    border-radius: 14px;
    background: rgba(255,255,255,.46);
}
.nikki-detail-label { display:block; color:var(--nikki-muted); font-size:12px; }
.nikki-detail-value { display:block; margin-top:6px; color:var(--global-font-color); font-size:18px; font-weight:600; }
body.dark .nikki-identity,
body.dark .nikki-stats,
body.dark .nikki-section { background: var(--dark-bg-secondary); border-color: rgba(100,100,100,.35); box-shadow: var(--dark-shadow-normal); }
body.dark .nikki-level {
    color: var(--dark-bg-secondary);
    background: var(--theme-skin-dark, var(--theme-skin-matching, #b58bd2));
    border: 1px solid color-mix(in srgb, var(--theme-skin-dark, var(--theme-skin-matching, #b58bd2)) 45%, #fff);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--theme-skin-dark, var(--theme-skin-matching, #b58bd2)) 28%, transparent);
}
body.dark .nikki-stat,
body.dark .nikki-detail { background: rgba(255,255,255,.06); border-color: rgba(100,100,100,.28); }
body.dark .nikki-name,
body.dark .nikki-section-title,
body.dark .nikki-stat-value,
body.dark .nikki-detail-value { color: var(--dark-text-secondary); }
@media (max-width: 760px) {
    .nikki-page { padding: 15px 12px 0; }
    .nikki-profile { grid-template-columns: 1fr; }
    .nikki-identity { min-height: 260px; }
    .nikki-stats { gap: 9px; padding: 11px; }
    .nikki-stat { min-height: 76px; padding: 12px 10px; gap: 8px; }
    .nikki-stat i { flex-basis: 27px; font-size: 18px; }
    .nikki-stat-value { font-size: 17px; }
    .nikki-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<div class="nikki-page">
    <section class="nikki-profile" aria-label="<?php echo esc_attr(get_the_title()); ?>个人资料卡">
        <div class="nikki-identity">
            <img class="nikki-avatar" src="<?php echo esc_url($nikki['avatar']); ?>" alt="<?php echo esc_attr($nikki['nickname']); ?> 的头像" loading="lazy">
            <h2 class="nikki-name"><?php echo esc_html($nikki['nickname']); ?></h2>
            <p class="nikki-uid">UID <?php echo esc_html($nikki['uid']); ?></p>
            <span class="nikki-level"><i class="fa-solid fa-sparkles" aria-hidden="true"></i> 搭配师等级. <?php echo esc_html($nikki['level']); ?></span>
        </div>

        <div class="nikki-stats">
            <?php foreach ($nikki_stats as $stat): ?>
                <div class="nikki-stat">
                    <i class="fa-solid <?php echo esc_attr($stat['icon']); ?>" aria-hidden="true"></i>
                    <div>
                        <span class="nikki-stat-value"><?php echo esc_html($stat['value']); ?></span>
                        <span class="nikki-stat-label"><?php echo esc_html($stat['label']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="nikki-section" aria-label="收集与共鸣">
        <h2 class="nikki-section-title"><i class="fa-solid fa-gem" aria-hidden="true"></i> 收集与共鸣</h2>
        <div class="nikki-detail-grid">
            <div class="nikki-detail"><span class="nikki-detail-label">灵感露珠</span><span class="nikki-detail-value"><?php echo esc_html($nikki['dewdrop']); ?></span></div>
            <div class="nikki-detail"><span class="nikki-detail-label">流转之柱</span><span class="nikki-detail-value"><?php echo esc_html($nikki['pillar']); ?></span></div>
            <div class="nikki-detail"><span class="nikki-detail-label">限定五星</span><span class="nikki-detail-value"><?php echo esc_html($nikki['limited_five']); ?> 件</span></div>
            <div class="nikki-detail"><span class="nikki-detail-label">限定四星</span><span class="nikki-detail-value"><?php echo esc_html($nikki['limited_four']); ?> 件</span></div>
            <div class="nikki-detail"><span class="nikki-detail-label">常驻五星</span><span class="nikki-detail-value"><?php echo esc_html($nikki['standard_five']); ?> 件</span></div>
            <div class="nikki-detail"><span class="nikki-detail-label">四星数量</span><span class="nikki-detail-value"><?php echo esc_html($nikki['four_star']); ?></span></div>
            <div class="nikki-detail"><span class="nikki-detail-label">套装完成</span><span class="nikki-detail-value"><?php echo esc_html($nikki['suits']); ?></span></div>
            <div class="nikki-detail"><span class="nikki-detail-label">共鸣次数</span><span class="nikki-detail-value"><?php echo esc_html($nikki['resonance']); ?></span></div>
        </div>
    </section>
</div>

<?php get_footer(); ?>
