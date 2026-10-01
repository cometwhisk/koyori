<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package Sakurairo
 */

if ( ! function_exists( 'koyori_render_pagination' ) ) :
/**
 * Render a shared numeric pagination component for custom page templates.
 *
 * @param int    $current_page Current page number.
 * @param int    $total_pages Total number of pages.
 * @param string $base_url    Base URL for pagination links.
 * @param string $query_arg   Query argument used by the data source.
 * @param array  $args        Optional aria label and legacy class prefix.
 * @return string
 */
function koyori_render_pagination( int $current_page, int $total_pages, string $base_url, string $query_arg, array $args = array() ): string {
    $total_pages = absint( $total_pages );
    if ( $total_pages <= 1 || ! preg_match( '/^[A-Za-z0-9_-]+$/', $query_arg ) ) {
        return '';
    }

    $current_page = max( 1, absint( $current_page ) );
    $current_page = min( $current_page, $total_pages );
    $base_url = remove_query_arg( $query_arg, $base_url );
    $aria_label = isset( $args['aria_label'] ) && is_string( $args['aria_label'] )
        ? $args['aria_label']
        : __( 'Pagination', 'sakurairo' );
    $legacy_prefix = isset( $args['legacy_prefix'] ) && in_array( $args['legacy_prefix'], array( 'steam', 'bangumi' ), true )
        ? $args['legacy_prefix']
        : '';
    $classes = 'koyori-pagination' . ( $legacy_prefix ? ' ' . $legacy_prefix . '-pagination' : '' );
    $items = array();

    if ( $current_page > 1 ) {
        $items[] = '<a class="koyori-pagination__link koyori-pagination__prev' . ( $legacy_prefix ? ' ' . $legacy_prefix . '-pagination-link' : '' ) . '" href="' . esc_url( add_query_arg( $query_arg, $current_page - 1, $base_url ) ) . '" rel="prev" aria-label="' . esc_attr__( '上一页', 'sakurairo' ) . '"><i class="fa-solid fa-angle-left" aria-hidden="true"></i></a>';
    }

    $pages = $total_pages <= 7
        ? range( 1, $total_pages )
        : ( $current_page <= 4
            ? array( 1, 2, 3, 4, 5, 'ellipsis', $total_pages )
            : ( $current_page >= $total_pages - 3
                ? array( 1, 'ellipsis', $total_pages - 4, $total_pages - 3, $total_pages - 2, $total_pages - 1, $total_pages )
                : array( 1, 'ellipsis', $current_page - 1, $current_page, $current_page + 1, 'ellipsis', $total_pages ) ) );

    foreach ( $pages as $page ) {
        if ( 'ellipsis' === $page ) {
            $items[] = '<span class="koyori-pagination__ellipsis" aria-hidden="true">…</span>';
            continue;
        }
        $page = absint( $page );
        if ( $page === $current_page ) {
            $items[] = '<span class="koyori-pagination__current' . ( $legacy_prefix ? ' ' . $legacy_prefix . '-pagination-current' : '' ) . '" aria-current="page" aria-label="' . esc_attr( sprintf( __( '当前页，第 %d 页', 'sakurairo' ), $page ) ) . '">' . esc_html( $page ) . '</span>';
        } else {
            $href = 1 === $page ? $base_url : add_query_arg( $query_arg, $page, $base_url );
            $items[] = '<a class="koyori-pagination__link koyori-pagination__page' . ( $legacy_prefix ? ' ' . $legacy_prefix . '-pagination-link' : '' ) . '" href="' . esc_url( $href ) . '" aria-label="' . esc_attr( sprintf( __( '第 %d 页', 'sakurairo' ), $page ) ) . '">' . esc_html( $page ) . '</a>';
        }
    }

    if ( $current_page < $total_pages ) {
        $items[] = '<a class="koyori-pagination__link koyori-pagination__next' . ( $legacy_prefix ? ' ' . $legacy_prefix . '-pagination-link' : '' ) . '" href="' . esc_url( add_query_arg( $query_arg, $current_page + 1, $base_url ) ) . '" rel="next" aria-label="' . esc_attr__( '下一页', 'sakurairo' ) . '"><i class="fa-solid fa-angle-right" aria-hidden="true"></i></a>';
    }

    return '<nav class="' . esc_attr( $classes ) . '" aria-label="' . esc_attr( $aria_label ) . '"><div class="koyori-pagination__items">' . implode( '', $items ) . '</div></nav>';
}
endif;

