<?php
/**
 * Seed the Divi Theme Builder body template that renders every team member's
 * page.
 *
 * A Theme Builder template is database state, not code, so it does not travel
 * with a plugin deploy. This script reproduces it from scratch on any
 * environment (local/staging/production) and keeps it in sync afterwards.
 *
 * Run with:
 *   wp eval-file bin/create-member-template.php
 *
 * SAFE TO RE-RUN. Every step is a find-or-create followed by a
 * write-only-if-different:
 *
 *   - The template is located by its `_et_use_on` condition (and a
 *     `_honest_member_template` marker meta), never created blind, so a second
 *     run reuses the same `et_template` post instead of adding a duplicate.
 *   - The body layout is located via the template's own
 *     `_et_body_layout_id`, so it is likewise never duplicated.
 *   - The layout's shortcode content is only rewritten while it still matches
 *     what this script last wrote (tracked by a content hash in
 *     `_honest_member_template_hash`). If someone has since edited the layout
 *     in the Divi Theme Builder UI, the edit is left alone and the script
 *     reports it rather than silently reverting a designer's work.
 *   - The template ID is added to the Theme Builder post's multi-row
 *     `_et_template` meta only if it is not already one of the rows.
 *
 * WHAT IT BUILDS
 *
 * An `et_template` assigned to ALL posts of the `article-author` post type
 * (the post type key is still `article-author`; only its URL slug moved to
 * `/team/` -- see bin/migrate-slug.php). The assignment string is
 * `singular:post_type:article-author:all`, the exact ID Divi itself builds for
 * an "All {post type}" condition -- see
 * et_theme_builder_get_template_settings_options_for_post_type() in
 * wp-content/themes/Divi/includes/builder/frontend-builder/theme-builder/theme-builder.php.
 * The script asserts that string exists in
 * et_theme_builder_get_flat_template_settings_options() before writing it: an
 * unrecognised condition would never match anything, and Divi validates each
 * `_et_use_on` row through that registry (ET_Theme_Builder_Request::
 * _fulfills_template_setting()) before a template can apply.
 *
 * The template is NOT the default template (`_et_default` = 0), so it can only
 * ever apply to requests its own condition matches -- singular `article-author`
 * posts. Regular pages and posts keep whatever template they already resolve
 * to. It reuses the site's existing header (38) and footer (97769) layouts,
 * the same pair the live default template uses, so a member page keeps the
 * site's normal chrome.
 *
 * The body layout -- the first `et_body_layout` post on this site -- holds
 * three Divi sections:
 *
 *   1. [honest_team_member_header] -- name/title/bio/quote/LinkedIn/portrait,
 *      including its own full-width "Back to Team Page" bar.
 *   2. [honest_featured_insights] with source="current_member" and limit="8" --
 *      the "Articles by {first name}" grid. `button_position="top"` puts the
 *      button beside the heading, per the Team Member Page design, and the
 *      heading/intro colours are overridden to #1e1e1e here because this
 *      instance sits on a light background (the module's own defaults are
 *      white, for the Our Team page's purple band).
 *   3. [honest_call_to_action] -- the closing band.
 *
 * Each row is full-width with zero padding: all three modules manage their own
 * inner max-width (1245px) and their bands are meant to be full-bleed, so
 * Divi's default row width/padding would double up on them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ET_THEME_BUILDER_TEMPLATE_POST_TYPE' ) || ! function_exists( 'et_theme_builder_get_theme_builder_post_id' ) ) {
	WP_CLI::error( 'Divi\'s Theme Builder is not loaded; ET_THEME_BUILDER_* constants and helpers are unavailable.' );
}

if ( ! function_exists( 'et_theme_builder_get_flat_template_settings_options' ) ) {
	WP_CLI::error( 'et_theme_builder_get_flat_template_settings_options() is unavailable; cannot validate the template condition.' );
}

$honest_tpl_post_type   = 'article-author';
$honest_tpl_title       = 'Team Member Template';
$honest_tpl_marker_meta = '_honest_member_template';
$honest_tpl_hash_meta   = '_honest_member_template_hash';

// The "All Article Authors" condition, assembled exactly the way Divi
// assembles it -- from the separator constant and the same four pieces, rather
// than hardcoding a colon-joined literal.
$honest_tpl_use_on = implode(
	ET_THEME_BUILDER_SETTING_SEPARATOR,
	array( 'singular', 'post_type', $honest_tpl_post_type, 'all' )
);

if ( ! post_type_exists( $honest_tpl_post_type ) ) {
	WP_CLI::error( sprintf( 'Post type "%s" is not registered; nothing to assign a template to.', $honest_tpl_post_type ) );
}

// Divi only honours a `_et_use_on` row it recognises, so refuse to write one
// it does not. This is the guard that makes a typo here inert rather than
// dangerous.
$honest_tpl_settings = et_theme_builder_get_flat_template_settings_options();

if ( ! isset( $honest_tpl_settings[ $honest_tpl_use_on ] ) ) {
	WP_CLI::error( sprintf( 'Divi does not recognise the template condition "%s"; aborting without changes.', $honest_tpl_use_on ) );
}

WP_CLI::log( sprintf( 'Template condition: %s (recognised by Divi).', $honest_tpl_use_on ) );

// Header/footer to reuse, read off the site's own default template rather than
// hardcoded, so this script produces the site's normal chrome on whichever
// environment it runs against.
$honest_tpl_theme_builder_id = (int) et_theme_builder_get_theme_builder_post_id( true, false );

if ( ! $honest_tpl_theme_builder_id ) {
	WP_CLI::error( 'No published Theme Builder post found; create one in Divi > Theme Builder first.' );
}

$honest_tpl_live_ids = array_map( 'intval', (array) get_post_meta( $honest_tpl_theme_builder_id, '_et_template', false ) );

WP_CLI::log( sprintf( 'Theme Builder post: %d. Live templates: %s.', $honest_tpl_theme_builder_id, implode( ', ', $honest_tpl_live_ids ) ) );

$honest_tpl_header_id = 0;
$honest_tpl_footer_id = 0;

foreach ( $honest_tpl_live_ids as $honest_tpl_live_id ) {
	if ( '1' !== get_post_meta( $honest_tpl_live_id, '_et_default', true ) ) {
		continue;
	}

	$honest_tpl_header_id = (int) get_post_meta( $honest_tpl_live_id, '_et_header_layout_id', true );
	$honest_tpl_footer_id = (int) get_post_meta( $honest_tpl_live_id, '_et_footer_layout_id', true );
	break;
}

if ( ! $honest_tpl_header_id || ! $honest_tpl_footer_id ) {
	WP_CLI::error( 'Could not read a header and footer layout ID off the live default template; aborting without changes.' );
}

WP_CLI::log( sprintf( 'Reusing header layout %d and footer layout %d from the default template.', $honest_tpl_header_id, $honest_tpl_footer_id ) );

/**
 * The body layout's Divi shortcode content.
 *
 * Three sections, one per module, each with a full-width zero-padding row --
 * see the file docblock for why. `theme_builder_area` marks the shortcodes as
 * belonging to a body layout, matching what Divi writes itself when a layout
 * is built through the Theme Builder UI.
 */
