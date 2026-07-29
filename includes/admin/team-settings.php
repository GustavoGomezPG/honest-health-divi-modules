<?php
/**
 * Teams settings screens.
 *
 * Registers the "Teams" top-level admin menu with two sub-pages, plus the ACF
 * field groups backing them. Definitions live here rather than in the database
 * so they are version-controlled and deploy with the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HONEST_TEAM_MENU_SLUG', 'honest-teams' );
define( 'HONEST_TEAM_PAGE_EXECUTIVE', 'honest-teams-executive' );
define( 'HONEST_TEAM_PAGE_MARKETS', 'honest-teams-markets' );
define( 'HONEST_TEAM_MAX_MARKETS', 4 );

/**
 * The post type members are selected from.
 */
function honest_team_member_post_type() {
	return 'article-author';
}

/**
 * Map animation segments, in the order they are bound to repeater rows.
 *
 * The Lottie map is a single animation split into four segments. Which segment
 * plays is decided by a market's position in the repeater, not by the name
 * typed into it, so this order is the single source of truth for both the
 * editor instructions and the front-end module.
 *
 * @return string[]
 */
function honest_team_market_segments() {
	return array(
		__( 'West', 'honest-divi-modules' ),
		__( 'Southwest', 'honest-divi-modules' ),
		__( 'Midwest', 'honest-divi-modules' ),
		__( 'East', 'honest-divi-modules' ),
	);
}

/**
 * Editor instructions explaining the position-to-animation binding.
 *
 * ACF runs instructions through acf_esc_html(), which allows the wp_kses_post()
 * tag list, so structured markup is safe from an escaping standpoint. It is NOT
 * safe from a selector standpoint: instructions render inside the repeater's
 * own field wrapper, and ACF counts repeater rows by looking for <tr> elements
 * there. A <table> here is therefore counted as rows and wrongly trips the
 * "maximum rows reached" guard. Use list markup, never table markup.
 *
 * @return string
 */
function honest_team_markets_instructions() {
	$items = '';

	foreach ( honest_team_market_segments() as $segment ) {
		$items .= sprintf( '<li><strong>%s</strong></li>', esc_html( $segment ) );
	}

	return sprintf(
		'<p>%1$s</p><ol style="margin:6px 0 8px 18px;">%2$s</ol><p><strong>%3$s</strong> %4$s</p>',
		esc_html__( 'The map is one Lottie animation split into four segments. Which segment plays is decided by a market\'s position in this list — not by the name you type.', 'honest-divi-modules' ),
		$items,
		esc_html__( 'Reordering changes the map.', 'honest-divi-modules' ),
		sprintf(
			/* translators: %d: maximum number of markets. */
			esc_html__( 'Dragging a market to a new position makes it play that position\'s segment instead. Maximum %d markets, because the animation has four segments.', 'honest-divi-modules' ),
			HONEST_TEAM_MAX_MARKETS
		)
	);
}

/**
 * Register the Teams menu and its two sub-pages.
 */
function honest_team_register_options_pages() {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title' => __( 'Teams', 'honest-divi-modules' ),
			'menu_title' => __( 'Teams', 'honest-divi-modules' ),
			'menu_slug'  => HONEST_TEAM_MENU_SLUG,
			'capability' => 'edit_posts',
			'icon_url'   => 'dashicons-groups',
			'position'   => 26,
			'redirect'   => true,
		)
	);

	acf_add_options_sub_page(
		array(
			'page_title'  => __( 'Executive Team', 'honest-divi-modules' ),
			'menu_title'  => __( 'Executive Team', 'honest-divi-modules' ),
			'menu_slug'   => HONEST_TEAM_PAGE_EXECUTIVE,
			'parent_slug' => HONEST_TEAM_MENU_SLUG,
			'capability'  => 'edit_posts',
		)
	);

	acf_add_options_sub_page(
		array(
			'page_title'  => __( 'Markets', 'honest-divi-modules' ),
			'menu_title'  => __( 'Markets', 'honest-divi-modules' ),
			'menu_slug'   => HONEST_TEAM_PAGE_MARKETS,
			'parent_slug' => HONEST_TEAM_MENU_SLUG,
			'capability'  => 'edit_posts',
		)
	);
}
add_action( 'acf/init', 'honest_team_register_options_pages' );

