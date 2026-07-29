<?php
/**
 * Legacy URL redirects for the /article-author/ to /team/ slug migration.
 *
 * The "article-author" post type's rewrite slug was changed from the post
 * type key to "team" (see bin/migrate-slug.php). All 17 existing member
 * URLs are in the Yoast sitemap and indexable, so the old
 * /article-author/{slug}/ URLs must keep resolving via a permanent
 * redirect rather than 404ing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 301 old /article-author/{slug}/ URLs to /team/{slug}/.
 *
 * These URLs are in the Yoast sitemap and are indexable, so they must not 404
 * after the rewrite slug changes.
 */
function honest_team_redirect_legacy_urls() {
	if ( is_admin() ) {
		return;
	}

	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

	if ( ! $path || 0 !== strpos( $path, '/article-author/' ) ) {
		return;
	}

	$target = home_url( str_replace( '/article-author/', '/team/', $path ) );

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'honest_team_redirect_legacy_urls', 1 );
