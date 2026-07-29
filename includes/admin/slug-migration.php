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
 *
 * The second guard is the same problem one level down: a 301 is permanently
 * cacheable, so redirecting a URL whose target does not exist teaches every
 * crawler and browser that caches it to keep going to a dead URL — the exact
 * SEO harm this migration exists to prevent. Blind path rewriting did that
 * for /article-author/ itself (no member slug at all) and for any
 * /article-author/{anything}/ that never named a real member, both of which
 * 301'd straight into a 404. So the member is resolved first and the target
 * comes from get_permalink(); anything that does not resolve to a published
 * member is left alone to 404 normally, uncached.
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

	// Exactly one path segment after the prefix is a member URL. The bare
	// /article-author/ archive URL leaves '' here, and anything deeper (a
	// paged or otherwise nested URL) is not a member permalink either;
	// neither has a real /team/ equivalent to send a permanent redirect to.
	$slug = trim( (string) substr( $path, strlen( '/article-author/' ) ), '/' );

	if ( '' === $slug || false !== strpos( $slug, '/' ) ) {
		return;
	}

	$member = get_page_by_path( rawurldecode( $slug ), OBJECT, 'article-author' );

	if ( ! $member instanceof WP_Post || 'publish' !== $member->post_status ) {
		return;
	}

	// get_permalink() rather than a str_replace() on the request path: it is
	// the post's real, current URL, so the redirect target is by construction
	// a URL that resolves.
	$target = get_permalink( $member );

	if ( ! $target ) {
		return;
	}

	if ( ! empty( $query ) ) {
		$target .= '?' . $query;
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'honest_team_redirect_legacy_urls', 1 );
