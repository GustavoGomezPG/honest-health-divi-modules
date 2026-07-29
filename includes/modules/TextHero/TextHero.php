<?php
/**
 * Text Hero module.
 *
 * The "Our Team" page hero: an eyebrow ("Our Team.") set on a blue banner
 * highlight, a headline, and an intro paragraph over a purple gradient band
 * with a FLAT bottom edge. Every colour it renders is exposed as an editable
 * Divi colour field (Design tab) whose default is the hex extracted from the
 * corresponding node in Figma. The module writes the chosen colours as CSS
 * custom properties inline on its own wrapper; the stylesheet only ever reads
 * them via var(--token, fallback).
 *
 * The authoritative design for this hero is file q1MGpWgDpBoZeS6dgrddjB,
 * node 1:729 -- the file the client supplied directly. It supersedes the
 * older 6LBpKOMFlN8KxaKbut00YW for this section, and it is where the eyebrow
 * highlight colour below was sampled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Text_Hero extends Honest_Divi_Module_Base {

	public $slug = 'honest_text_hero';

	public function init() {
		$this->name             = esc_html__( 'Text Hero', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Content', 'honest-divi-modules' ) ) ),
			'advanced' => array(
				'toggles' => array(
					'eyebrow'     => esc_html__( 'Eyebrow', 'honest-divi-modules' ),
					'headline'    => esc_html__( 'Headline', 'honest-divi-modules' ),
					'body'        => esc_html__( 'Body', 'honest-divi-modules' ),
					'hero_colors' => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color: colour for these elements is owned exclusively by
		// the custom colour fields below (text_color / headline_color),
		// applied as inline CSS custom properties. Divi's font-group
		// builder otherwise auto-generates a native "Text Color" sub-option
		// per group that emits a directly-targeted CSS rule, which would
		// silently beat the inherited custom-property value and defeat the
		// single-source-of-truth colour rule.
		//
		// font_size / letter_spacing / line_height defaults, and the weight
		// carried in `font`'s default, are the values extracted straight off
		// the Figma text nodes (file q1MGpWgDpBoZeS6dgrddjB, hero frame
		// 1:729): eyebrow node 1:203 (56px / 900 / 1em / normal), headline
		// node 1:205 (28px / 600 / 1.45em / normal), body node 1:204 (20px /
		// 400 / 1.45em / normal). `font`'s default is deliberately
		// `|<weight>|||||||` -- an empty first (font-family) segment -- so
		// only font-weight is set and the family keeps inheriting from Divi;
		// see et_builder_set_element_font(), which skips the font-family
		// declaration entirely when that segment is blank. These same
		// numbers are also hard-coded in assets/css/modules.css, because
		// Divi only emits CSS from a font-group field when its stored value
		// differs from this default -- the default alone renders nothing on
		// the front end.
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'eyebrow'  => array(
					'label'          => esc_html__( 'Eyebrow', 'honest-divi-modules' ),
					'css'            => array( 'main' => "{$this->main_css_element} .honest-text-hero__eyebrow" ),
					'toggle_slug'    => 'eyebrow',
					'hide_text_color' => true,
					'font_size'      => array( 'default' => '56px' ),
					'letter_spacing' => array( 'default' => '0px' ),
					'line_height'    => array( 'default' => '1em' ),
					'font'           => array( 'default' => '|900|||||||' ),
				),
				'headline' => array(
					'label'          => esc_html__( 'Headline', 'honest-divi-modules' ),
					'css'            => array( 'main' => "{$this->main_css_element} .honest-text-hero__headline" ),
					'toggle_slug'    => 'headline',
					'hide_text_color' => true,
					'font_size'      => array( 'default' => '28px' ),
					'letter_spacing' => array( 'default' => '0px' ),
					'line_height'    => array( 'default' => '1.45em' ),
					'font'           => array( 'default' => '|600|||||||' ),
				),
				'body'     => array(
					'label'          => esc_html__( 'Body', 'honest-divi-modules' ),
					'css'            => array( 'main' => "{$this->main_css_element} .honest-text-hero__body" ),
					'toggle_slug'    => 'body',
					'hide_text_color' => true,
					'font_size'      => array( 'default' => '20px' ),
					'letter_spacing' => array( 'default' => '0px' ),
					'line_height'    => array( 'default' => '1.45em' ),
					'font'           => array( 'default' => '|400|||||||' ),
				),
			)
		);
	}

	public function get_fields() {
		return array(
			'eyebrow'                 => array(
				'label'           => esc_html__( 'Eyebrow', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Large display text, e.g. "Our Team."', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'headline'                => array(
				'label'           => esc_html__( 'Headline', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content'                 => array(
				'label'           => esc_html__( 'Body', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			// Colour fields. The text defaults are the hexes extracted from
			// the older file 6LBpKOMFlN8KxaKbut00YW -- "Our Team." title
			// frame 229:3001 and intro paragraph 56:404 (white text, bound
			// to the WHITE variable, #ffffff), and headline node 56:406
			// (#c7e4ff).
			//
			// The two gradient defaults below are re-derived from the
			// AUTHORITATIVE file, q1MGpWgDpBoZeS6dgrddjB node 1:198, and
			// both come out unchanged. That node's fill is a single colour
			// at two alphas drawn right-to-left and multiplied over the
			// white page -- rgba(106,76,145,0.67) at the right edge
			// reaching rgb(106,76,145) 63.435% of the way back across.
			// Multiply over white is the identity, so the painted endpoints
			// are rgb(106,76,145) = #6a4c91 on the left and
			// 0.67*(106,76,145) + 0.33*255 = (155,135,181) = #9b87b5 on the
			// right, which is exactly what these defaults already said.
			//
			// What was actually wrong was the STOP GEOMETRY, and it lives in
			// assets/css/modules.css, not here: the start colour holds flat
			// until 36.565% (= 100% - 63.435%) before the ramp begins, and
			// the old declaration ramped from 0%. See the long note on
			// `.honest-text-hero` in that file. These two fields still feed
			// the gradient through `wrap()`'s $css_vars, so editing either
			// colour keeps the design's hold-then-ramp shape.
			'gradient_start_color'    => array(
				'label'        => esc_html__( 'Gradient Start', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background gradient colour at the left edge. Held flat across the first 36.565% of the band before the ramp begins, matching the design.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#6a4c91',
			),
			'gradient_end_color'      => array(
				'label'        => esc_html__( 'Gradient End', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background gradient colour at the right edge.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#9b87b5',
			),
			'text_color'              => array(
				'label'        => esc_html__( 'Eyebrow & Body Text Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the eyebrow and body copy.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#ffffff',
			),
			'headline_color'          => array(
				'label'        => esc_html__( 'Headline Text Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the headline.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#c7e4ff',
			),
			// The banner shape behind the eyebrow. Figma frame 1:199 draws it
			// as two overlapping fills, #6386c8 (node 1:200) and #6985c3
			// (node 1:201); #6985c3 is the larger shape, is what the overlap
			// renders as, and matches the plugin's existing --hh-blue, so the
			// highlight is one colour here rather than two.
			'eyebrow_highlight_color' => array(
				'label'        => esc_html__( 'Eyebrow Highlight Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the banner shape behind the eyebrow.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#6985c3',
			),
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$parts = '';

		// Trimmed before testing, so a whitespace-only field renders nothing
		// at all rather than an empty <p>/<h1> -- the same guard CallToAction
		// and Featured Insights already applied to their own headings.
		$eyebrow  = trim( (string) $this->props['eyebrow'] );
		$headline = trim( (string) $this->props['headline'] );

		// The inner span is what carries the blue banner highlight (its
		// ::before), so it has to shrink-wrap the text -- that is the whole
		// reason the eyebrow is wrapped rather than styled on the <p>. It is
		// only emitted when there is text to sit on, so a blank eyebrow field
		// leaves no floating shape behind.
		if ( '' !== $eyebrow ) {
			$parts .= sprintf(
				'<p class="honest-text-hero__eyebrow"><span class="honest-text-hero__eyebrow-text">%s</span></p>',
				esc_html( $eyebrow )
			);
		}
		if ( '' !== $headline ) {
			$parts .= sprintf( '<h1 class="honest-text-hero__headline">%s</h1>', esc_html( $headline ) );
		}
		if ( '' !== trim( (string) $this->content ) ) {
			$parts .= sprintf( '<div class="honest-text-hero__body">%s</div>', et_core_esc_previously( $this->content ) );
		}

		return $this->wrap(
			$render_slug,
			sprintf( '<div class="honest-text-hero__inner">%s</div>', $parts ),
			array( 'honest-text-hero' ),
			array(
				'--hh-hero-from'               => $this->props['gradient_start_color'],
				'--hh-hero-to'                 => $this->props['gradient_end_color'],
				'--hh-hero-text'               => $this->props['text_color'],
				'--hh-hero-headline'           => $this->props['headline_color'],
				'--hh-hero-eyebrow-highlight' => $this->props['eyebrow_highlight_color'],
			)
		);
	}
}
