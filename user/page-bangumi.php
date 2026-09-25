<?php
/*
  Template Name: Bangumi Template
*/
get_header();
?>

<style>
.site-content {
    max-width: 1280px;
}

span.linkss-title {
    font-size: 30px;
    text-align: center;
    display: block;
    margin: 6.5% 0 7.5%;
    letter-spacing: 2px;
    font-weight: var(--global-font-weight);
}

.bangumi {
    margin-top: 40px;
}

.bangumi .row {
    display: flex;
    margin: 0 -10px -20px;
    flex-wrap: wrap;
}

.bangumi .column {
    max-width: 100%;
    flex: 0 0 100%;
    margin-bottom: 30px;
    padding: 0 15px;
}

/* 卡片基础样式 */
.bangumi-item {
    height: 0;
    color: #fff;
    display: block;
    overflow: hidden;
    text-align: center;
    position: relative;
    padding-bottom: 130%;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, .1), 0 5px 20px rgba(0, 0, 0, .2);
}

.bangumi-item:hover {
    color: #fff;
}

.bangumi-item img {
    width: 100%;
    user-select: none;
    object-fit: cover;
    transition: filter 2s;
}

/* 信息层样式 */
.bangumi-info {
    height: 30%;
    top: 0;
    left: 0;
    right: 0;
    padding: 10px;
    position: absolute;
    background: rgba(0, 0, 0, .5);
    transition: transform 1s;
    transform: translateY(250%);
}

.bangumi-title {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    margin-top: 0;
}

.bangumi-summary {
    height: 65%;
    white-space: normal;
    display: none;
    font-weight: bold;
}

/* 状态栏样式 */
.bangumi-status {
    position: relative;
    background: rgba(0, 0, 0, .6);
    border-radius: 3px;
}

.bangumi-status-bar {
    top: 0;
    bottom: 0;
    max-width: 100%;
    position: absolute;
    background: #dc143c;
    border-radius: 3px;
}

.bangumi-status p {
    position: relative;
    width: 100%;
}

.bangumi-pagination {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 18px;
    margin: 18px 0 8px;
}

.bangumi-pagination-link,
.bangumi-pagination-current {
    padding: 10px 18px;
    border-radius: 999px;
    color: var(--global-font-color);
    background: rgba(255, 255, 255, 0.72);
    box-shadow: 0 1px 14px rgba(0, 0, 0, 0.08);
}

.bangumi-pagination-link {
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.bangumi-pagination-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 18px rgba(0, 0, 0, 0.12);
}

.bangumi-pagination-current {
    color: var(--global-font-secondary-color);
    background: rgba(255, 255, 255, 0.45);
}

.bangumi-list-summary {
    width: 100%;
    margin: 0 0 18px;
    text-align: center;
    color: var(--global-font-secondary-color);
}

body.dark .bangumi-pagination-link,
body.dark .bangumi-pagination-current {
    color: var(--dark-text-secondary);
    background: var(--dark-bg-secondary);
    box-shadow: var(--dark-shadow-normal);
}

body.dark .bangumi-list-summary {
    color: var(--dark-text-secondary);
}

/* 400px及以上 */
@media screen and (min-width: 400px) {
  .bangumi .column {
    max-width: 50%;
    flex: 0 0 50%;
  }
  .bangumi-info {
    height: 50%;
    transform: translateY(140%);
  }
  .bangumi-title {
    height: 20%;
  }
}

/* 600px及以上 */
@media screen and (min-width: 600px) {
  .bangumi .column {
    max-width: 33.3333%;
    flex: 0 0 33.3333%;
  }
  .bangumi-info {
    height: 45%;
    transform: translateY(140%);
  }
  .bangumi-title {
    height: 30%;
  }
}

/* 900px及以上 */
@media screen and (min-width: 900px) {
  .bangumi .column {
    max-width: 25%;
    flex: 0 0 25%;
  }
  .bangumi-info {
    height: 100%;
    transform: translateY(85%);
  }
  .bangumi-item:hover .bangumi-info {
    transform: translateY(0);
  }
  .bangumi-item:hover img {
    filter: blur(3px);
  }
  .bangumi-title {
    height: 15%;
  }
  .bangumi-summary {
    display: block;
  }
  .bangumi-status {
    height: 10%;
  }
}

/* 1200px及以上 */
@media screen and (min-width: 1200px) {
  .bangumi-info {
    height: 75%;
    transform: translateY(115%);
  }
  .bangumi-item:hover .bangumi-info {
    transform: translateY(35%);
  }
  .bangumi-title {
    height: 10%;
  }
}

@media screen and (min-width: 900px) {
  body.dark .bangumi-item:hover img {
    filter: brightness(0.8) blur(3px);
  }
}

</style>
</head>

<?php while (have_posts()) : the_post(); ?>
    <?php 
    if (!iro_opt('patternimg') || !koyori_has_cover(get_the_ID())) { 
    ?>
        <span class="linkss-title"><?php the_title(); ?></span>
    <?php 
    } 
    ?>

    <article <?php post_class("post-item"); ?>>
        <?php the_content('', true); ?>
        <section class="bangumi have-columns">
            <?php
            $bangumi_source = iro_opt('bangumi_source');
            $bilibili_id = iro_opt('bilibili_id');
            $mal_username = iro_opt('my_anime_list_username');
            $bangumi_id = iro_opt('bangumi_id');
            $bangumi_page = max(1, absint($_GET['bangumi_page'] ?? 1));
            $pagination_url = get_permalink();
            ?>

            <div class="row">
                <?php
                if ($bangumi_source === 'bilibili') {
                    if ($bilibili_id) {
                        $bgm = new \Sakura\API\Bilibili();
                        echo $bgm->get_bgm_items($bangumi_page, $pagination_url);
                    } else {
                        echo '<p>' . __("Please fill in the Bilibili UID in Sakura Options.", "sakurairo") . '</p>';
                    }
                } elseif ($bangumi_source === 'bangumi') {
                    if (!empty($bangumi_id)) {
                        $bgmList = new \Sakura\API\BangumiList();
                        echo $bgmList->get_bgm_items($bangumi_id, $bangumi_page, $pagination_url);
                    } else {
                        echo '<p>' . __("Please fill in the Bangumi UID in Sakura Options.", "sakurairo") . '</p>';
                    }
                } elseif ($mal_username) {
                    $bgm = new \Sakura\API\MyAnimeList();
                    echo $bgm->get_all_items($bangumi_page, $pagination_url);
                } else { 
                    echo '<p>' . __("Please fill in the My Anime List Username in Sakura Options.", "sakurairo") . '</p>';
                } 
                ?>
            </div>
        </section>
    </article>
<?php endwhile; ?>

<?php
get_footer();
?>
