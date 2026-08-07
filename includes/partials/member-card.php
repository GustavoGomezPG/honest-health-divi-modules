<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * <img> attributes for a member card portrait, with a working srcset.
 *
 * WordPress builds a perfectly good srcset for these -- and then Divi throws it
 * away. `et_filter_wp_calculate_image_srcset` reduces the candidate list to
 * Divi's own responsive widths, which for a 300px `medium` leaves fewer than the
 * two core requires, so `wp_calculate_image_srcset()` returns false and the card
 * ships a lone 300x300 that a 2x screen renders soft. Measured on a 1200x1200
 * source: eight valid candidates exist when that filter runs, and none survive
 * it. Asking for `large` gets a srcset; asking for `medium` does not.
 *
 * So core does the work with Divi's filter lifted for the duration of one call,
 * rather than the candidate list being rebuilt here. That is deliberate. The
 * first attempt did hand-roll it, and picked up a bug worth recording: for the
 * 480x480 file, `wp_get_attachment_image_src()` returns a width of 270 -- Divi's
 * downsize filter reports the size as REGISTERED (480x270, cropped) rather than
 * as generated. Declaring `480x480.png 270w` tells the browser the opposite of
 * the truth, and it duly skipped that candidate and took the 768 on a phone that
 * needed 322. Core reads real widths out of the attachment metadata and also
 * handles aspect-ratio matching, edited-image hashes and the width cap, none of
 * which is worth reimplementing.
 *
 * Fails safe in both directions: if Divi ever renames that function the
 * remove_filter() no-ops and this returns whatever core returns; if Divi stops
 * stripping srcsets, this is simply the same value core would have produced
 * anyway.
 *
 * The `sizes` list is the executive grid's real geometry -- Divi's row is a flat
 * 80% of the viewport, and the grid is 4 columns above 1024, 2 below it, 1 below
 * 600. It matters: without it the browser assumes 100vw and over-fetches on every
 * card.
 *
 * @param int $image_id Attachment ID.
 * @return array Attributes for wp_get_attachment_image().
 */
function honest_team_member_card_image_attrs( $image_id ) {
	$attrs = array(
		'class'   => 'honest-member-card__image',
		'loading' => 'lazy',
	);

	$lifted = remove_filter( 'wp_calculate_image_srcset', 'et_filter_wp_calculate_image_srcset', 10 );

	$srcset = wp_get_attachment_image_srcset( $image_id, 'medium' );

	if ( $lifted ) {
		add_filter( 'wp_calculate_image_srcset', 'et_filter_wp_calculate_image_srcset', 10, 4 );
	}

	if ( $srcset ) {
		$attrs['srcset'] = $srcset;
		$attrs['sizes']  = '(max-width: 600px) 80vw, (max-width: 1024px) 40vw, 20vw';
	}

	return $attrs;
}

/**
 * Member card used by the Executive Leadership and Leadership by Market modules.
 *
 * The markup is deliberately flat -- media, then a body holding the text and the
 * chevron -- because the redesign (Figma node 414:796) is entirely a CSS change:
 * the chevron moved from the name's row onto its own line beneath it, and a rule
 * appeared between portrait and text. Both fall out of `flex-direction: column`
 * and a `border-top` in modules.css, so no element was added or moved here.
 *
 * One upload contract the design depends on: portraits are transparent cutouts
 * whose subject is flush to the BOTTOM edge of the file. The card bottom-anchors
 * the image so every subject lands on the rule regardless of how tall the person
 * is in frame. A portrait with transparent padding below the subject will float
 * off the rule; a legacy opaque photo still renders (contained and bottom-
 * aligned) but will not sit against the rule the way the design shows.
 *
 * @param array    $member Shape returned by honest_team_get_member().
 * @param int|null $index  Position in its grid. When given, the card opts into
 *                         Divi's own scroll-in animation (see
 *                         honest_team_animation_attrs()) and is staggered.
 * @param string   $extra_classes Additional classes for the root element. Used by
 *                         Leadership by Market, whose cards are choreographed
 *                         against the map by JavaScript instead and so ship
 *                         hidden (see assets/js/market-map.js).
 */
function honest_team_render_member_card( $member, $index = null, $extra_classes = '' ) {
	if ( empty( $member['id'] ) ) {
		return '';
	}

	$animation = honest_team_animation_attrs( $index );

	$classes = $animation['class'];
	if ( '' !== trim( (string) $extra_classes ) ) {
		$classes .= ' ' . esc_attr( trim( (string) $extra_classes ) );
	}

	$image = $member['image_id']
		? wp_get_attachment_image(
			$member['image_id'],
			'medium',
			false,
			honest_team_member_card_image_attrs( $member['image_id'] )
		)
		: '';

	// Tested on the rendered HTML, not on image_id: a member can carry a
	// non-zero author_image whose attachment no longer exists, in which case
	// wp_get_attachment_image() returns '' (member 102473 does exactly this).
	// Both that and a genuinely unset image land on the placeholder.
	if ( '' === $image ) {
		$image = honest_team_render_media_placeholder();
	}

	// Omitted entirely rather than rendered empty: the text block is a flex column
	// with a gap, so an empty <span> still costs that gap and pushes the name off
	// centre against the members either side of it. Member 82762 has no
	// `job_title_short` and was doing exactly that.
	$title = '' !== trim( (string) $member['job_title'] )
		? sprintf( '<span class="honest-member-card__title">%s</span>', esc_html( $member['job_title'] ) )
		: '';

	return sprintf(
		'<a class="honest-member-card%4$s" href="%1$s"%5$s>
			<span class="honest-member-card__media">%2$s</span>
			<span class="honest-member-card__body">
				<span class="honest-member-card__text">
					<span class="honest-member-card__name">%3$s</span>
					%7$s
				</span>
				<span class="honest-member-card__arrow">%6$s</span>
			</span>
		</a>',
		esc_url( $member['permalink'] ),
		$image,
		esc_html( $member['name'] ),
		$classes,
		$animation['style'],
		honest_team_render_card_chevron(),
		$title
	);
}
