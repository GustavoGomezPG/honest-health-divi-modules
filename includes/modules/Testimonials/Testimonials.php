<?php
/**
 * Testimonials module.
 *
 * A quote carousel on a coloured band: one slide per row of the Quote Carousel
 * screen under Teams, each a pull quote plus an attribution line (name and job
 * title), with dot indicators below to jump between slides.
 *
 * The rows are a curated list, not a derived one. This used to render every
 * Executive Team member who happened to carry a `quote`, which cannot express
 * the actual carousel: it mixes executives with market leaders, and several of
 * those people are quoted here with different words than the pull quote on
 * their own page. A row supplies its own text and falls back to the member's
 * pull quote when left blank -- see honest_team_get_carousel_quotes().
 *
 * If the carousel has no usable rows, render() returns ''.
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

	/**
	 * Full builder compatibility; component in assets/js/vb-modules.js under this
	 * slug. The slides and dots reach the builder as server-rendered HTML through
	 * the `__testimonials` computed property, so the quote markup and the ARIA
	 * wiring that ties each dot to its slide exist only in PHP.
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	public function init() {
		$this->name             = esc_html__( 'Testimonials', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			// Declared for parity with the other seven modules, all of which
			// declare the General tab their main_content fields sit on. This
			// module's are playback controls rather than editor copy, which is
			// how it came to be the one that did not.
			//
			// NOT a bug fix -- the three controls below were always reachable.
			// Divi backfills a missing toggle definition for third-party
			// modules: ET_Builder_Element::get_toggles() borrows an existing
			// definition of the same slug from any other registered module
			// (class-et-builder-element.php:18895-18933), and get_options()
			// writes it back into settings_modal_toggles (:12692-12697).
			// Verified on Divi 4.27.7 by registering a module with the old
			// shape: the lookup still resolved.
			//
			// Worth declaring anyway. It drops a dependency on undocumented
			// self-healing and on some other module happening to define
			// 'main_content', and it pins the toggle's title to this plugin's
			// own text domain instead of whichever module Divi borrowed from.
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Content', 'honest-divi-modules' ),
				),
			),
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
			// The band background that used to live here is gone: the module no
			// longer paints one. The surrounding Divi Section owns the background
			// (a half-white / half-purple gradient on the Our Team page), so a
			// module-level colour would paint over it. The field went with it
			// rather than being left as a colour picker that changes nothing.
			//   quote text           node 50:816         -> #ffffff
			//   attribution text     node 50:817         -> #ffffff
			//   dot (inactive)       node 54:290         -> #ffffff
			//   dot (active)         node 54:290         -> #6a4c91
			// Playback. Autoplay defaults on because the section is a passive
			// quote rotator, but it yields to the visitor: it pauses on hover and
			// on keyboard focus, stops while the tab is in the background, and
			// restarts the clock whenever a dot is used so a chosen quote gets a
			// full reading interval rather than the remainder of the last one.
			// prefers-reduced-motion disables it outright.
			// Delivers the slides and dots to the builder's React component. Reads
			// no props -- the quotes come from the Teams settings screen -- but a
			// computed property needs a dependency to be re-fetched at all, and
			// autoplay is the cheapest honest one: it costs a re-render that was
			// happening anyway when playback settings change.
			'__testimonials'     => array(
				'type'                => 'computed',
				'computed_callback'   => array( 'Honest_Divi_Module_Testimonials', 'get_carousel_parts' ),
				'computed_depends_on' => array( 'autoplay' ),
			),
			'autoplay'           => array(
				'label'           => esc_html__( 'Autoplay', 'honest-divi-modules' ),
				'description'     => esc_html__( 'Advance the quotes automatically. Pauses while a visitor hovers or focuses the carousel, and is disabled for visitors who ask for reduced motion.', 'honest-divi-modules' ),
				'type'            => 'yes_no_button',
				'option_category' => 'configuration',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'honest-divi-modules' ),
					'off' => esc_html__( 'No', 'honest-divi-modules' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			// Seconds rather than milliseconds: this is a reading interval, and an
			// editor picking how long a quote stays up thinks in seconds. Floor of
			// 2s because anything quicker cannot be read; re-validated in render()
			// because a raw shortcode edit bypasses this UI entirely.
			'slide_duration'     => array(
				'label'           => esc_html__( 'Slide Duration (seconds)', 'honest-divi-modules' ),
				'description'     => esc_html__( 'How long each quote stays on screen before the next one fades in.', 'honest-divi-modules' ),
				'type'            => 'range',
				'option_category' => 'configuration',
				'default'         => '6',
				'unitless'        => true,
				'range_settings'  => array(
					'min'  => '2',
					'max'  => '20',
					'step' => '0.5',
				),
				'toggle_slug'     => 'main_content',
				'show_if'         => array( 'autoplay' => 'on' ),
			),
			// Milliseconds here, because a crossfade is not a thing anyone counts
			// in seconds. 0 is allowed and means an instant cut.
			'fade_duration'      => array(
				'label'           => esc_html__( 'Fade Duration (ms)', 'honest-divi-modules' ),
				'description'     => esc_html__( 'Length of the crossfade between quotes. Set to 0 for an instant change.', 'honest-divi-modules' ),
				'type'            => 'range',
				'option_category' => 'configuration',
				'default'         => '400',
				'unitless'        => true,
				'range_settings'  => array(
					'min'  => '0',
					'max'  => '2000',
					'step' => '50',
				),
				'toggle_slug'     => 'main_content',
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
	 * The slides and their dots, rendered server-side.
	 *
	 * Shared by render() and by the `__testimonials` computed property so the
	 * builder and the front end cannot drift.
	 *
	 * Returned as two strings rather than one block because they are separate
	 * children of the carousel region, and the React component builds that region
	 * itself in order to keep the playback settings on it prop-driven -- autoplay
	 * and the durations are plain attributes, and routing them through a
	 * server round-trip would make every nudge of a slider wait on AJAX.
	 *
	 * Static because Divi calls computed callbacks as plain callables with no
	 * module instance. It reads no props: the slides come from the Quote Carousel
	 * screen under Teams.
	 *
	 * Same caveat as the market map's ids -- $uid comes from a per-request
	 * counter, so two copies of this module on one page would collide in the
	 * builder preview, where each module's computed value is fetched in its own
	 * request. The front end renders both in one request and is unaffected.
	 *
	 * @return array{slides:string,dots:string} Empty array when the carousel is unset.
	 */
	public static function get_carousel_parts() {
		$slides = honest_team_get_carousel_quotes();

		if ( empty( $slides ) ) {
			return array();
		}

		$uid   = 'honest-testimonials-' . ( ++self::$instances );
		$count = count( $slides );

		$slides_html = '';
		$dots_html   = '';

		foreach ( $slides as $i => $slide ) {
			$member   = $slide['member'];
			$active   = 0 === $i;
			$slide_id = sprintf( '%s-slide-%d', $uid, $i );

			$attribution = trim( (string) $member['name'] );
			$job_title   = trim( (string) $member['job_title'] );
			if ( '' !== $job_title ) {
				$attribution .= ', ' . $job_title;
			}

			/* translators: 1: slide position (1-based), 2: total number of slides. */
			$slide_label = sprintf( __( 'Quote %1$d of %2$d', 'honest-divi-modules' ), $i + 1, $count );

			// `is-current` rather than the `hidden` attribute: hidden means
			// display:none, which cannot crossfade. The stylesheet keeps a
			// non-current slide invisible and out of the accessibility tree via
			// visibility, so the first slide is still the only one exposed even
			// with no JavaScript at all.
			$slides_html .= sprintf(
				'<div class="honest-testimonials__slide%4$s" id="%1$s" role="group" aria-roledescription="%2$s" aria-label="%3$s">
					<blockquote class="honest-testimonials__quote">%5$s</blockquote>
					<cite class="honest-testimonials__cite">%6$s</cite>
				</div>',
				esc_attr( $slide_id ),
				esc_attr__( 'slide', 'honest-divi-modules' ),
				esc_attr( $slide_label ),
				$active ? ' is-current' : '',
				esc_html( $slide['quote'] ),
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

		return array(
			'slides' => $slides_html,
			'dots'   => $dots_html,
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$parts = self::get_carousel_parts();

		if ( empty( $parts ) ) {
			return '';
		}

		wp_enqueue_script( 'honest-testimonials' );

		// Both durations are re-validated against the same bounds the fields
		// advertise, not merely for being numeric: a raw shortcode edit or a
		// value saved before these fields existed bypasses the builder UI, and a
		// slide duration of 0 would advance the carousel every tick. Out-of-range
		// values fall back to the documented default rather than being snapped to
		// a bound, so a nonsense number behaves normally instead of surprisingly.
		$seconds = isset( $this->props['slide_duration'] ) ? (float) $this->props['slide_duration'] : 6.0;
		if ( ! is_finite( $seconds ) || $seconds < 2.0 || $seconds > 20.0 ) {
			$seconds = 6.0;
		}

		$fade = isset( $this->props['fade_duration'] ) ? (float) $this->props['fade_duration'] : 400.0;
		if ( ! is_finite( $fade ) || $fade < 0.0 || $fade > 2000.0 ) {
			$fade = 400.0;
		}

		$autoplay = 'off' === $this->props['autoplay'] ? 'off' : 'on';

		$inner = sprintf(
			'<div class="honest-testimonials__inner"><div class="honest-testimonials__region" role="region" aria-roledescription="carousel" aria-label="%1$s" data-autoplay="%5$s" data-slide-duration="%6$d"><div class="honest-testimonials__slides">%2$s</div><div class="honest-testimonials__dots" role="group" aria-label="%3$s">%4$s</div></div></div>',
			esc_attr__( 'Testimonials', 'honest-divi-modules' ),
			$parts['slides'],
			esc_attr__( 'Choose which quote to display', 'honest-divi-modules' ),
			$parts['dots'],
			esc_attr( $autoplay ),
			(int) round( $seconds * 1000 )
		);

		return $this->wrap(
			$render_slug,
			$inner,
			array( 'honest-testimonials' ),
			array(
				'--hh-testimonials-quote'       => $this->props['quote_color'],
				'--hh-testimonials-attribution' => $this->props['attribution_color'],
				'--hh-testimonials-dot'         => $this->props['dot_color'],
				'--hh-testimonials-dot-active'  => $this->props['dot_active_color'],
				'--hh-testimonials-fade'        => array( 'ms' => $fade ),
			)
		);
	}
}
