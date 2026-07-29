<?php
/**
 * One-off migration: give the "article-author" ACF post type a custom
 * "team" URL slug instead of deriving the slug from the post type key.
 *
 * Member URLs move from /article-author/{slug}/ to /team/{slug}/. The old
 * URLs are indexed by search engines (they are in the Yoast sitemap), so a
 * template_redirect handler elsewhere in this plugin 301s them to the new
 * location — see includes/admin/slug-migration.php. This script only
 * changes the ACF post type definition; it does not touch post content,
 * postmeta, or any individual member.
 *
 * Run with:
 *   wp eval-file bin/migrate-slug.php
 *
 * Safe to re-run: it reads the current ACF post type definition, sets the
 * rewrite slug to "team" (a no-op if already set), and saves. Saving via
 * acf_update_post_type() re-registers the post type with WordPress and
 * flushes rewrite rules in the same request, but this script also flushes
 * explicitly afterward as a defensive, idempotent belt-and-suspenders step.
 *
 * Does NOT touch has_archive (left false) so a future page at /team/ can
 * coexist with member URLs at /team/{slug}/.
 *
 * DEPLOY ORDER: includes/admin/slug-migration.php's redirect handler checks
 * the post type's live rewrite slug and stays inert (no redirects) until it
 * is "team", so deploying the plugin before running this script — or a DB
 * restore that reopens that window — is safe rather than turning indexed
 * /article-author/ URLs into 301s to a 404. Still, run this script as part
 * of the same deploy so the URLs move promptly: /article-author/ only
 * starts redirecting to /team/ once this script has been run.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Identify the post type by its ACF key rather than a hardcoded post ID, so
// this script works unchanged across environments (local/staging/production)
// even if the underlying post ID differs between databases.
$honest_migration_post_type_key = 'post_type_6803de60156dd';
$honest_migration_target_slug   = 'team';

if ( ! function_exists( 'acf_get_post_type' ) || ! function_exists( 'acf_update_post_type' ) ) {
	WP_CLI::error( 'ACF is not active; acf_get_post_type()/acf_update_post_type() are unavailable.' );
}

$honest_post_type = acf_get_post_type( $honest_migration_post_type_key );

if ( ! $honest_post_type ) {
	WP_CLI::error( sprintf( 'No ACF post type found for key "%s".', $honest_migration_post_type_key ) );
}

if ( 'article-author' !== $honest_post_type['post_type'] ) {
	WP_CLI::error( sprintf( 'Expected post_type "article-author", found "%s". Aborting without changes.', $honest_post_type['post_type'] ) );
}

if ( ! empty( $honest_post_type['has_archive'] ) ) {
	WP_CLI::error( 'has_archive is enabled on this post type; this script assumes it is false so /team/ can be a separate page. Aborting without changes.' );
}

WP_CLI::log( sprintf( 'Found post type "%s" (ID %d, key %s).', $honest_post_type['post_type'], $honest_post_type['ID'], $honest_post_type['key'] ) );
WP_CLI::log( 'Current rewrite config: ' . wp_json_encode( $honest_post_type['rewrite'] ) );

// Switch from deriving the slug off the post type key to a custom slug.
// ACF's own admin UI writes this exact shape (see
// includes/admin/views/acf-post-type/advanced-settings.php in ACF Pro):
// 'permalink_rewrite' => 'custom_permalink' unlocks the 'slug' key, which is
// otherwise ignored. with_front/feeds/pages are carried over unchanged.
$honest_post_type['rewrite']['permalink_rewrite'] = 'custom_permalink';
$honest_post_type['rewrite']['slug']               = $honest_migration_target_slug;

$honest_updated = acf_update_post_type( $honest_post_type );

if ( ! $honest_updated ) {
	WP_CLI::error( 'acf_update_post_type() did not return a post type array; the update may have failed.' );
}

WP_CLI::log( 'New rewrite config: ' . wp_json_encode( $honest_updated['rewrite'] ) );

// Belt-and-suspenders: acf_update_post_type() already re-registers the post
// type and flushes rewrite rules internally, but flush again explicitly so
// this script's success does not depend on that internal behavior.
flush_rewrite_rules();

WP_CLI::success( sprintf( 'Rewrite slug for "%s" set to "%s" and rewrite rules flushed.', $honest_post_type['post_type'], $honest_migration_target_slug ) );
