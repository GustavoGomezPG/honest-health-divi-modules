<?php
/**
 * Per-member ACF fields.
 *
 * Registers the "Team Member Details" field group on the article-author post
 * type, adding a pull quote and a LinkedIn URL. Definitions live here rather
 * than in the database so they are version-controlled and deploy with the
 * plugin, matching the existing UI-created "Article Authors" group only in
 * that both attach to the same post type — this group is registered and
 * maintained separately from it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Team Member Details field group.
 */
function honest_team_register_member_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_honest_member_details',
			'title'    => __( 'Team Member Details', 'honest-divi-modules' ),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => honest_team_member_post_type(),
					),
				),
			),
			'fields'   => array(
				array(
					'key'          => 'field_honest_member_quote',
					'label'        => __( 'Pull Quote', 'honest-divi-modules' ),
					'name'         => 'quote',
					'type'         => 'textarea',
					'instructions' => __( 'Shown on this member\'s page and eligible for the testimonial carousel. Leave blank to exclude from the carousel.', 'honest-divi-modules' ),
					'rows'         => 4,
					'new_lines'    => '',
				),
				array(
					'key'          => 'field_honest_member_linkedin',
					'label'        => __( 'LinkedIn URL', 'honest-divi-modules' ),
					'name'         => 'linkedin_url',
					'type'         => 'url',
					'instructions' => __( 'Full profile URL. Leave blank to hide the link.', 'honest-divi-modules' ),
				),
			),
		)
	);
}
add_action( 'acf/init', 'honest_team_register_member_fields' );