if ( ! function_exists( 'akina_posted_on' ) ) :
/**
 * Prints HTML with meta information for the current post-date/time and author.
 */
function akina_posted_on() {
	$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
	if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
		$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
	}

	$time_string = sprintf( $time_string,
		esc_attr( get_the_date( 'c' ) ),
		esc_html( get_the_date() ),
		esc_attr( get_the_modified_date( 'c' ) ),
		esc_html( get_the_modified_date() )
	);

	$posted_on = sprintf(
		_x( 'Posted on %s', 'post date', 'sakurairo' ),
		'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $time_string . '</a>'
	);

	$byline = sprintf(
		_x( 'by %s', 'post author', 'sakurairo' ),
		'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'
	);

	echo '<span class="posted-on">' . $posted_on . '</span><span class="byline"> ' . $byline . '</span>'; // WPCS: XSS OK.

}
endif;

if ( ! function_exists( 'akina_entry_footer' ) ) :
/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function akina_entry_footer() {
	// Hide category and tag text for pages.
	if ( 'post' === get_post_type() ) {
		/* translators: used between list items, there is a space after the comma */
		$categories_list = get_the_category_list( __( ', ', 'sakurairo' ) );
		if ( $categories_list && akina_categorized_blog() ) {
			printf( '<span class="cat-links">' . __( 'Posted in %1$s', 'sakurairo' ) . '</span>', $categories_list ); // WPCS: XSS OK.
		}

		/* translators: used between list items, there is a space after the comma */
		$tags_list = get_the_tag_list( '', __( ', ', 'sakurairo' ) );
		if ( $tags_list ) {
			printf( '<span class="tags-links">' . __( 'Tagged %1$s', 'sakurairo' ) . '</span>', $tags_list ); // WPCS: XSS OK.
		}
	}

	if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
		echo '<span class="comments-link">';
		/* translators: %s: post title */
		comments_popup_link( sprintf( wp_kses( __( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'sakurairo' ), array( 'span' => array( 'class' => array() ) ) ), get_the_title() ) );
		echo '</span>';
	}

	edit_post_link(
		sprintf(
			/* translators: %s: Name of current post */
			__( 'Edit %s', 'sakurairo' ),
			the_title( '<span class="screen-reader-text">"', '"</span>', false )
		),
		'<span class="edit-link">',
		'</span>'
	);
}
endif;

/**
 * Returns true if a blog has more than 1 category.
 *
 * @return bool
 */
function akina_categorized_blog() {
	if ( false === ( $all_the_cool_cats = get_transient( 'akina_categories' ) ) ) {
		// Create an array of all the categories that are attached to posts.
		$all_the_cool_cats = get_categories( array(
			'fields'     => 'ids',
			'hide_empty' => 1,
			// We only need to know if there is more than one category.
			'number'     => 2,
		) );

		// Count the number of categories that are attached to the posts.
		$all_the_cool_cats = count( $all_the_cool_cats );

		set_transient( 'akina_categories', $all_the_cool_cats );
	}

	if ( $all_the_cool_cats > 1 ) {
		// This blog has more than 1 category so akina_categorized_blog should return true.
		return true;
	} else {
		// This blog has only 1 category so akina_categorized_blog should return false.
		return false;
	}
}

/**
 * Flush out the transients used in akina_categorized_blog.
 */
function akina_category_transient_flusher() {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// Like, beat it. Dig?
	delete_transient( 'akina_categories' );
}
add_action( 'edit_category', 'akina_category_transient_flusher' );
add_action( 'save_post',     'akina_category_transient_flusher' );
