<?php
/**
 * Member Statement module.
 *
 * The band on a team member's page carrying their pull quote and their "Why
 * Statement" side by side. Figma nodes 594:224 (both columns) and 594:225 (one
 * column only), file 6LBpKOMFlN8KxaKbut00YW.
 *
 * The quote used to be rendered by Team Member Header, immediately under the
 * bio. It moved here because the design gives it its own band with its own
 * background, and because it now has a sibling: a member can have a quote, a why
 * statement, both, or neither, and only a module that owns both can decide how
 * to lay them out. The `quote` meta itself did not move, so the testimonial
 * carousel that also reads it is unaffected.
 *
 * Three states, all driven by content rather than by a setting:
 *
 *   both     two columns, 41.7% / 46.5% with the design's 11.7% gutter between.
 *   one only that column alone, capped at 875px and centred (node 594:225 draws
 *            the quote at 874.3 wide).
 *   neither  nothing at all -- no band, no padding. Consistent with every other
 *            module here, which stays silent rather than painting an empty
 *            section.
 *
 * Every colour is an editable Divi colour field whose default is the value
 * sampled from the corresponding Figma node, written onto this module's own
 * wrapper as a CSS custom property; modules.css only ever reads
 * var(--token, fallback). See the file header in assets/css/modules.css.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Member_Statement extends Honest_Divi_Module_Base {

	public $slug = 'honest_member_statement';

	/**
	 * Full builder compatibility; the component lives in assets/js/vb-modules.js
	 * under this slug. Like Team Member Header, the body is delivered to the
	 * builder as server-rendered HTML through the `__body` computed property
	 * rather than reimplemented in JavaScript -- the markup depends on which of
	 * the two fields the member in context actually has, and duplicating that
	 * decision in two languages is how the two drift apart.
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	/**
	 * The band's contents, rendered server-side.
	 *
	 * Static because Divi calls computed callbacks as plain callables, with no
	 * module instance.
	 *
	 * @param array $args             label.
	 * @param array $conditional_tags Unused; part of Divi's callback signature.
	 * @param array $current_page     Divi's page context. Preferred over
	 *                                get_the_ID() because during a
	 *                                computed-property request the queried object
	 *                                is not the member being previewed.
	 * @return string Empty when there is no member, or when the member has
	 *                neither a quote nor a why statement.
	 */
	public static function get_body_html( $args = array(), $conditional_tags = array(), $current_page = array() ) {
		$member_id = ! empty( $current_page['id'] ) ? (int) $current_page['id'] : (int) get_the_ID();
		$member    = honest_team_get_member( $member_id );

		// Same reasoning as Team Member Header: inside the Theme Builder there is
		// no member in context, so the template could not be laid out at all
		// without standing one in. On the front end an unresolved member means
		// this module genuinely has nothing to say and must stay silent.
		if ( ! $member && honest_team_is_builder_render() ) {
			$member = honest_team_get_member( honest_team_get_preview_member_id() );
		}

		if ( ! $member ) {
			return '';
		}

		// Intro animation, staggered in reading order: quote, then the why
		// column's label and body. The starting index is decided here rather than
		// fixed per element so a band carrying only the why statement still begins
		// at zero instead of idling through a delay for a quote that is absent.
		// honest_team_animation_attrs() withholds the whole thing in the builder.
		$quote = self::quote_html( $member['quote'], 0 );
		$why   = self::why_html( $member['why'], $args, '' === $quote ? 0 : 1 );

		if ( '' === $quote && '' === $why ) {
			return '';
		}

		// The modifier is what switches the grid between two columns and one
		// centred, capped column. Deciding it here rather than in CSS
		// (:only-child would nearly do it) keeps the 875px cap off the two-column
		// case, where it would fight the percentage tracks.
		$single = ( '' === $quote || '' === $why ) ? ' honest-statement__grid--single' : '';

		return sprintf(
			'<div class="honest-statement__grid%3$s">%1$s%2$s</div>',
			$quote,
			$why,
			$single
		);
	}

	/**
	 * The pull quote.
	 *
	 * Curly quotes are drawn by modules.css via `open-quote`/`close-quote`, so any
	 * an editor typed are stripped from the ends first -- otherwise a value pasted
	 * from a document that already carried them renders double-quoted. This is the
	 * same normalisation Team Member Header applied while it owned the quote.
	 *
	 * @param string $raw  Stored `quote` meta.
	 * @param int    $step Zero-based position in the band's intro stagger.
	 * @return string
	 */
	private static function quote_html( $raw, $step = 0 ) {
		$text = trim( (string) $raw );
		$text = preg_replace( '/^[\x{0022}\x{0027}\x{2018}\x{2019}\x{201C}\x{201D}]+|[\x{0022}\x{0027}\x{2018}\x{2019}\x{201C}\x{201D}]+$/u', '', $text );
		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		$anim = honest_team_animation_attrs( $step );

		return sprintf(
			'<blockquote class="honest-statement__quote%2$s"%3$s>%1$s</blockquote>',
			esc_html( $text ),
			$anim['class'],
			$anim['style']
		);
	}

	/**
	 * The why statement, with its labelled banner.
	 *
	 * The value is editor HTML from a wysiwyg field, so it is filtered with
	 * wp_kses_post() rather than escaped -- escaping would print the markup.
	 * `strip_tags()` before the emptiness test because an "empty" wysiwyg often
	 * still holds `<p>&nbsp;</p>`, which would otherwise paint a labelled banner
	 * over nothing.
	 *
	 * @param string $raw  Stored `why_statement` meta.
	 * @param array  $args Module args carrying the label.
	 * @param int    $step Zero-based position in the band's intro stagger. The
	 *                     label takes it and the body the one after, so the
	 *                     banner lands before the prose it titles.
	 * @return string
	 */
	private static function why_html( $raw, $args = array(), $step = 0 ) {
		$html = (string) $raw;

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return '';
		}

		$label = trim( (string) ( isset( $args['label'] ) ? $args['label'] : '' ) );
		if ( '' === $label ) {
			$label = esc_html__( 'Why Statement', 'honest-divi-modules' );
		}

		$anim_label = honest_team_animation_attrs( $step );
		$anim_body  = honest_team_animation_attrs( $step + 1 );

		// The column itself is not animated, only its two children: the wrapper is
		// a grid track, and hiding it would take the track's height with it and
		// reflow the band as it revealed.
		//
		// The inner span carries the banner shape as its ::before, so it has to
		// shrink-wrap the label -- exactly why the Text Hero wraps its eyebrow the
		// same way.
		return sprintf(
			'<div class="honest-statement__why">
				<p class="honest-statement__label%3$s"%4$s><span class="honest-statement__label-text">%1$s</span></p>
				<div class="honest-statement__why-body%5$s"%6$s>%2$s</div>
			</div>',
			esc_html( $label ),
			wp_kses_post( $html ),
			$anim_label['class'],
			$anim_label['style'],
			$anim_body['class'],
			$anim_body['style']
		);
	}

	public function init() {
		$this->name             = esc_html__( 'Member Statement', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Content', 'honest-divi-modules' ) ) ),
			'advanced' => array(
				'toggles' => array(
					'quote'  => esc_html__( 'Quote', 'honest-divi-modules' ),
					'why'    => esc_html__( 'Why Statement', 'honest-divi-modules' ),
					'colors' => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color on every group: colour here belongs exclusively to the
		// custom colour fields below, applied as inline custom properties. Divi's
		// font-group builder otherwise generates a native "Text Color" sub-option
		// that emits a directly-targeted rule and silently beats the inherited
		// value -- the same trap Text Hero and Executive Leadership document.
		//
		// The sizes are the Figma text nodes: quote 40px/800 at 1.27 (node 594:224
		// and again at 594:225), label 28px/800 at 1.22, body 16px/400 at 1.45.
		// `font`'s default keeps an empty first segment so only the weight is set
		// and the family goes on inheriting from Divi. These same numbers are also
		// declared in modules.css, because Divi emits nothing from a font group
		// whose stored value still equals its default.
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				'quote'     => array(
					'label'          => esc_html__( 'Quote', 'honest-divi-modules' ),
					'css'            => array( 'main' => '%%order_class%% .honest-statement__quote' ),
					'hide_text_color' => true,
					'font_size'      => array( 'default' => '40px' ),
					'line_height'    => array( 'default' => '1.27em' ),
					'letter_spacing' => array( 'default' => 'normal' ),
					'font'           => array( 'default' => '|800|||||||' ),
					'toggle_slug'    => 'quote',
				),
				'why_label' => array(
					'label'          => esc_html__( 'Why Statement Label', 'honest-divi-modules' ),
					'css'            => array( 'main' => '%%order_class%% .honest-statement__label' ),
					'hide_text_color' => true,
					'font_size'      => array( 'default' => '28px' ),
					'line_height'    => array( 'default' => '1.22em' ),
					'letter_spacing' => array( 'default' => 'normal' ),
					'font'           => array( 'default' => '|800|||||||' ),
					'toggle_slug'    => 'why',
				),
				'why_body'  => array(
					'label'          => esc_html__( 'Why Statement Body', 'honest-divi-modules' ),
					'css'            => array( 'main' => '%%order_class%% .honest-statement__why-body' ),
					'hide_text_color' => true,
					'font_size'      => array( 'default' => '16px' ),
					'line_height'    => array( 'default' => '1.45em' ),
					'letter_spacing' => array( 'default' => 'normal' ),
					'font'           => array( 'default' => '|400|||||||' ),
					'toggle_slug'    => 'why',
				),
			)
		);
	}

	public function get_fields() {
		return array(
			'label'            => array(
				'label'           => esc_html__( 'Why Statement Label', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Text on the banner above the why statement. Defaults to "Why Statement".', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
				'default'         => 'Why Statement',
			),
			// Colours sampled from Figma node 594:224: the band is #dad4e3 at 65%
			// (node "Rectangle 91"), the quote #6a4c91, the label white on a banner
			// drawn as two overlapping fills -- #6386c8 and the larger #6985c3,
			// which is what the overlap renders as and is this plugin's existing
			// --hh-blue, so the banner is one editable colour rather than two --
			// and the why body #070707.
			'band_color'       => array(
				'label'        => esc_html__( 'Band Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Background of the full-width band behind both columns.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => 'rgba(218,212,227,0.65)',
			),
			'quote_color'      => array(
				'label'        => esc_html__( 'Quote Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the pull quote.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a4c91',
			),
			'label_color'      => array(
				'label'        => esc_html__( 'Label Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the label text on the banner.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			'banner_color'     => array(
				'label'        => esc_html__( 'Label Banner Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the banner shape behind the label.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6985c3',
			),
			'why_color'        => array(
				'label'        => esc_html__( 'Why Statement Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the why statement copy.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#070707',
			),
			// Mirrors the body to the builder. Divi re-requests this whenever a
			// value in `depends_on` changes, which is what makes editing the label
			// update the banner live.
			'__body'           => array(
				'type'                => 'computed',
				'computed_callback'   => array( 'Honest_Divi_Module_Member_Statement', 'get_body_html' ),
				'computed_depends_on' => array( 'label' ),
				'computed_minimum'    => array(),
			),
		);
	}

	public function render( $attrs, $content, $render_slug ) {
		$body = self::get_body_html(
			array( 'label' => $this->props['label'] ),
			array(),
			array( 'id' => (int) get_the_ID() )
		);

		if ( '' === $body ) {
			return '';
		}

		return $this->wrap(
			$render_slug,
			sprintf( '<div class="honest-statement__inner">%s</div>', $body ),
			array( 'honest-statement' ),
			array(
				'--hh-statement-band'   => $this->props['band_color'],
				'--hh-statement-quote'  => $this->props['quote_color'],
				'--hh-statement-label'  => $this->props['label_color'],
				'--hh-statement-banner' => $this->props['banner_color'],
				'--hh-statement-why'    => $this->props['why_color'],
			)
		);
	}
}
