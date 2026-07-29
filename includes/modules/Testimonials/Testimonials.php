<?php
/**
 * Testimonials module.
 *
 * A quote carousel on a coloured band: one slide per Executive Team member
 * who has a non-empty `quote`, each a pull quote plus an attribution line
 * (name and job title), with dot indicators below to jump between slides.
 * Members without a quote are excluded entirely; ordering follows the
 * `executive_team_members` relationship as-is (Open Assumption 3: ordering
 * does not matter here). If no member has a quote, render() returns ''.
 *
 * Every colour it renders is exposed as an editable Divi colour field
 * (Design tab) whose default is the hex extracted from Figma (file
 * 6LBpKOMFlN8KxaKbut00YW) by pixel-sampling the rendered node screenshots:
 * the testimonial block node 50:813 (band background, #6985c3), the quote
 * text node 50:816 and attribution node 50:817 (both solid white), and the
 * dot indicator component node 54:290 (white inactive dot, solid purple
 * active dot). See the task report for the full method and a discrepancy
 * noted against that node's Figma variable bindings. The module writes the
 * chosen colours as CSS custom properties inline on its own wrapper via
 * wrap()'s $css_vars map; modules.css only ever reads them via
 * var(--token, fallback) at the point of use.
 *
 * No autoplay: the brief did not ask for it, and implementing it would have
 * required a visible pause control plus prefers-reduced-motion handling.
 * Slides only change when a visitor operates a dot, by mouse or keyboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Testimonials extends Honest_Divi_Module_Base {

	public $slug = 'honest_testimonials';

	/**
	 * Rendered-instance counter, used only to keep slide/dot ids unique if
	 * more than one instance ends up on a page.
	 *
	 * @var int
	 */
	private static $instances = 0;

	public function init() {
		$this->name             = esc_html__( 'Testimonials', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'advanced' => array(
				'toggles' => array(
					'quote'   => esc_html__( 'Quote', 'honest-divi-modules' ),
					'cite'    => esc_html__( 'Attribution', 'honest-divi-modules' ),
					'colors'  => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color: colour for these two font groups is owned
		// exclusively by the custom colour fields below (quote_color /
		// attribution_color), applied as inline CSS custom properties. Divi's
		// font-group builder otherwise auto-generates a native "Text Color"
		// sub-option per group that emits a directly-targeted CSS rule, which
		// would silently beat the inherited custom-property value and defeat
		// the single-source-of-truth colour rule (see the other modules in
		// this plugin for the same pattern).
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'quote' => array( 'label' => esc_html__( 'Quote', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-testimonials__quote" ), 'toggle_slug' => 'quote', 'hide_text_color' => true ),
				'cite'  => array( 'label' => esc_html__( 'Attribution', 'honest-divi-modules' ), 'css' => array( 'main' => "{$this->main_css_element} .honest-testimonials__cite" ), 'toggle_slug' => 'cite', 'hide_text_color' => true ),
			)
		);
	}

	public function get_fields() {
		return array(
			// Colour fields. Defaults are the hexes extracted from Figma (file
			// 6LBpKOMFlN8KxaKbut00YW) by pixel-sampling rendered screenshots
			// of the listed nodes -- see the task report for the full method:
			//   band background      node 50:813/50:815 -> #6985c3
			//   quote text           node 50:816         -> #ffffff
			//   attribution text     node 50:817         -> #ffffff
			//   dot (inactive)       node 54:290         -> #ffffff
			//   dot (active)         node 54:290         -> #6a4c91
			'bg_color'           => array(
				'label'        => esc_html__( 'Background Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background colour of the testimonial band.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6985c3',
			),
			'quote_color'        => array(
				'label'        => esc_html__( 'Quote Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the pull-quote text.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'attribution_color'  => array(
				'label'        => esc_html__( 'Attribution Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the name and job title beneath each quote.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'dot_color'          => array(
				'label'        => esc_html__( 'Dot Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of an inactive slide dot.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'dot_active_color'   => array(
				'label'        => esc_html__( 'Dot Color (Active)', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the dot for the currently visible slide.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a4c91',
			),
		);
	}

	/**
	 * Slide source: every Executive Team member with a non-empty quote, in
	 * relationship order. Members without a quote are excluded. Ordering is
	 * explicitly not a requirement here (Open Assumption 3) -- relationship
	 * order is used as-is.
	 *
	 * @return array[] Member arrays, shape per honest_team_get_member().
	 */
	protected function get_slides() {
		$slides = array();

		foreach ( honest_team_get_members( honest_team_get_executive_members() ) as $member ) {
			if ( '' === trim( (string) $member['quote'] ) ) {
				continue;
			}
			$slides[] = $member;
		}

		return $slides;
	}

	public function render( $attrs, $content, $render_slug ) {
		$slides = $this->get_slides();

		if ( empty( $slides ) ) {
			return '';
		}

		wp_enqueue_script( 'honest-testimonials' );

		$uid   = 'honest-testimonials-' . ( ++self::$instances );
		$count = count( $slides );

		$slides_html = '';
		$dots_html   = '';

		foreach ( $slides as $i => $member ) {
			$active   = 0 === $i;
			$slide_id = sprintf( '%s-slide-%d', $uid, $i );

			$attribution = trim( (string) $member['name'] );
			$job_title   = trim( (string) $member['job_title'] );
			if ( '' !== $job_title ) {
				$attribution .= ', ' . $job_title;
			}

			/* translators: 1: slide position (1-based), 2: total number of slides. */
			$slide_label = sprintf( __( 'Quote %1$d of %2$d', 'honest-divi-modules' ), $i + 1, $count );

			$slides_html .= sprintf(
				'<div class="honest-testimonials__slide" id="%1$s" role="group" aria-roledescription="%2$s" aria-label="%3$s"%4$s>
					<blockquote class="honest-testimonials__quote">%5$s</blockquote>
					<cite class="honest-testimonials__cite">%6$s</cite>
				</div>',
				esc_attr( $slide_id ),
				esc_attr__( 'slide', 'honest-divi-modules' ),
				esc_attr( $slide_label ),
				$active ? '' : ' hidden',
				esc_html( $member['quote'] ),
				esc_html( $attribution )
			);

			/* translators: 1: slide position (1-based), 2: total number of slides. */
			$dot_label = sprintf( __( 'Show quote %1$d of %2$d', 'honest-divi-modules' ), $i + 1, $count );

			$dots_html .= sprintf(
				'<button type="button" class="honest-testimonials__dot" aria-controls="%1$s" aria-label="%2$s"%3$s></button>',
				esc_attr( $slide_id ),
				esc_attr( $dot_label ),
				$active ? ' aria-current="true"' : ''
			);
		}

		$inner = sprintf(
			'<div class="honest-testimonials__inner"><div class="honest-testimonials__region" role="region" aria-roledescription="carousel" aria-label="%1$s"><div class="honest-testimonials__slides">%2$s</div><div class="honest-testimonials__dots" role="group" aria-label="%3$s">%4$s</div></div></div>',
			esc_attr__( 'Testimonials', 'honest-divi-modules' ),
			$slides_html,
			esc_attr__( 'Choose which quote to display', 'honest-divi-modules' ),
			$dots_html
		);

		return $this->wrap(
			$render_slug,
			$inner,
			array( 'honest-testimonials' ),
			array(
				'--hh-testimonials-bg'          => $this->props['bg_color'],
				'--hh-testimonials-quote'       => $this->props['quote_color'],
				'--hh-testimonials-attribution' => $this->props['attribution_color'],
				'--hh-testimonials-dot'         => $this->props['dot_color'],
				'--hh-testimonials-dot-active'  => $this->props['dot_active_color'],
			)
		);
	}
}
