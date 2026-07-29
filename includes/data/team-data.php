<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalised member data. Returns null if the post is missing or not published.
 */
function honest_team_get_member( $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );

	if ( ! $post || honest_team_member_post_type() !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}

	return array(
		'id'        => $post_id,
		'name'      => get_the_title( $post_id ),
		'job_title' => (string) get_post_meta( $post_id, 'job_title_short', true ),
		'bio'       => (string) get_post_meta( $post_id, 'bio', true ),
		'quote'     => (string) get_post_meta( $post_id, 'quote', true ),
		'linkedin'  => (string) get_post_meta( $post_id, 'linkedin_url', true ),
		'image_id'  => (int) get_post_meta( $post_id, 'author_image', true ),
		'permalink' => (string) get_permalink( $post_id ),
	);
}

/**
 * Members for a list of IDs, preserving the given order.
 *
 * @param int[] $ids
 * @return array[]
 */
function honest_team_get_members( $ids ) {
	$members = array();

	foreach ( (array) $ids as $id ) {
		$member = honest_team_get_member( $id );

		if ( $member ) {
			$members[] = $member;
		}
	}

	return $members;
}

/**
 * Posts this member is credited on, via the existing `article_authors`
 * relationship field stored on the post.
 *
 * @return WP_Post[]
 */
function honest_team_get_articles_by_member( $member_id, $limit = 8 ) {
	$member_id = (int) $member_id;

	if ( ! $member_id ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'meta_query'     => array(
				array(
					'key'     => 'article_authors',
					'value'   => '"' . $member_id . '"',
					'compare' => 'LIKE',
				),
			),
		)
	);
}

/**
 * Credited members for a post, for card bylines.
 *
 * @return array[]
 */
function honest_team_get_article_authors( $post_id ) {
	$ids = get_post_meta( (int) $post_id, 'article_authors', true );

	return honest_team_get_members( (array) maybe_unserialize( $ids ) );
}
