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

/**
 * Segment frame ranges from the Lottie manifest, cached per request.
 *
 * Returns only the `segments` array, in repeater-row order. The manifest's own
 * `index` is 1-based; array position is 0-based and is what the module uses.
 *
 * @return array[] Each: { index, name, slug, in, out, emptyFrame, frames, states, labelFrame }
 */
function honest_team_map_segment_ranges() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$path = HONEST_DIVI_MODULES_DIR . 'assets/lottie/market-map-segments.json';

	if ( ! file_exists( $path ) ) {
		$cache = array();
		return $cache;
	}

	$data  = json_decode( (string) file_get_contents( $path ), true );
	$cache = isset( $data['segments'] ) && is_array( $data['segments'] ) ? $data['segments'] : array();

	return $cache;
}

/**
 * A member to stand in for the real one while editing in a builder.
 *
 * The Team Member Header and the Featured Insights "current member" sources all
 * resolve their member from the post being viewed. In the Theme Builder's layout
 * editor there is no such post -- the queried object is the layout itself -- so
 * both sections render empty and the template cannot be laid out or styled.
 * This supplies a real member for that case only.
 *
 * ONLY for builder requests. The caller is responsible for that check, and every
 * caller makes it: a random member appearing on a live page would be a data bug,
 * not a preview.
 *
 * The choice is held in a transient rather than drawn fresh each time, because
 * the header and the article grid are fetched in SEPARATE requests. Two
 * independent draws would put one person in the header and "Articles by" someone
 * else underneath it, which reads as broken. Sharing the pick keeps the preview
 * coherent.
 *
 * WHEN IT CHANGES is the whole design of this: rotation is tied to opening the
 * Theme Builder, not to the clock -- see honest_team_rotate_preview_member().
 * A short expiry was the obvious first approach and was wrong in both
 * directions. Reloading the editor inside the window kept showing the same
 * person, so it looked stuck; and an expiry that fell BETWEEN the header
 * request and the grid request produced exactly the mismatch the shared pick
 * exists to prevent (observed: a heading reading "Articles by Mary" above one of
 * Greg's articles). The long expiry here is only a floor under the transient, so
 * a stale pick cannot outlive a deleted member indefinitely.
 *
 * Members with at least one credited article are preferred, so the section below
 * the header is populated too; on this site that is 15 of 17. Anyone published
 * is acceptable if none qualify.
 *
 * @return int Member post ID, or 0 when there are no published members at all.
 */
function honest_team_get_preview_member_id() {
	$cached = get_transient( 'honest_team_preview_member' );

	if ( $cached && honest_team_get_member( $cached ) ) {
		return (int) $cached;
	}

	return honest_team_pick_preview_member();
}

/**
 * Draw a new stand-in member and store it.
 *
 * Hooked to loading the Theme Builder screen, which is the one moment that
 * reliably means "a new editing session is starting" and is not itself one of
 * the many requests that make up that session. The alternative, hooking a
 * builder ajax action, was measured and rejected: the layout editor issues no
 * et_fb_retrieve_builder_data at all -- the layout arrives embedded in the admin
 * page -- and the actions it does issue fire repeatedly per session, so the
 * member would change underneath the editor.
 *
 * The previous pick is excluded so a reload visibly produces somebody new. With
 * a pool this size a fair draw repeats about one time in fifteen, which is
 * indistinguishable from the rotation being broken.
 *
 * @param int $exclude Member to avoid choosing, when the pool allows it.
 * @return int Member post ID, or 0 when there are no published members at all.
 */
function honest_team_pick_preview_member( $exclude = 0 ) {
	$members = get_posts(
		array(
			'post_type'      => honest_team_member_post_type(),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	if ( empty( $members ) ) {
		return 0;
	}

	$with_articles = array();

	foreach ( $members as $id ) {
		if ( honest_team_get_articles_by_member( $id, 1 ) ) {
			$with_articles[] = $id;
		}
	}

	$pool = ! empty( $with_articles ) ? $with_articles : $members;

	// Only when something is left: with a single candidate, repeating it beats
	// returning nobody and rendering the sections empty.
	$without_previous = array_values( array_diff( $pool, array( (int) $exclude ) ) );

	if ( ! empty( $without_previous ) ) {
		$pool = $without_previous;
	}

	$choice = (int) $pool[ wp_rand( 0, count( $pool ) - 1 ) ];

	set_transient( 'honest_team_preview_member', $choice, DAY_IN_SECONDS );

	return $choice;
}

/**
 * Rotate the stand-in member when the Theme Builder is opened.
 *
 * Checked against $_GET rather than a `load-{$hook}` hook because the screen id
 * is assembled from Divi's menu slug ("divi_page_et_theme_builder"), which is
 * not ours to depend on. Reading the page argument is the stable half of that.
 */
function honest_team_rotate_preview_member() {
	// phpcs:ignore WordPress.Security.NonceVerification -- reading which admin
	// screen is being displayed; changes nothing a visitor can see.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( 'et_theme_builder' !== $page ) {
		return;
	}

	honest_team_pick_preview_member( (int) get_transient( 'honest_team_preview_member' ) );
}
