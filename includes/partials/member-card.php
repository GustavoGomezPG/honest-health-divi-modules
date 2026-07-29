<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Member card used by the Executive Leadership and Leadership by Market modules.
 *
 * @param array $member Shape returned by honest_team_get_member().
 */
function honest_team_render_member_card( $member ) {
	if ( empty( $member['id'] ) ) {
		return '';
	}

	$image = $member['image_id']
		? wp_get_attachment_image( $member['image_id'], 'medium', false, array( 'class' => 'honest-member-card__image', 'loading' => 'lazy' ) )
		: '';

	// Tested on the rendered HTML, not on image_id: a member can carry a
	// non-zero author_image whose attachment no longer exists, in which case
	// wp_get_attachment_image() returns '' (member 102473 does exactly this).
	// Both that and a genuinely unset image land on the placeholder.
	if ( '' === $image ) {
		$image = honest_team_render_media_placeholder();
	}

	return sprintf(
		'<a class="honest-member-card" href="%1$s">
			<span class="honest-member-card__media">%2$s</span>
			<span class="honest-member-card__body">
				<span class="honest-member-card__text">
					<span class="honest-member-card__name">%3$s</span>
					<span class="honest-member-card__title">%4$s</span>
				</span>
				<span class="honest-member-card__arrow" aria-hidden="true"></span>
			</span>
		</a>',
		esc_url( $member['permalink'] ),
		$image,
		esc_html( $member['name'] ),
		esc_html( $member['job_title'] )
	);
}
