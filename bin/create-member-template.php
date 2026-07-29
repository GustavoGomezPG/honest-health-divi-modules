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
 *   - The template ID is added to the Theme Builder post's multi-row
 *     `_et_template` meta only if it is not already one of the rows.
 *
 * NEVER DESTROYS A HAND-EDITED LAYOUT. The layout's shortcode content is only
 * rewritten while it is still byte-for-byte what this script last wrote,
 * tracked by a content hash in `_honest_member_template_hash`. If the layout
 * has been edited since -- most realistically by someone re-saving it in the
 * Divi Theme Builder, which re-serializes the shortcode -- the script leaves
 * the content alone, says so, and KEEPS REFUSING ON EVERY SUBSEQUENT RUN. It
 * deliberately does not re-record the hash on that path: doing so would make
 * the next run mistake the edit for its own output and silently revert it, so
 * two deploys in a row would quietly destroy the editor's work.
 *
 * Everything else (the template's metas, its `_et_use_on` assignment, its
 * place in the Theme Builder's live template list) is still brought up to date
 * on a refusing run -- only the layout's content is held back.
 *
 * Resolving a refusal is a deliberate human act, one of exactly two:
 *
 *   - Keep the edit: do nothing. The refusal is not an error and the run still
 *     exits successfully.
 *   - Discard the edit and rebuild from this script:
 *       wp eval-file bin/create-member-template.php force
 *
 * `force` is the ONLY way to make the script overwrite content it did not
 * author. In particular, deleting `_honest_member_template_hash` does not do
 * it: a layout with no recorded hash has unknown provenance -- it may be one
 * a designer built by hand in the Theme Builder and that this script merely
 * adopted -- so a missing hash refuses too, rather than treating "I have no
 * record of writing this" as licence to replace it.
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
 * THE ROWS CARRY NO WIDTH ATTRIBUTES. They used to be forced to
 * `width="100%" max_width="100%"`, which was only ever necessary because every
 * module carried a page container of its own (`max-width: 1245px; margin: 0
 * auto`) and the two would otherwise have compounded -- i.e. the modules were
 * acting as rows. The modules render at 100% width now, so the row is the only
 * container and Divi decides how wide it is (Divi > Theme Options > Layout >
 * Website Content Width). The rows keep `custom_padding="0px||0px||true|false"`
 * because that is VERTICAL padding only: all three modules supply their own
 * vertical rhythm. The Team Member Header's back bar and the Call To Action's
 * band still reach the viewport edges -- each paints itself with a breakout
 * layer, see rule 5 in assets/css/modules.css's header -- so a normal-width row
 * is no obstacle to either.
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

/**
 * Whether to discard a hand-edited body layout and rebuild it from this
 * script. Off unless `force` is passed as a positional argument:
 *
 *   wp eval-file bin/create-member-template.php force
 *
 * A positional argument, not `--force`: WP-CLI's own parser rejects unknown
 * `--` flags on `wp eval-file` before the file is ever included, so a `--`
 * flag cannot reach this script at all. `--force` is still accepted here in
 * case a future WP-CLI passes it through, so the obvious spelling never
 * silently no-ops into a destructive default.
 */
$honest_tpl_force = isset( $args ) && is_array( $args )
	&& ( in_array( 'force', $args, true ) || in_array( '--force', $args, true ) );

/**
 * Whether to (re)record the body layout content hash at the end of the run.
 *
 * Cleared on exactly one path: when a foreign edit to the layout is detected
 * and preserved. The hash must always describe the content THIS SCRIPT wrote,
 * never whatever happens to be in the post -- if a run that refused to touch
 * an edit still re-stamped the hash, the next run would read that edit as its
 * own previous output and silently overwrite it.
 */
$honest_tpl_stamp_hash = true;

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
 * Three sections, one per module, each in a row of Divi's own width with zero
 * VERTICAL padding -- see the file docblock for why. `theme_builder_area` marks the shortcodes as
 * belonging to a body layout, matching what Divi writes itself when a layout
 * is built through the Theme Builder UI.
 */
$honest_tpl_content = implode(
	'',
	array(
		// 1. Member header, including its own "Back to Team Page" bar.
		'[et_pb_section fb_built="1" _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_row _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
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
		'[et_pb_row _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_column type="4_4" _builder_version="4.27.4" _module_preset="default" theme_builder_area="et_body_layout"]',
		'[honest_featured_insights heading="Articles by %first_name%" source="current_member" limit="8" button_text="View All Thought Leadership" button_url="/industry-news/" button_position="top" heading_color="#1e1e1e" intro_color="#1e1e1e" button_bg_color="#ffffff" button_label_color="#6985c3" button_border_color="#6985c3" _builder_version="4.27.4" theme_builder_area="et_body_layout"][/honest_featured_insights]',
		'[/et_pb_column][/et_pb_row][/et_pb_section]',

		// 3. Closing CTA band.
		'[et_pb_section fb_built="1" _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
		'[et_pb_row _builder_version="4.27.4" _module_preset="default" custom_padding="0px||0px||true|false" theme_builder_area="et_body_layout"]',
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

	// "Untouched" means: still byte-for-byte what THIS SCRIPT last wrote. The
	// stored hash is the record of that write and nothing else -- see the
	// $honest_tpl_stamp_hash rule below for why it must never be re-stamped
	// from content the script did not author.
	//
	// A MISSING hash is deliberately not "untouched": a layout this script has
	// no record of writing may be one a designer built by hand and that the
	// find-or-create step above merely adopted, so it falls through to the
	// same refusal as an edited one. Only `force` overrides either case.
	$honest_tpl_untouched = '' !== $honest_tpl_last_run && md5( $honest_tpl_current ) === $honest_tpl_last_run;

	if ( $honest_tpl_current === $honest_tpl_content ) {
		// Already exactly the canonical content, so hashing it records a true
		// statement regardless of who typed it. This is the one branch that
		// stamps without writing, and it is safe precisely because there is
		// no editor work to lose: the content and the canonical content are
		// the same bytes.
		WP_CLI::log( 'Body layout content already matches; left untouched.' );
	} elseif ( $honest_tpl_untouched ) {
		// Still exactly what this script last wrote -- safe to bring forward.
		wp_update_post(
			array(
				'ID'           => $honest_tpl_body_id,
				'post_content' => $honest_tpl_content,
			)
		);
		WP_CLI::log( 'Body layout content refreshed from this script (it had not been edited since the last run).' );
	} elseif ( $honest_tpl_force ) {
		WP_CLI::warning(
			sprintf(
				'force: discarding the edited content of body layout %d and rebuilding it from this script.',
				$honest_tpl_body_id
			)
		);
		wp_update_post(
			array(
				'ID'           => $honest_tpl_body_id,
				'post_content' => $honest_tpl_content,
			)
		);
	} else {
		// A foreign edit. Refuse, loudly, and -- critically -- do NOT stamp
		// the hash. Stamping here would make the very next run mistake the
		// edit for this script's own work and silently revert it, which is
		// the opposite of what this guard exists to do.
		$honest_tpl_stamp_hash = false;

		WP_CLI::warning(
			sprintf(
				"Body layout %d does not match what this script last wrote -- it has been edited (most likely re-saved in the Divi Theme Builder).\n"
				. "Its content is LEFT AS-IS. This script will keep refusing on every run until a human resolves it; it will not overwrite the edit on a later deploy.\n"
				. "  To KEEP the edit:    do nothing. This run already updated the template, its metas and its assignment; only the layout content was held back.\n"
				. '  To DISCARD the edit: wp eval-file %s force',
				$honest_tpl_body_id,
				'wp-content/plugins/honest-divi-modules/bin/create-member-template.php'
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

// Record the hash of the content THIS SCRIPT wrote, so the next run can tell
// "unchanged since I wrote it" from "a human edited this in the UI".
//
// $honest_tpl_stamp_hash is false on exactly one path: the branch above that
// detected a foreign edit and preserved it. Stamping there would re-point the
// guard at content the script did not author, so the next run would read the
// edit as its own work and silently revert it -- the refusal must persist on
// every subsequent run until a human resolves it with `force`.
// The hash is taken by re-reading the stored content rather than hashing
// $honest_tpl_content directly, so that anything WordPress's save filters do
// to the shortcode on the way in is captured too -- otherwise a filtered save
// would read as a foreign edit on the very next run.
if ( $honest_tpl_stamp_hash ) {
	update_post_meta( $honest_tpl_body_id, $honest_tpl_hash_meta, md5( (string) get_post_field( 'post_content', $honest_tpl_body_id, 'raw' ) ) );
}

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