$honest_tpl_content = implode(
	'',
	array(
		// 1. Member header, including its own "Back to Team Page" bar.
		'[et_pb_section fb_built="1" _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_row _builder_version="4.27.4" _module_preset="default" width="100%" max_width="100%" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_column type="4_4" _builder_version="4.27.4" _module_preset="default" theme_builder_area="et_body_layout"]',
		'[honest_team_member_header back_text="Back to Team Page" back_url="/our-team/" _builder_version="4.27.4" theme_builder_area="et_body_layout"][/honest_team_member_header]',
		'[/et_pb_column][/et_pb_row][/et_pb_section]',

		// 2. "Articles by {first name}" grid. `%first_name%` is resolved per
		// member by the module -- one template, a different name on every
		// page. Heading/intro colours overridden to the dark neutral because
		// this instance sits on a light background, unlike the Our Team
		// page's purple band that the module's white defaults are for. Button
		// colours are the Team Member Page's outline treatment.
		'[et_pb_section fb_built="1" _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_row _builder_version="4.27.4" _module_preset="default" width="100%" max_width="100%" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_column type="4_4" _builder_version="4.27.4" _module_preset="default" theme_builder_area="et_body_layout"]',
		'[honest_featured_insights heading="Articles by %first_name%" source="current_member" limit="8" button_text="View All Thought Leadership" button_url="/industry-news/" button_position="top" heading_color="#1e1e1e" intro_color="#1e1e1e" button_bg_color="#ffffff" button_text_color="#6985c3" button_border_color="#6985c3" _builder_version="4.27.4" theme_builder_area="et_body_layout"][/honest_featured_insights]',
		'[/et_pb_column][/et_pb_row][/et_pb_section]',

		// 3. Closing CTA band.
		'[et_pb_section fb_built="1" _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_row _builder_version="4.27.4" _module_preset="default" width="100%" max_width="100%" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_column type="4_4" _builder_version="4.27.4" _module_preset="default" theme_builder_area="et_body_layout"]',
		'[honest_call_to_action heading="About Honest Health" button_text="About Honest Health" button_url="/our-story/" alignment="right" _builder_version="4.27.4" theme_builder_area="et_body_layout"]',
		'<p>Honest Health partners with primary care providers to build value-based care programs that work for clinicians and the people they care for.</p>',
		'[/honest_call_to_action]',
		'[/et_pb_column][/et_pb_row][/et_pb_section]',
	)
);

