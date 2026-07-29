<?php
/**
 * Text Hero module.
 *
 * The "Our Team" page hero: an eyebrow ("Our Team."), a headline, and an
 * intro paragraph over a purple gradient band with a curved white bottom
 * edge. Every colour it renders is exposed as an editable Divi colour field
 * (Design tab) whose default is the hex extracted from the corresponding
 * component in Figma (file 6LBpKOMFlN8KxaKbut00YW). The module writes the
 * chosen colours as CSS custom properties inline on its own wrapper; the
 * stylesheet only ever reads them via var(--token, fallback).
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
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'eyebrow'  => array( 'label' => esc_html__( 'Eyebrow', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-text-hero__eyebrow" ), 'toggle_slug' => 'eyebrow', 'hide_text_color' => true ),
				'headline' => array( 'label' => esc_html__( 'Headline', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-text-hero__headline" ), 'toggle_slug' => 'headline', 'hide_text_color' => true ),
				'body'     => array( 'label' => esc_html__( 'Body', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-text-hero__body" ), 'toggle_slug' => 'body', 'hide_text_color' => true ),
			)
		);
	}

	public function get_fields() {
		return array(
			'eyebrow'              => array(
				'label'           => esc_html__( 'Eyebrow', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Large display text, e.g. "Our Team."', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'headline'             => array(
				'label'           => esc_html__( 'Headline', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content'              => array(
				'label'           => esc_html__( 'Body', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			// Colour fields. Defaults are the hexes extracted from Figma
			// (file 6LBpKOMFlN8KxaKbut00YW): hero background band node
			// 56:401 (gradient), "Our Team." title frame 229:3001 and
			// intro paragraph 56:404 (white text, bound to the WHITE
			// variable, #ffffff), and headline node 56:406 (#c7e4ff).
			'gradient_start_color' => array(
				'label'        => esc_html__( 'Gradient Start', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background gradient colour at the left edge.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#6a4c91',
			),
			'gradient_end_color'   => array(
				'label'        => esc_html__( 'Gradient End', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background gradient colour at the right edge.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#9b87b5',
			),
			'text_color'           => array(
				'label'        => esc_html__( 'Eyebrow & Body Text Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the eyebrow and body copy.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#ffffff',
			),
			'headline_color'       => array(
				'label'        => esc_html__( 'Headline Text Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the headline.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'hero_colors',
				'default'      => '#c7e4ff',
			),
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$parts = '';

		if ( '' !== $this->props['eyebrow'] ) {
			$parts .= sprintf( '<p class="honest-text-hero__eyebrow"><span>%s</span></p>', esc_html( $this->props['eyebrow'] ) );
		}
		if ( '' !== $this->props['headline'] ) {
			$parts .= sprintf( '<h1 class="honest-text-hero__headline">%s</h1>', esc_html( $this->props['headline'] ) );
		}
		if ( '' !== trim( (string) $this->content ) ) {
			$parts .= sprintf( '<div class="honest-text-hero__body">%s</div>', et_core_esc_previously( $this->content ) );
		}

		return $this->wrap(
			$render_slug,
			sprintf( '<div class="honest-text-hero__inner">%s</div>', $parts ),
			array( 'honest-text-hero' ),
			array(
				'--hh-hero-from'     => $this->props['gradient_start_color'],
				'--hh-hero-to'       => $this->props['gradient_end_color'],
				'--hh-hero-text'     => $this->props['text_color'],
				'--hh-hero-headline' => $this->props['headline_color'],
			)
		);
	}
}