/**
 * Register the field groups for both sub-pages.
 */
function honest_team_register_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$post_type = honest_team_member_post_type();

	acf_add_local_field_group(
		array(
			'key'      => 'group_honest_executive_team',
			'title'    => __( 'Executive Team', 'honest-divi-modules' ),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => HONEST_TEAM_PAGE_EXECUTIVE,
					),
				),
			),
			'fields'   => array(
				array(
					'key'           => 'field_honest_executive_members',
					'label'         => __( 'Executive Team Members', 'honest-divi-modules' ),
					'name'          => 'executive_team_members',
					'type'          => 'relationship',
					'instructions'  => __( 'Search and add members, then drag them into the order they should appear in.', 'honest-divi-modules' ),
					'post_type'     => array( $post_type ),
					'filters'       => array( 'search' ),
					'return_format' => 'id',
					'min'           => 0,
					'max'           => 0,
				),
			),
		)
	);

	acf_add_local_field_group(
		array(
			'key'      => 'group_honest_markets',
			'title'    => __( 'Markets', 'honest-divi-modules' ),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => HONEST_TEAM_PAGE_MARKETS,
					),
				),
			),
			'fields'   => array(
				array(
					'key'          => 'field_honest_markets',
					'label'        => __( 'Markets', 'honest-divi-modules' ),
					'name'         => 'markets',
					'type'         => 'repeater',
					'instructions' => honest_team_markets_instructions(),
					'layout'       => 'block',
					'min'          => 0,
					'max'          => HONEST_TEAM_MAX_MARKETS,
					'button_label' => __( 'Add Market', 'honest-divi-modules' ),
					'sub_fields'   => array(
						array(
							'key'          => 'field_honest_market_name',
							'label'        => __( 'Market Name', 'honest-divi-modules' ),
							'name'         => 'market_name',
							'type'         => 'text',
							'instructions' => __( 'Shown as the tab label.', 'honest-divi-modules' ),
							'required'     => 1,
							'wrapper'      => array( 'width' => '30' ),
						),
						array(
							'key'          => 'field_honest_market_caption',
							'label'        => __( 'Caption', 'honest-divi-modules' ),
							'name'         => 'market_caption',
							'type'         => 'textarea',
							'instructions' => __( 'The line beneath the map, e.g. “Honest Health’s West market includes: Washington, Oregon, Idaho, Montana.”', 'honest-divi-modules' ),
							'rows'         => 2,
							'new_lines'    => '',
							'wrapper'      => array( 'width' => '70' ),
						),
						array(
							'key'           => 'field_honest_market_members',
							'label'         => __( 'Members', 'honest-divi-modules' ),
							'name'          => 'market_members',
							'type'          => 'relationship',
							'instructions'  => __( 'Search and add members, then drag them into order.', 'honest-divi-modules' ),
							'post_type'     => array( $post_type ),
							'filters'       => array( 'search' ),
							'return_format' => 'id',
							'min'           => 0,
							'max'           => 0,
						),
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'honest_team_register_fields' );

/**
 * Selected executive team member IDs, in the order set on the settings screen.
 *
 * @return int[]
 */
function honest_team_get_executive_members() {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$ids = get_field( 'executive_team_members', 'option' );

	return array_map( 'intval', (array) $ids );
}

/**
 * Configured markets, in the order set on the settings screen.
 *
 * The `segment` value is derived from position, matching the binding described
 * to editors in honest_team_markets_instructions().
 *
 * @return array[] Each entry: array{ name: string, caption: string, members: int[], segment: int, segment_name: string }
 */
function honest_team_get_markets() {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$rows     = get_field( 'markets', 'option' );
	$segments = honest_team_market_segments();
	$markets  = array();

	foreach ( (array) $rows as $row ) {
		if ( empty( $row['market_name'] ) ) {
			continue;
		}

		$position = count( $markets );

		$markets[] = array(
			'name'         => (string) $row['market_name'],
			'caption'      => isset( $row['market_caption'] ) ? (string) $row['market_caption'] : '',
			'members'      => array_map( 'intval', (array) ( isset( $row['market_members'] ) ? $row['market_members'] : array() ) ),
			'segment'      => $position,
			'segment_name' => isset( $segments[ $position ] ) ? $segments[ $position ] : '',
		);
	}

	return $markets;
}
