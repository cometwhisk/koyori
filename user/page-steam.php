<?php
/*
  Template Name: Steam Library Template
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

    .steam-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 0 auto;
        justify-content: center;
        margin-top: 20px;
    }
    .steam-card {
        width: 23%;
        overflow: hidden;
        position: relative;
        border-radius: 12px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.06);
        transition: all 0.4s ease;
        transform: translateY(0);
        border: 1px solid rgba(255, 255, 255, 0.8);
        will-change: transform, box-shadow;
        display: flex;
        flex-direction: column;
        background-color: rgba(255, 255, 255, 0.7);
    }
    .steam-card-image {
        position: relative;
        overflow: hidden;
        aspect-ratio: 16 / 9;
        border-radius: 12px 12px 0 0;
    }
    .steam-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.7s cubic-bezier(0.25, 1, 0.5, 1);
        will-change: transform;
    }
    
    .steam-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 35px rgba(0, 0, 0, 0.1);
        background-color: rgba(255, 255, 255, 0.9);
    }
    
    .steam-card:hover img {
        transform: scale(1.07);
    }
    
    .steam-card-image, .steam-info {
        flex: 0 0 auto;
    }
    .steam-title-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        padding: 12px 15px;
        background: linear-gradient(to bottom, 
                    rgba(0, 0, 0, 0.7) 0%, 
                    rgba(0, 0, 0, 0.4) 60%, 
                    transparent 100%);
        transition: all 0.5s cubic-bezier(0.25, 1, 0.5, 1);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        z-index: 2;
        transform: translateY(0);
        mask-image: linear-gradient(180deg, #000000 20%, #0000004d);
        -webkit-mask-image: linear-gradient(180deg, #000000 20%, #0000004d);
    }
    
    .steam-title {
        margin: 0;
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: all 0.5s ease;
        opacity: 1;
        transform: translateY(0);
    }
    
    .steam-card:hover .steam-title-overlay {
        opacity: 0;
        transform: translateY(-15px);
    }
    
    .steam-card:hover .steam-title {
        opacity: 0;
        transform: translateY(-5px);
    }
    .steam-info {
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: rgba(255, 255, 255, 0.7);
        border-radius: 0 0 12px 12px;
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        will-change: transform;
    }
    .steam-stat {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 500;
        color: #505050;
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }
    
    .steam-stat:nth-child(2) {
        transition-delay: 0.05s;
    }

    .steam-library-summary-card {
        margin: 0 1.5% 20px;
        overflow: hidden;
        border: 1px solid rgba(232, 232, 232, 0.8);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.8);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .steam-library-summary-card .steam-user-card {
        margin: 0;
    }
    .steam-library-summary-card .steam-profile {
        margin: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        background: transparent;
    }
    .steam-library-summary-card .steam-profile:hover {
        transform: none;
        box-shadow: none;
    }
    .steam-library-summary-card .steam-summary {
        margin: 0;
        padding: 16px;
        border-top: 1px solid rgba(232, 232, 232, 0.8);
    }
    body.dark .steam-library-summary-card {
        border-color: rgba(70, 70, 70, 0.35);
        background: var(--dark-bg-secondary);
        box-shadow: var(--dark-shadow-normal);
    }

    .steam-summary {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin: 0 0 6px;
    }
    .steam-summary-item {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 58px;
        padding: 10px 16px;
        text-align: left;
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.62);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.045);
    }
    .steam-summary-icon {
        flex: 0 0 34px;
        width: 34px;
        color: var(--theme-skin-matching);
        font-size: 30px;
        line-height: 1;
        text-align: center;
    }
    .steam-summary-content {
        min-width: 0;
    }
    .steam-summary-value {
        display: block;
        color: var(--global-font-color);
        font-size: 21px;
        font-weight: 600;
        line-height: 1.15;
        white-space: nowrap;
    }
    .steam-summary-label {
        display: block;
        margin-top: 5px;
        color: #777;
        font-size: 12px;
        line-height: 1.2;
        white-space: nowrap;
    }
    body.dark .steam-summary-item {
        border-color: rgba(70, 70, 70, 0.35);
        background: var(--dark-bg-secondary);
        box-shadow: var(--dark-shadow-normal);
    }
    body.dark .steam-summary-value,
    body.dark .steam-summary-label {
        color: var(--dark-text-secondary);
    }

    .steam-pagination {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 18px;
        margin: 36px 0 8px;
    }
    .steam-pagination-link,
    .steam-pagination-current {
        padding: 10px 18px;
        border-radius: 999px;
        color: var(--global-font-color);
        background: rgba(255, 255, 255, 0.72);
        box-shadow: 0 1px 14px rgba(0, 0, 0, 0.08);
    }
    .steam-pagination-link {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .steam-pagination-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 18px rgba(0, 0, 0, 0.12);
    }
    .steam-pagination-current {
        color: var(--global-font-secondary-color);
        background: rgba(255, 255, 255, 0.45);
    }
    body.dark .steam-pagination-link,
    body.dark .steam-pagination-current {
        color: var(--dark-text-secondary);
        background: var(--dark-bg-secondary);
        box-shadow: var(--dark-shadow-normal);
    }
    @media (max-width: 768px) {
        .steam-pagination {
            gap: 8px;
        }
        .steam-pagination-link,
        .steam-pagination-current {
            padding: 9px 12px;
            font-size: 13px;
        }
    }
    
    /* Dark mode styles */
    body.dark .steam-card {
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(70, 70, 70, 0.3);
        background-color: var(--dark-bg-secondary);
    }

    body.dark .steam-card:hover {
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
    }
    
    body.dark .steam-info {
        background-color: var(--dark-bg-secondary);
    }
    
    body.dark .steam-stat {
        color: var(--dark-text-secondary);
    }
    
    .steam-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(120deg, 
                    rgba(255, 255, 255, 0) 30%, 
                    rgba(255, 255, 255, 0.08) 50%, 
                    rgba(255, 255, 255, 0) 70%);
        background-size: 200% 100%;
        z-index: 1;
        opacity: 0;
        transition: all 1.2s ease;
    }
    
    .steam-card:hover::before {
        opacity: 1;
        background-position: 100% 0;
    }
    
    .steam-card::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 12px;
        padding: 1px;
        background: linear-gradient(120deg, 
                    transparent, 
                    rgba(255, 255, 255, 0.3), 
                    transparent);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        opacity: 0;
        transition: opacity 0.8s ease;
    }
    
    .steam-card:hover::after {
        opacity: 1;
    }

    /* Responsive styles */
    @media (max-width: 1024px) {
        .steam-card {
            width: 31%;
        }
    }
    
    @media (max-width: 768px) {
        .steam-card {
            width: 96.5%;
        }
        .steam-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .steam-summary-item {
            gap: 8px;
            padding: 12px 8px;
        }
        .steam-summary-icon {
            flex-basis: 28px;
            width: 28px;
            font-size: 24px;
        }
        .steam-summary-value {
            font-size: 18px;
        }
        .steam-title-overlay{
            padding: 25px 30px;
        }
        .steam-title{
            font-size: 20px;
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
    <div class="steam-library-summary-card">
        <?php the_content('', true); ?>
        <?php
        $steam = new \Sakura\API\Steam();
        echo $steam->get_steam_summary();
        ?>
    </div>
    <section class="steam-row have-columns row">
        <?php
        $steam_page = max(1, absint($_GET['steam_page'] ?? 1));
        echo $steam->get_steam_items($steam_page, get_permalink());
        ?>
    </section>
</article>
<?php endwhile; ?>

<?php get_footer(); ?> 