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
 *
 * This handler ships in the plugin, but the slug change itself lives only in
 * the database (applied by bin/migrate-slug.php). If this code deploys
 * before that script runs — or a DB restore reopens that window — the
 * post type's rewrite slug is still "article-author", so /team/{slug}/
 * would not resolve. The guard below keeps the handler inert until the
 * rewrite slug is actually "team", so it cannot redirect indexed URLs into
 * 404s during that window.
 */
function honest_team_redirect_legacy_urls() {
	if ( is_admin() ) {
		return;
	}

	$post_type = get_post_type_object( 'article-author' );

	if ( ! $post_type || empty( $post_type->rewrite['slug'] ) || 'team' !== $post_type->rewrite['slug'] ) {
		return;
	}

	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
	$query       = wp_parse_url( $request_uri, PHP_URL_QUERY );

	if ( ! $path || 0 !== strpos( $path, '/article-author/' ) ) {
		return;
	}

	$target = home_url( str_replace( '/article-author/', '/team/', $path ) );

	if ( ! empty( $query ) ) {
		$target .= '?' . $query;
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'honest_team_redirect_legacy_urls', 1 );
