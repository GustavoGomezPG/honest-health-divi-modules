<?php
/**
 * Leadership by Market module.
 *
 * Renders the markets configured on Teams -> Markets as an ARIA tablist. Each
 * tab reveals that market's member grid (the shared member card partial, in the
 * order set on the settings screen) and its caption, and drives the animated
 * Lottie US map to that market's segment.
 *
 * Which map segment a market plays is decided by its POSITION in the repeater,
 * not by the name typed into it -- honest_team_get_markets() derives `segment`
 * from that position, and honest_team_map_segment_ranges() returns the frame
 * ranges in the same order. The manifest's own `index` field is 1-based and is
 * deliberately NOT used as an offset anywhere.
 *
 * Every colour this module renders is exposed as an editable Divi colour field
 * (Design tab) whose default is the hex extracted from Figma (file
 * 6LBpKOMFlN8KxaKbut00YW, market section node 145:413). The module writes the
 * chosen colours as CSS custom properties inline on its own wrapper via
 * wrap()'s $css_vars map; the card-related ones cascade to the shared member
 * card partial rendered inside it, which reads the same custom properties (each
 * default carried in the var() fallback at the point of use in modules.css, so
 * the value inherited from this wrapper always wins).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Leadership_By_Market extends Honest_Divi_Module_Base {

	public $slug = 'honest_leadership_by_market';

	/**
	 * Rendered-instance counter, used only to keep the tab/panel/caption ids
	 * unique when more than one instance ends up on a page.
	 *
	 * @var int
	 */
	private static $instances = 0;

	public function init() {
		$this->name             = esc_html__( 'Leadership by Market', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Content', 'honest-divi-modules' ) ) ),
			'advanced' => array(
				'toggles' => array(
					'heading' => esc_html__( 'Heading', 'honest-divi-modules' ),
					'intro'   => esc_html__( 'Intro', 'honest-divi-modules' ),
					'tab'     => esc_html__( 'Tabs', 'honest-divi-modules' ),
					'caption' => esc_html__( 'Caption', 'honest-divi-modules' ),
					'colors'  => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color: colour for these elements is owned exclusively by the
		// custom colour fields below, applied as inline CSS custom properties.
		// Divi's font-group builder otherwise auto-generates a native "Text
		// Color" sub-option per group that emits a directly-targeted CSS rule,
		// which would silently beat the inherited custom-property value and
		// defeat the single-source-of-truth colour rule.
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'heading' => array( 'label' => esc_html__( 'Heading', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-market__heading" ), 'toggle_slug' => 'heading', 'hide_text_color' => true ),
				'intro'   => array( 'label' => esc_html__( 'Intro', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-market__intro" ), 'toggle_slug' => 'intro', 'hide_text_color' => true ),
				'tab'     => array( 'label' => esc_html__( 'Tabs', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-market__tab" ), 'toggle_slug' => 'tab', 'hide_text_color' => true ),
				'caption' => array( 'label' => esc_html__( 'Caption', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-market__caption" ), 'toggle_slug' => 'caption', 'hide_text_color' => true ),
			)
		);
	}

	public function get_fields() {
		return array(
			'heading'                 => array(
				'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content'                 => array(
				'label'           => esc_html__( 'Intro', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			// Section colour fields. Defaults are the hexes extracted from Figma
			// (file 6LBpKOMFlN8KxaKbut00YW, market section node 145:413) by
			// pixel-sampling the rendered node screenshots -- see the task
			// report for the full method:
			//   heading  node 145:415 -> #1e1e1e
			//   intro    node 223:447 -> #1e1e1e
			//   caption  node 145:428 -> #6a8090 (also confirmed by the node's
			//            bound Figma variable "DARK GREY" = #6a8090)
			// The region tab pills live in node 145:419: a resting pill is a
			// white fill with a 2px #6985c3 border and #6985c3 label; the
			// selected pill (145:420, "West") is a solid #6985c3 fill with a
			// #ffffff label.
			'heading_color'           => array(
				'label'        => esc_html__( 'Heading Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the section heading.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#1e1e1e',
			),
			'intro_color'             => array(
				'label'        => esc_html__( 'Intro Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the intro copy.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#1e1e1e',
			),
			'tab_color'               => array(
				'label'        => esc_html__( 'Tab Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Label and border colour of a market tab that is not selected.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6985c3',
			),
			'tab_bg_color'            => array(
				'label'        => esc_html__( 'Tab Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background colour of a market tab that is not selected.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'tab_active_color'        => array(
				'label'        => esc_html__( 'Tab Color (Selected)', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Label colour of the selected market tab.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'tab_active_bg_color'     => array(
				'label'        => esc_html__( 'Tab Background (Selected)', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background, border and focus-ring colour of the selected market tab.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6985c3',
			),
			'caption_color'           => array(
				'label'        => esc_html__( 'Caption Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the caption beneath the map.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a8090',
			),
			// Card colour fields. Same Figma-extracted defaults as the Executive
			// Leadership module, because they drive the same shared member card
			// partial (member card component node 145:291 for the resting state,
			// hover-state example node 224:2431 for the hover fill and its hard
			// offset drop-shadow).
			'card_bg_color'           => array(
				'label'        => esc_html__( 'Card Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background colour of each member card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#d2d8ee',
			),
			'card_hover_bg_color'     => array(
				'label'        => esc_html__( 'Card Background (Hover)', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background colour of a member card on hover.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a4c91',
			),
			'card_hover_shadow_color' => array(
				'label'        => esc_html__( 'Card Shadow (Hover)', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the hard offset drop-shadow behind a member card on hover.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6985c3',
			),
			'card_name_color'         => array(
				'label'        => esc_html__( 'Card Name Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the member name on each card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a4c91',
			),
			'card_title_color'        => array(
				'label'        => esc_html__( 'Card Title Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the member job title on each card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#1e1e1e',
			),
		);
	}

	/**
	 * Text alternative for the map in a given market.
	 *
	 * The map is not decorative: it conveys which states are in the selected
	 * market. The states are taken from the Lottie manifest rather than from the
	 * editor-authored caption, so the alternative always describes what the
	 * animation actually draws even if the caption is empty or worded loosely.
	 *
	 * @param string $name    Market name, as typed by the editor.
	 * @param int    $segment 0-based segment position.
	 * @param array  $ranges  honest_team_map_segment_ranges() output.
	 * @return string Unescaped label text.
	 */
	private function map_label( $name, $segment, $ranges ) {
		$states = isset( $ranges[ $segment ]['states'] ) && is_array( $ranges[ $segment ]['states'] )
			? array_filter( array_map( 'strval', $ranges[ $segment ]['states'] ) )
			: array();

		if ( empty( $states ) ) {
			/* translators: %s: market name. */
			return sprintf( __( 'Map of the United States highlighting the %s market.', 'honest-divi-modules' ), $name );
		}

		return sprintf(
			/* translators: 1: market name, 2: comma-separated list of state names. */
			__( 'Map of the United States highlighting the %1$s market. States shown: %2$s.', 'honest-divi-modules' ),
			$name,
			implode( ', ', $states )
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$markets = honest_team_get_markets();

		if ( empty( $markets ) ) {
			return '';
		}

		wp_enqueue_script( 'honest-market-map' );

		$ranges = honest_team_map_segment_ranges();
		$uid    = 'honest-market-' . ( ++self::$instances );

		$tabs         = '';
		$panels       = '';
		$captions     = '';
		$first_cap_id = '';

		foreach ( $markets as $i => $market ) {
			$selected = 0 === $i;
			$segment  = (int) $market['segment'];
			$caption  = trim( (string) $market['caption'] );

			$tabs .= sprintf(
				'<button type="button" class="honest-market__tab" role="tab" id="%1$s-tab-%2$d" aria-controls="%1$s-panel-%2$d" aria-selected="%3$s" tabindex="%4$s" data-segment="%5$d" data-map-label="%6$s">%7$s</button>',
				esc_attr( $uid ),
				$i,
				$selected ? 'true' : 'false',
				$selected ? '0' : '-1',
				$segment,
				esc_attr( $this->map_label( $market['name'], $segment, $ranges ) ),
				esc_html( $market['name'] )
			);

			$cards = '';
			foreach ( honest_team_get_members( $market['members'] ) as $member ) {
				$cards .= honest_team_render_member_card( $member );
			}

			$panels .= sprintf(
				'<div class="honest-market__panel" role="tabpanel" id="%1$s-panel-%2$d" aria-labelledby="%1$s-tab-%2$d"%3$s><div class="honest-market__grid">%4$s</div></div>',
				esc_attr( $uid ),
				$i,
				$selected ? '' : ' hidden',
				$cards
			);

			$captions .= sprintf(
				'<p class="honest-market__caption" id="%1$s-caption-%2$d" data-index="%2$d"%3$s>%4$s</p>',
				esc_attr( $uid ),
				$i,
				$selected ? '' : ' hidden',
				esc_html( $caption )
			);

			if ( $selected && '' !== $caption ) {
				$first_cap_id = sprintf( '%s-caption-%d', $uid, $i );
			}
		}

		// The caption already names the states in the selected market, so it is
		// wired up as the map's description rather than duplicated into the
		// label. When a market has no caption there is nothing to point at, and
		// the attribute is omitted rather than left dangling at an empty node.
		$map = sprintf(
			'<div class="honest-market__map"><div class="honest-market-map" id="%1$s-map" data-lottie="%2$s" data-segments="%3$s" role="img" aria-label="%4$s"%5$s></div>%6$s</div>',
			esc_attr( $uid ),
			esc_url( HONEST_DIVI_MODULES_URL . 'assets/lottie/market-map.json' ),
			esc_attr( (string) wp_json_encode( $ranges ) ),
			esc_attr( $this->map_label( $markets[0]['name'], (int) $markets[0]['segment'], $ranges ) ),
			'' === $first_cap_id ? '' : sprintf( ' aria-describedby="%s"', esc_attr( $first_cap_id ) ),
			$captions
		);

		$head = '';
		if ( '' !== $this->props['heading'] ) {
			$head .= sprintf( '<h2 class="honest-market__heading">%s</h2>', esc_html( $this->props['heading'] ) );
		}
		if ( '' !== trim( (string) $this->content ) ) {
			$head .= sprintf( '<div class="honest-market__intro">%s</div>', et_core_esc_previously( $this->content ) );
		}

		$inner = sprintf(
			'<div class="honest-market__inner"><div class="honest-market__head">%1$s</div><div class="honest-market__tabs" role="tablist" aria-label="%2$s">%3$s</div><div class="honest-market__body"><div class="honest-market__panels">%4$s</div>%5$s</div></div>',
			$head,
			esc_attr__( 'Markets', 'honest-divi-modules' ),
			$tabs,
			$panels,
			$map
		);

		return $this->wrap(
			$render_slug,
			$inner,
			array( 'honest-market' ),
			array(
				'--hh-market-heading'       => $this->props['heading_color'],
				'--hh-market-intro'         => $this->props['intro_color'],
				'--hh-market-tab'           => $this->props['tab_color'],
				'--hh-market-tab-bg'        => $this->props['tab_bg_color'],
				'--hh-market-tab-active'    => $this->props['tab_active_color'],
				'--hh-market-tab-active-bg' => $this->props['tab_active_bg_color'],
				'--hh-market-caption'       => $this->props['caption_color'],
				'--hh-card-bg'              => $this->props['card_bg_color'],
				'--hh-card-hover-bg'        => $this->props['card_hover_bg_color'],
				'--hh-card-hover-shadow'    => $this->props['card_hover_shadow_color'],
				'--hh-card-name'            => $this->props['card_name_color'],
				'--hh-card-title'           => $this->props['card_title_color'],
			)
		);
	}
}
