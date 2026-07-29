<?php
/**
 * Executive Leadership module.
 *
 * Renders the Executive Team members chosen on the Teams settings screen
 * (Teams -> Executive Team) as a grid of member cards, in the exact order
 * set by that ACF relationship field. Every colour it renders is exposed as
 * an editable Divi colour field (Design tab) whose default is the hex
 * extracted from Figma (file 6LBpKOMFlN8KxaKbut00YW): the member card
 * component, node 145:291 (hover example node 224:2431), for the four card
 * colour fields, and this module's own section heading (node 53:390) and
 * intro paragraph (node 223:449) for the other two. The module writes the
 * chosen colours as CSS custom properties inline on its own wrapper; the
 * card-related ones cascade to the shared member card partial rendered
 * inside it, which reads the same custom properties (each default carried in
 * the `var()` fallback at the point of use in modules.css, so the inherited
 * value from this wrapper always wins); the heading/intro ones are read by
 * this module's own CSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Executive_Leadership extends Honest_Divi_Module_Base {

	public $slug = 'honest_executive_leadership';

	public function init() {
		$this->name             = esc_html__( 'Executive Leadership', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Content', 'honest-divi-modules' ) ) ),
			'advanced' => array(
				'toggles' => array(
					'heading'     => esc_html__( 'Heading', 'honest-divi-modules' ),
					'intro'       => esc_html__( 'Intro', 'honest-divi-modules' ),
					'card_colors' => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color: colour for these elements is owned exclusively by
		// the custom colour fields below (heading_color / intro_color),
		// applied as inline CSS custom properties. Divi's font-group builder
		// otherwise auto-generates a native "Text Color" sub-option per
		// group that emits a directly-targeted CSS rule, which would
		// silently beat the inherited custom-property value and defeat the
		// single-source-of-truth colour rule (see Text Hero for the same
		// pattern).
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'heading' => array( 'label' => esc_html__( 'Heading', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-exec__heading" ), 'toggle_slug' => 'heading', 'hide_text_color' => true ),
				'intro'   => array( 'label' => esc_html__( 'Intro', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-exec__intro" ), 'toggle_slug' => 'intro', 'hide_text_color' => true ),
			)
		);
	}

	public function get_fields() {
		return array(
			'heading'              => array(
				'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content'              => array(
				'label'           => esc_html__( 'Intro', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'columns'              => array(
				'label'           => esc_html__( 'Columns', 'honest-divi-modules' ),
				'type'            => 'select',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Number of cards per row on desktop.', 'honest-divi-modules' ),
				'options'         => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'default'         => '4',
				'toggle_slug'     => 'main_content',
			),
			// Heading/intro colour fields. Defaults are the hexes extracted
			// from Figma (file 6LBpKOMFlN8KxaKbut00YW): the section heading
			// node 53:390 ("Executive Leadership Team") and the intro
			// paragraph node 223:449, both solid #1e1e1e text, pixel-sampled
			// from the rendered node screenshots -- see the task report for
			// the full method.
			'heading_color'        => array(
				'label'        => esc_html__( 'Heading Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the section heading.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#1e1e1e',
			),
			'intro_color'          => array(
				'label'        => esc_html__( 'Intro Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the intro copy.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#1e1e1e',
			),
			// Card colour fields. Defaults are the hexes extracted from Figma
			// (file 6LBpKOMFlN8KxaKbut00YW): member card component node
			// 145:291 ("Member Inactive") for the resting-state background
			// (#d2d8ee), name text (#6a4c91), and title text (#1e1e1e); the
			// hover-state example node 224:2431 for the hover background
			// (#6a4c91) and the hover drop-shadow colour (#6985c3). All
			// extracted by pixel-sampling the rendered node screenshots --
			// see the task report for the full method and the discrepancy
			// against the pre-existing (pre-refactor) token values, which
			// this module's defaults intentionally do NOT match.
			//
			// NOTE on the hover state: an earlier report described node
			// 224:2431 as a two-tone horizontal gradient (#6985c3 -> #6a4c91).
			// That was a misreading. Independently re-confirmed here via
			// get_design_context on the node: it returns a FLAT
			// `bg-[#6a4c91]` fill plus a hard offset shadow
			// `shadow-[-8px_8px_0px_0px_#6985c3]` (left 8px, down 8px, no
			// blur, no spread), and a per-pixel scan of the rendered node
			// confirms it: #6985c3 occupies exactly x=0..7 and y=16..394 (an
			// 8px band, offset down by 8px), with the card body a uniform
			// #6a4c91 from x=8 to x=296. There is no gradient ramp. The name
			// and job-title text also switch to solid white on hover in this
			// node (`text-white`), matching the existing CSS. The flat hover
			// colour and shadow colour below therefore match Figma as-is; the
			// literal `-8px 8px 0 0` offsets live in modules.css and only the
			// two colours travel as custom properties.
			'card_bg_color'        => array(
				'label'        => esc_html__( 'Card Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background colour of each member card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#d2d8ee',
			),
			'card_hover_bg_color'  => array(
				'label'        => esc_html__( 'Card Background (Hover)', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background colour of a member card on hover.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#6a4c91',
			),
			'card_hover_shadow_color' => array(
				'label'        => esc_html__( 'Card Shadow (Hover)', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the hard offset drop-shadow behind a member card on hover.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#6985c3',
			),
			'card_name_color'      => array(
				'label'        => esc_html__( 'Card Name Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the member name on each card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#6a4c91',
			),
			'card_title_color'     => array(
				'label'        => esc_html__( 'Card Title Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the member job title on each card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#1e1e1e',
			),
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$members = honest_team_get_members( honest_team_get_executive_members() );

		if ( empty( $members ) ) {
			return '';
		}

		$cards      = '';
		$card_index = 0;
		foreach ( $members as $member ) {
			$cards .= honest_team_render_member_card( $member, $card_index++ );
		}

		$header = '';
		// Trimmed before testing, so a whitespace-only heading renders nothing
		// rather than an empty <h2> -- consistent with every other module here.
		$heading = trim( (string) $this->props['heading'] );
		if ( '' !== $heading ) {
			$header .= sprintf( '<h2 class="honest-exec__heading">%s</h2>', esc_html( $heading ) );
		}
		if ( '' !== trim( (string) $this->content ) ) {
			$header .= sprintf( '<div class="honest-exec__intro">%s</div>', et_core_esc_previously( $this->content ) );
		}

		// No inner wrapper: the module renders at 100% width and Divi's Row is
		// the page container, so there is nothing for one to do -- see rule 4
		// in assets/css/modules.css's header.
		$inner = sprintf(
			'%1$s<div class="honest-exec__grid honest-exec__grid--%2$s">%3$s</div>',
			$header,
			esc_attr( $this->props['columns'] ),
			$cards
		);

		return $this->wrap(
			$render_slug,
			$inner,
			array( 'honest-exec' ),
			array(
				'--hh-exec-heading'      => $this->props['heading_color'],
				'--hh-exec-intro'        => $this->props['intro_color'],
				'--hh-card-bg'           => $this->props['card_bg_color'],
				'--hh-card-hover-bg'     => $this->props['card_hover_bg_color'],
				'--hh-card-hover-shadow' => $this->props['card_hover_shadow_color'],
				'--hh-card-name'         => $this->props['card_name_color'],
				'--hh-card-title'        => $this->props['card_title_color'],
			)
		);
	}
}
