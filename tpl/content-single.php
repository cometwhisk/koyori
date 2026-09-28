<?php
/**
 * Template part for displaying posts.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Sakurairo
 */
$legacy_ai_excerpt = (string) get_post_meta($post_id, 'ai_summon_excerpt', true);
$native_excerpt = trim((string) get_post_field('post_excerpt', $post_id));
$ai_excerpt_hash = trim((string) get_post_meta($post_id, '_koyori_ai_excerpt_hash', true));
$is_ai_excerpt = $native_excerpt !== '' && $ai_excerpt_hash !== ''
    && hash_equals($ai_excerpt_hash, hash('sha256', $native_excerpt));
$display_ai_excerpt = $is_ai_excerpt ? $native_excerpt : ($native_excerpt === '' ? $legacy_ai_excerpt : '');
?>

<?php
$post = get_post();
if (iro_opt('article_auto_toc', 'true') && check_title_tags($post->post_content)) {
	echo '<div class="has-toc have-toc"></div>';
}
?>

<article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class(); ?>>
	<?php if (should_show_title()) { 
		get_template_part('tpl/single-entry-header');
	} ?>
	<?php if ($display_ai_excerpt !== '') { ?>
	<div class="ai-excerpt">
		<h4><i class="fa-solid fa-atom"></i><?php esc_html_e("AI Excerpt", "sakurairo"); ?></h4><?php echo esc_html($display_ai_excerpt); ?>
	</div>
	<?php } ?>
	<div class="entry-content">
		<?php the_content('', true); ?>
		<?php
			wp_link_pages(array(
				'before' => '<div class="page-links">' . esc_html__('Pages:', 'ondemand'),
				'after'  => '</div>',
			));
		?>
	</div><!-- .entry-content -->
	<?php get_template_part('tpl/section-article-function'); ?>
</article><!-- #post-## -->
