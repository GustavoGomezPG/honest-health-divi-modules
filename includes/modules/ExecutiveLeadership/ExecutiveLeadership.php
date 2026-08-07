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

	/**
	 * Full builder compatibility; component in assets/js/vb-modules.js under this
	 * slug. The card grid reaches the builder as server-rendered HTML through the
	 * `__cards` computed property below rather than being re-implemented in
	 * JavaScript, so the markup has exactly one source: get_cards_html().
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	/**
	 * The member cards, rendered server-side.
	 *
	 * Shared by render() and by the `__cards` computed property, so the builder
	 * and the front end cannot drift. The animation classes differ between the
	 * two automatically and correctly: honest_team_animation_attrs() withholds
	 * them for builder renders, and the computed property is delivered by the
	 * et_pb_process_computed_property action that honest_team_is_builder_render()
	 * recognises -- so the front end animates and the builder shows the cards
	 * outright instead of stranding them at opacity 0.
	 *
	 * Static because Divi calls computed callbacks as plain callables, with no
	 * module instance: call_user_func( $callback, $depends_on, $conditional_tags,
	 * $current_page ). It needs no props -- the roster comes from the Teams
	 * settings screen, not from the module.
	 *
	 * @return string
	 */
	public static function get_cards_html() {
		$members = honest_team_get_members( honest_team_get_executive_members() );

		if ( empty( $members ) ) {
			return '';
		}

		$cards      = '';
		$card_index = 0;

		foreach ( $members as $member ) {
			$cards .= honest_team_render_member_card( $member, $card_index++ );
		}

		return $cards;
	}

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
				// font_size / line_height / weight defaults are read off the Figma
				// text nodes in file q1MGpWgDpBoZeS6dgrddjB, section 1:101:
				// heading 1:103 (48px / 700 / 1.27) and intro 1:104 (18px / 400 /
				// 1.45). `font`'s default keeps an empty first segment so only the
				// weight is set and the family goes on inheriting from Divi. The
				// same numbers appear in assets/css/modules.css, which is the
				// floor -- a default alone emits no CSS for an already-saved
				// instance.
				'heading' => array(
					'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-exec__heading" ),
					'toggle_slug'     => 'heading',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '48px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'line_height'     => array( 'default' => '1.27em' ),
					'font'            => array( 'default' => '|700|||||||' ),
				),
				'intro'   => array(
					'label'           => esc_html__( 'Intro', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-exec__intro" ),
					'toggle_slug'     => 'intro',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '18px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'line_height'     => array( 'default' => '1.45em' ),
					'font'            => array( 'default' => '|400|||||||' ),
				),
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
			// Delivers the card grid's HTML to the builder's React component.
			// `columns` is the only module prop the grid depends on, and only for
			// its wrapper class -- which the component renders itself -- so this
			// re-fetches rarely. The roster itself lives in the Teams settings
			// screen, outside the builder entirely, so a change there shows up on
			// the next builder load rather than instantly; that is the correct
			// trade for not duplicating the card markup in JavaScript.
			'__cards'              => array(
				'type'                => 'computed',
				'computed_callback'   => array( 'Honest_Divi_Module_Executive_Leadership', 'get_cards_html' ),
				'computed_depends_on' => array( 'columns' ),
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
			// Card colour fields. Defaults are the values extracted from Figma
			// (file 6LBpKOMFlN8KxaKbut00YW). The redesigned member card is node
			// 414:796 ("Member Inactive"), which supersedes the 145:291 this
			// block used to cite: the resting background is no longer the solid
			// #d2d8ee but that same lavender at 38% -- `rgba(210,216,238,0.38)`
			// -- because the portrait is now a transparent cutout sitting ON the
			// card rather than a ringed photo inset into it, and the lighter
			// wash is what keeps the cutout's edges from reading as a hard box.
			// Name text (#6a4c91) and title text (#1e1e1e) are unchanged. The
			// rule between portrait and text (#b9c4ed) is new with the redesign
			// (node 414:801) and is exposed here for the same reason as every
			// other colour: nothing this module paints may be hardcoded.
			//
			// NOTE on the hover state: an earlier report described node
			// 224:2431 as a two-tone horizontal gradient (#6985c3 -> #6a4c91).
			// That was a misreading. Independently re-confirmed via
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
			//
			// The redesign's hover counterpart was NOT re-exported (the design
			// file only ships the "Member Inactive" state), so the hover
			// treatment is carried over as-is and the rule keeps its resting
			// colour underneath it.
			'card_bg_color'        => array(
				'label'        => esc_html__( 'Card Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background colour of each member card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => 'rgba(210,216,238,0.38)',
			),
			'card_rule_color'      => array(
				'label'        => esc_html__( 'Card Divider', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the rule between the portrait and the name on each member card.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'card_colors',
				'default'      => '#b9c4ed',
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
		$cards = self::get_cards_html();

		if ( '' === $cards ) {
			return '';
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
				'--hh-card-rule'         => $this->props['card_rule_color'],
				'--hh-card-hover-bg'     => $this->props['card_hover_bg_color'],
				'--hh-card-hover-shadow' => $this->props['card_hover_shadow_color'],
				'--hh-card-name'         => $this->props['card_name_color'],
				'--hh-card-title'        => $this->props['card_title_color'],
			)
		);
	}
}
