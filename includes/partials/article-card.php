<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Article card used by Featured Insights and the member template.
 *
 * Renders every credited author's byline (posts can and do have more than
 * one), and falls back to the post title/excerpt when the editor-authored
 * `card_title` / `card_description` meta fields are blank.
 *
 * @param WP_Post $post Post to render the card for.
 * @return string Card markup, or an empty string if $post is invalid.
 */
function honest_team_render_article_card( $post ) {
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$card_title = (string) get_post_meta( $post->ID, 'card_title', true );
	$card_desc  = (string) get_post_meta( $post->ID, 'card_description', true );
	$title      = '' !== $card_title ? $card_title : get_the_title( $post );
	$desc       = '' !== $card_desc ? $card_desc : get_the_excerpt( $post );

	$terms = get_the_terms( $post->ID, 'category' );
	$flag  = ( $terms && ! is_wp_error( $terms ) )
		? sprintf( '<span class="honest-article-card__flag">%s</span>', esc_html( $terms[0]->name ) )
		: '';

	$image = has_post_thumbnail( $post )
		? get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'honest-article-card__image', 'loading' => 'lazy' ) )
		: '';

	// Tested on the rendered HTML rather than has_post_thumbnail() alone, so a
	// featured image whose attachment has since been deleted (which still
	// reports true but renders '') falls back to the placeholder too.
	if ( '' === $image ) {
		$image = honest_team_render_media_placeholder();
	}

	$byline = '';
	foreach ( honest_team_get_article_authors( $post->ID ) as $author ) {
		$byline .= sprintf(
			'<span class="honest-article-card__author"><span class="honest-article-card__author-name">%1$s</span><span class="honest-article-card__author-title">%2$s</span></span>',
			esc_html( $author['name'] ),
			esc_html( $author['job_title'] )
		);
	}

	return sprintf(
		'<article class="honest-article-card">
			<div class="honest-article-card__media">%1$s%2$s</div>
			<h3 class="honest-article-card__title">%3$s</h3>
			<div class="honest-article-card__byline">%4$s</div>
			<p class="honest-article-card__desc">%5$s</p>
			<a class="honest-article-card__link" href="%6$s">%7$s</a>
		</article>',
		$image,
		$flag,
		esc_html( $title ),
		$byline,
		esc_html( $desc ),
		esc_url( get_permalink( $post ) ),
		esc_html__( 'Read More', 'honest-divi-modules' )
	);
}
