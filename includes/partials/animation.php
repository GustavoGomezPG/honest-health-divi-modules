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
function honest_team_animation_attrs( $index = null, $step_ms = 90, $max_ms = 540 ) {
	if ( null === $index ) {
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
