<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scroll-in animation attributes for a repeated grid item.
 *
 * Deliberately reuses Divi's OWN animation system rather than shipping any
 * JavaScript of our own:
 *
 * - `et-waypoint` is the hook Divi's bundled waypoint handler looks for. It
 *   adds `et-animated` to the element once it scrolls into view.
 * Divi's own animation CLASSES are deliberately not used. `et_pb_animation_*`
 * looks global but the rule that actually animates is generated per-module by
 * Divi's CSS generator for Divi's own modules, so on a third-party module the
 * class matches nothing: the element gets hidden by `.et-waypoint` and never
 * revealed. The reveal therefore lives in this plugin's stylesheet, keyed off
 * the `et-animated` class Divi's handler adds.
 *
 * The only thing this plugin contributes is the per-item delay, which is what
 * turns a grid appearing all at once into a stagger. It is set inline because
 * it varies per item; an inline declaration outranks the stylesheet's
 * `animation` shorthand, so the delay survives.
 *
 * Passing a null index opts out entirely, which is what any caller rendering a
 * single, non-grid item should do.
 *
 * @param int|null $index    Zero-based position within its grid.
 * @param int      $step_ms  Delay added per position.
 * @param int      $max_ms   Ceiling, so a long grid's last item is not left
 *                           waiting seconds before it appears.
 * @return array{class:string,style:string} Pre-escaped fragments to splice
 *                                          into a class attribute and a tag.
 */
/**
 * Whether this render is for one of Divi's builders rather than the front end.
 *
 * Anything that starts hidden and relies on our JavaScript to reveal it must opt
 * out when this is true, because that JavaScript never runs against builder
 * output: the builder re-renders module HTML into its own React tree long after
 * DOMContentLoaded, so handlers bound at DOMContentLoaded -- Divi's waypoint
 * handler as well as ours -- never see those nodes. The content then stays at
 * the opacity it was shipped with, and the editor shows an empty section.
 *
 * Three checks, because no single one of Divi's own helpers covers the path that
 * actually matters here. Measured on Divi 4.27.7:
 *
 * - et_core_is_fb_enabled() keys off $_GET['et_fb'], so it only answers for the
 *   builder's own page load. That page turns out to contain no module markup at
 *   all (measured: zero cards in the ?et_fb=1 document), so on its own this
 *   check never once fires where it is needed.
 * - et_builder_is_loading_data() knows three actions -- et_fb_retrieve_builder_data,
 *   et_fb_update_builder_assets and et_pb_process_computed_property -- and none
 *   of them is the one that renders us.
 * - A module with $vb_support = 'partial' is rendered for the builder by
 *   et_fb_ajax_render_shortcode(), which is a plain do_shortcode() behind
 *   admin-ajax. It carries no et_fb query arg and is absent from the list above,
 *   which is why the first two checks alone left the builder showing an empty
 *   grid.
 *
 * The action test is deliberately a prefix rather than a fixed list: every
 *`et_fb_*` ajax action exists only to serve the Visual Builder, so matching the
 * family keeps this working when Divi renames or adds one. et_pb_process_computed_property
 * is named outright because it is the one relevant action outside that prefix.
 *
 * @return bool
 */
function honest_team_is_builder_render() {
	if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
		return true;
	}

	if ( function_exists( 'et_builder_is_loading_data' ) && et_builder_is_loading_data() ) {
		return true;
	}

	// phpcs:ignore WordPress.Security.NonceVerification -- read-only branch on a
	// presentation detail; Divi verifies the nonce in its own handler.
	$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

	return 'et_pb_process_computed_property' === $action || 0 === strpos( $action, 'et_fb_' );
}

function honest_team_animation_attrs( $index = null, $step_ms = 90, $max_ms = 540 ) {
	if ( null === $index || honest_team_is_builder_render() ) {
		return array(
			'class' => '',
			'style' => '',
		);
	}

	$delay = min( (int) $max_ms, max( 0, (int) $index ) * (int) $step_ms );

	return array(
		'class' => ' et-waypoint honest-anim',
		'style' => $delay > 0 ? sprintf( ' style="animation-delay:%dms"', $delay ) : '',
	);
}
