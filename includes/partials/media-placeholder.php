<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Placeholder mark shown wherever a card's media is missing.
 *
 * Used by both card partials (and available to any future module) so the
 * empty-media treatment stays in one place. The mark is the Honest Health
 * heart, greyscaled in CSS (`filter: grayscale(1)` on .honest-media-placeholder)
 * rather than shipped as a second, pre-greyscaled binary: one asset stays the
 * single source of truth, and the same file can be reused in colour elsewhere.
 *
 * Callers should treat "missing" as "the image HTML came back empty", not
 * "the meta field is zero" -- a non-zero attachment ID whose attachment no
 * longer exists makes wp_get_attachment_image() return '' (member 102473 is a
 * live example), and that case needs the placeholder too.
 *
 * Decorative: empty alt + aria-hidden keeps it out of the accessibility tree,
 * since it carries no information the card's own text does not already give.
 *
 * @return string Placeholder <img> markup.
 */
function honest_team_render_media_placeholder() {
	return sprintf(
		'<img class="honest-media-placeholder" src="%s" alt="" aria-hidden="true" width="512" height="512" loading="lazy" decoding="async" />',
		esc_url( HONEST_DIVI_MODULES_URL . 'assets/img/honest-heart-placeholder.png' )
	);
}