/**
 * Find the template this script owns, if it already exists.
 *
 * Matched on the `_et_use_on` condition rather than on the marker meta alone,
 * so a template created by hand through the Theme Builder UI for the same
 * post type is adopted and updated instead of being duplicated.
 */
$honest_tpl_existing = get_posts(
	array(
		'post_type'        => ET_THEME_BUILDER_TEMPLATE_POST_TYPE,
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'suppress_filters' => false,
		'meta_query'       => array(
			array(
				'key'     => '_et_use_on',
				'value'   => $honest_tpl_use_on,
				'compare' => '=',
			),
		),
	)
);

$honest_tpl_id = ! empty( $honest_tpl_existing ) ? (int) $honest_tpl_existing[0] : 0;

if ( count( $honest_tpl_existing ) > 1 ) {
	WP_CLI::warning(
		sprintf(
			'Found %d templates already assigned to "%s" (IDs %s). Reusing the first; the others are untouched and should be removed by hand.',
			count( $honest_tpl_existing ),
			$honest_tpl_use_on,
			implode( ', ', array_map( 'intval', $honest_tpl_existing ) )
		)
	);
}

if ( $honest_tpl_id ) {
	WP_CLI::log( sprintf( 'Found existing template %d; updating in place.', $honest_tpl_id ) );
} else {
	$honest_tpl_id = wp_insert_post(
		array(
			'post_type'   => ET_THEME_BUILDER_TEMPLATE_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $honest_tpl_title,
		),
		true
	);

	if ( is_wp_error( $honest_tpl_id ) ) {
		WP_CLI::error( 'Could not create the template post: ' . $honest_tpl_id->get_error_message() );
	}

	$honest_tpl_id = (int) $honest_tpl_id;
	WP_CLI::log( sprintf( 'Created template %d ("%s").', $honest_tpl_id, $honest_tpl_title ) );
}

/**
 * Find or create the body layout. Located via the template's own
 * `_et_body_layout_id` so it can never be duplicated on a re-run.
 */
$honest_tpl_body_id = (int) get_post_meta( $honest_tpl_id, '_et_body_layout_id', true );

if ( $honest_tpl_body_id
	&& ( ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE !== get_post_type( $honest_tpl_body_id ) || 'publish' !== get_post_status( $honest_tpl_body_id ) )
) {
	// A stale ID pointing at a deleted or wrong-typed post: Divi would zero it
	// out at render time anyway, so treat it as absent and build a fresh one.
	WP_CLI::warning( sprintf( 'Template %d points at %d, which is not a published body layout; creating a new one.', $honest_tpl_id, $honest_tpl_body_id ) );
	$honest_tpl_body_id = 0;
}

if ( $honest_tpl_body_id ) {
	WP_CLI::log( sprintf( 'Found existing body layout %d.', $honest_tpl_body_id ) );

	$honest_tpl_current  = (string) get_post_field( 'post_content', $honest_tpl_body_id, 'raw' );
	$honest_tpl_last_run = (string) get_post_meta( $honest_tpl_body_id, $honest_tpl_hash_meta, true );

	if ( $honest_tpl_current === $honest_tpl_content ) {
		WP_CLI::log( 'Body layout content already matches; left untouched.' );
	} elseif ( '' !== $honest_tpl_last_run && md5( $honest_tpl_current ) === $honest_tpl_last_run ) {
		// Still exactly what this script last wrote -- safe to bring forward.
		wp_update_post(
			array(
				'ID'           => $honest_tpl_body_id,
				'post_content' => $honest_tpl_content,
			)
		);
		WP_CLI::log( 'Body layout content refreshed from this script (it had not been edited since the last run).' );
	} else {
		WP_CLI::warning(
			sprintf(
				'Body layout %d has been edited since this script last wrote it; its content is left as-is. Delete the layout (or clear its %s meta) if you want this script to rebuild it.',
				$honest_tpl_body_id,
				$honest_tpl_hash_meta
			)
		);
	}
} else {
	$honest_tpl_body_id = wp_insert_post(
		array(
			'post_type'    => ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $honest_tpl_title,
			'post_content' => $honest_tpl_content,
		),
		true
	);

	if ( is_wp_error( $honest_tpl_body_id ) ) {
		WP_CLI::error( 'Could not create the body layout post: ' . $honest_tpl_body_id->get_error_message() );
	}

	$honest_tpl_body_id = (int) $honest_tpl_body_id;
	WP_CLI::log( sprintf( 'Created body layout %d.', $honest_tpl_body_id ) );
}

// Divi will not process the layout's shortcodes unless the post is flagged as
// a builder post -- the same three metas its own header/footer layouts carry.
$honest_tpl_layout_meta = array(
	'_et_pb_use_builder'          => 'on',
	'_et_pb_built_for_post_type'  => 'page',
	'_et_pb_show_page_creation'   => 'off',
);

foreach ( $honest_tpl_layout_meta as $honest_tpl_key => $honest_tpl_value ) {
	if ( (string) get_post_meta( $honest_tpl_body_id, $honest_tpl_key, true ) !== (string) $honest_tpl_value ) {
		update_post_meta( $honest_tpl_body_id, $honest_tpl_key, $honest_tpl_value );
	}
}

// Record what the layout content looks like now, so the next run can tell
// "unchanged since I wrote it" from "a human edited this in the UI".
update_post_meta( $honest_tpl_body_id, $honest_tpl_hash_meta, md5( (string) get_post_field( 'post_content', $honest_tpl_body_id, 'raw' ) ) );

/**
 * Template metas. Written only where they differ, so a re-run is a no-op.
 * `_et_default` is explicitly 0: a default template with no overrides would
 * take over every page on the site.
 */
$honest_tpl_metas = array(
	'_et_enabled'               => '1',
	'_et_default'               => '0',
	'_et_autogenerated_title'   => '0',
	'_et_header_layout_id'      => (string) $honest_tpl_header_id,
	'_et_header_layout_enabled' => '1',
	'_et_body_layout_id'        => (string) $honest_tpl_body_id,
	'_et_body_layout_enabled'   => '1',
	'_et_footer_layout_id'      => (string) $honest_tpl_footer_id,
	'_et_footer_layout_enabled' => '1',
	'_et_onboarding_created'    => '0',
	$honest_tpl_marker_meta     => '1',
);

foreach ( $honest_tpl_metas as $honest_tpl_key => $honest_tpl_value ) {
	if ( (string) get_post_meta( $honest_tpl_id, $honest_tpl_key, true ) !== (string) $honest_tpl_value ) {
		update_post_meta( $honest_tpl_id, $honest_tpl_key, $honest_tpl_value );
		WP_CLI::log( sprintf( '  set %s = %s', $honest_tpl_key, $honest_tpl_value ) );
	}
}

// `_et_use_on` is a MULTI-ROW meta (a template can carry several conditions),
// so add a row rather than overwrite, and only if this exact condition is not
// already one of them.
$honest_tpl_use_on_rows = (array) get_post_meta( $honest_tpl_id, '_et_use_on', false );

if ( ! in_array( $honest_tpl_use_on, $honest_tpl_use_on_rows, true ) ) {
	add_post_meta( $honest_tpl_id, '_et_use_on', $honest_tpl_use_on );
	WP_CLI::log( sprintf( '  added _et_use_on row "%s"', $honest_tpl_use_on ) );
}

// Divi treats a template as live only while its ID appears in the Theme
// Builder post's own multi-row `_et_template` meta. Same add-if-absent rule.
if ( ! in_array( $honest_tpl_id, $honest_tpl_live_ids, true ) ) {
	add_post_meta( $honest_tpl_theme_builder_id, '_et_template', $honest_tpl_id );
	WP_CLI::log( sprintf( 'Added template %d to Theme Builder post %d.', $honest_tpl_id, $honest_tpl_theme_builder_id ) );
} else {
	WP_CLI::log( sprintf( 'Template %d is already live on Theme Builder post %d.', $honest_tpl_id, $honest_tpl_theme_builder_id ) );
}

// A template that is referenced again must not stay flagged for cleanup by
// et_theme_builder_trash_draft_and_unused_posts().
delete_post_meta( $honest_tpl_id, '_et_theme_builder_marked_as_unused' );
delete_post_meta( $honest_tpl_body_id, '_et_theme_builder_marked_as_unused' );

if ( function_exists( 'et_theme_builder_clear_wp_cache' ) ) {
	et_theme_builder_clear_wp_cache();
	WP_CLI::log( 'Cleared Theme Builder / page caches.' );
}

WP_CLI::success(
	sprintf(
		'Template %d (body layout %d) is live for %s.',
		$honest_tpl_id,
		$honest_tpl_body_id,
		$honest_tpl_use_on
	)
);
