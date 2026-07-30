<?php
/**
 * Call To Action module.
 *
 * A full-bleed band -- a background image, a gradient overlay, and content
 * (heading, body copy, button) constrained to one side, per the `alignment`
 * field (left|right, default right). It appears in two places with
 * identical styling and layout, differing only in copy:
 *
 * - The "Our Team" page (Figma frame 50:470): background image node 55:311,
 *   overlay node 55:314, heading node 224:2325 ("About Page Callout" --
 *   template/wireframe-style copy, not a hint to keep it literally), intro
 *   paragraph node 55:320 (lorem ipsum placeholder copy), button node 55:317
 *   with label node 55:319 ("Learn More").
 * - The single team-member page (Figma frame 224:2532): background node
 *   224:2667, overlay node 224:2668, heading node 224:2669, paragraph node
 *   224:2673, button node 224:2670 with label node 224:2672 ("About Honest
 *   Health").
 *
 * Both instances were rendered (get_screenshot) and re-confirmed via
 * get_design_context before anything was extracted from them, per the
 * lesson from two earlier tasks in this plugin that were burned by
 * wireframe scaffolding sitting alongside finished design in this same
 * Figma file. Both instances here checked out as real and identical:
 *
 *   - Neither is `hidden` in the metadata tree (unlike a nearby, genuinely
 *     hidden pair -- nodes 56:389/56:390 on the Our Team page -- which this
 *     module does NOT use).
 *   - Both background nodes (55:311, 224:2667) render the same real stock
 *     photo (a clinician and an older patient), not a placeholder fill or a
 *     grey box.
 *   - Both overlay nodes (55:314, 224:2668) resolve, via get_design_context,
 *     to the exact same fill on both instances:
 *     `linear-gradient(89.69deg, rgba(184,200,231,0) 0%, rgb(184,200,231) 99.9%)`
 *     -- i.e. one hue (#b8c8e7) fading from fully transparent to fully
 *     opaque, left to right. That direction is why `alignment` defaults to
 *     `right`: the content side is the opaque end, giving the heading/body
 *     text a legible light backdrop, while the image reads clearly through
 *     the transparent end on the opposite side.
 *   - Both heading nodes (224:2325, 224:2669) resolve to the same colour --
 *     `var(--hh-blue-shade, #3a61b6)` -- and both paragraph nodes (55:320,
 *     224:2673) to the same `var(--dark-grey, #6a8090)`. The button is
 *     identical on both instances too: a solid black fill
 *     (`var(--black, black)`) with white text (`var(--white, white)`).
 *   - The visible copy ("About Page Callout", the lorem ipsum paragraph,
 *     "Learn More") reads exactly like unfinished placeholder text, but the
 *     two nodes' *fills* and *structure* are what this module's defaults are
 *     built from, not the placeholder wording -- the wording is left to
 *     whoever fills in the module's actual fields.
 *
 * This module owns every colour it renders, all applied as inline CSS custom
 * properties via wrap()'s $css_vars map (see Honest_Divi_Module_Base):
 * heading_color, content_color, button_bg_color, button_label_color,
 * overlay_color (the gradient's opaque end -- a single editable colour,
 * since Divi's colour field can only hold one value; the transparent end is
 * fixed in modules.css because a Figma-real 0%-alpha stop of the same hue is
 * not itself a meaningful separate colour to expose), and cta_bg_color
 * (a plain solid fill beneath the image+overlay, visible only when no
 * background image is chosen, so an empty upload field never produces a
 * broken or empty `background-image` -- see get_background_image_url()).
 *
 * The image field is named `cta_image`, and the solid fallback fill
 * `cta_bg_color`, rather than the more obvious `background_image` /
 * `background_color`: those two names are already reserved by Divi's own
 * native "Background" advanced option, which base_advanced_fields() enables
 * for every module in this plugin (the 'background' => array() entry).
 * Naming this module's fields the same reused Divi's own prop names for a
 * second, unrelated purpose -- confirmed by testing, not just by reading the
 * source: Divi silently wrote its own competing `background-image` CSS rule
 * straight onto the module's order-class from the very same shortcode
 * attribute. `cta_image`/`cta_bg_color` sidestep that collision entirely;
 * Divi's own native Background toggle is still present in the Design tab
 * (base_advanced_fields() does not turn it off) but is simply left unused.
 *
 * A second, subtler instance of the same class of collision: Divi's
 * font-group generator auto-creates an `"{$option_name}_text_color"` prop for
 * every font group unless that group sets `hide_text_color => true`
 * (class-et-builder-element.php, ET_Builder_Element::generate_font_options()).
 * This module's own `button` font group would therefore generate
 * `button_text_color` -- which is why the button label colour field is named
 * `button_label_color`, not the more obvious `button_text_color`: the latter
 * would be an exact match for Divi's own auto-generated prop name for that
 * same font group. See the `hide_text_color` comment at the `button` font
 * group definition in init() for why this is currently harmless (that flag
 * suppresses Divi's auto-generated prop entirely) but was still worth
 * renaming away from rather than leaving as a same-name coincidence that
 * only doesn't collide because of a flag set for an unrelated reason.
 *
 * Contrast: the heading (#3a61b6, large/extrabold) against the overlay's
 * opaque end (#b8c8e7) computes to 3.49:1, clearing WCAG AA's 3:1 threshold
 * for large text. The body copy (#6a8090, ~23px, not "large" per WCAG)
 * against the same background computes to ~2.44:1, well short of the 4.5:1
 * normal-text minimum. This is a real property of the Figma design's own
 * colour choices, faithfully reproduced here, not a defect introduced by
 * this implementation -- flagged per the brief rather than silently shipped.
 * Both colours remain fully editable per-instance if a real deployment needs
 * to raise that contrast.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Honest_Divi_Module_Call_To_Action extends Honest_Divi_Module_Base {

	public $slug = 'honest_call_to_action';

	/**
	 * Full builder compatibility; component in assets/js/vb-modules.js under
	 * this slug. See the note in Honest_Divi_Module_Base on why the level is
	 * declared per module.
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	public function init() {
		$this->name             = esc_html__( 'Call To Action', 'honest-divi-modules' );
		$this->main_css_element = '%%order_class%%';

		$this->settings_modal_toggles = array(
			'general'  => array( 'toggles' => array( 'main_content' => esc_html__( 'Content', 'honest-divi-modules' ) ) ),
			'advanced' => array(
				'toggles' => array(
					'heading' => esc_html__( 'Heading', 'honest-divi-modules' ),
					'content' => esc_html__( 'Body Copy', 'honest-divi-modules' ),
					'button'  => esc_html__( 'Button', 'honest-divi-modules' ),
					'colors'  => esc_html__( 'Colors', 'honest-divi-modules' ),
				),
			),
		);

		// hide_text_color: colour for each of these font groups is owned
		// exclusively by the custom colour fields below, applied as inline
		// CSS custom properties. Divi's font-group builder otherwise
		// auto-generates a native "Text Color" sub-option per group that
		// emits a directly-targeted CSS rule, which would silently beat the
		// inherited custom-property value and defeat the single-source-of-
		// truth colour rule (see the other modules in this plugin for the
		// same pattern).
		//
		// On the `button` group specifically, hide_text_color is load-bearing
		// for a SECOND, independent reason, not just colour ownership: Divi's
		// font-group generator auto-creates an `"{$option_name}_text_color"`
		// prop for every font group unless this flag is set -- which for a
		// group named `button` means an auto-generated `button_text_color`
		// prop. This module's own colour field is named `button_label_color`
		// precisely to avoid ever colliding with that (see the file header
		// comment for the full story, including a case where an earlier,
		// differently-named field on this exact module DID collide with a
		// Divi-reserved prop and had to be renamed after the fact). Do not
		// remove `hide_text_color` from this group believing it only governs
		// colour ownership -- doing so would also re-enable Divi's
		// auto-generated `button_text_color` prop, which is a plausible-enough
		// name that a future edit could easily reintroduce a same-name
		// collision on this group without renaming this comment along with it.
		$this->advanced_fields = $this->base_advanced_fields(
			array(
				// font_size / weight / line_height / letter_spacing defaults are the
				// values on the Figma text nodes in q1MGpWgDpBoZeS6dgrddjB node
				// 12:765: heading 1:191 (50px / 800 / 1 / -0.5px), body 1:195 (23px
				// / 500 / 1.15) and the button label 1:194 (24.444px / 700 -- the
				// design's own scaled value, kept literal). The same numbers appear
				// in assets/css/modules.css, which is the floor: a Divi default
				// emits no CSS at all for an instance saved before it existed.
				'heading' => array(
					'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-cta__heading" ),
					'toggle_slug'     => 'heading',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '50px' ),
					'letter_spacing'  => array( 'default' => '-0.5px' ),
					'line_height'     => array( 'default' => '1em' ),
					'font'            => array( 'default' => '|800|||||||' ),
				),
				'content' => array(
					'label'           => esc_html__( 'Body Copy', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-cta__body" ),
					'toggle_slug'     => 'content',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '23px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'line_height'     => array( 'default' => '1.15em' ),
					'font'            => array( 'default' => '|500|||||||' ),
				),
				'button'  => array(
					'label'           => esc_html__( 'Button', 'honest-divi-modules' ),
					'css'             => array( 'main' => "{$this->main_css_element} .honest-cta__button" ),
					'toggle_slug'     => 'button',
					'hide_text_color' => true,
					'font_size'       => array( 'default' => '24.444px' ),
					'letter_spacing'  => array( 'default' => '0px' ),
					'font'            => array( 'default' => '|700|||||||' ),
				),
			)
		);
	}

	public function get_fields() {
		return array(
			'heading'           => array(
				'label'           => esc_html__( 'Heading', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'content'           => array(
				'label'           => esc_html__( 'Body Copy', 'honest-divi-modules' ),
				'type'            => 'tiny_mce',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'button_text'       => array(
				'label'           => esc_html__( 'Button Text', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Leave blank to hide the button entirely.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'text',
			),
			'button_url'        => array(
				'label'           => esc_html__( 'Button URL', 'honest-divi-modules' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'url',
			),
			// Named `cta_image`, not `background_image` -- see the file header
			// comment for why that name collides with Divi's own native
			// Background option. Stores whatever URL Divi's media modal sets
			// on the field (its standard behaviour for an `upload` field).
			// That raw value is never trusted or embedded directly --
			// get_background_image_url() resolves it back to an attachment ID
			// and re-derives the URL via wp_get_attachment_image_url(), so
			// only a URL that is genuinely still a media-library attachment
			// can ever reach the page. See that method for what happens when
			// it is not (an unset field, a moved/deleted file, or a pasted
			// external URL all end up the same way: no background image,
			// solid cta_bg_color instead).
			'cta_image'         => array(
				'label'           => esc_html__( 'Background Image', 'honest-divi-modules' ),
				'type'            => 'upload',
				'option_category' => 'basic_option',
				'description'     => esc_html__( 'Optional. Leave blank to show a solid background colour instead.', 'honest-divi-modules' ),
				'toggle_slug'     => 'main_content',
				'dynamic_content' => 'image',
			),
			'alignment'         => array(
				'label'           => esc_html__( 'Content Alignment', 'honest-divi-modules' ),
				'type'            => 'select',
				'option_category' => 'configuration',
				'description'     => esc_html__( 'Which side of the band the heading, copy and button sit on. The overlay always fades in toward this side.', 'honest-divi-modules' ),
				'options'         => array(
					'left'  => esc_html__( 'Left', 'honest-divi-modules' ),
					'right' => esc_html__( 'Right', 'honest-divi-modules' ),
				),
				'default'         => 'right',
				'toggle_slug'     => 'main_content',
			),
			// Colour fields. Defaults are the hexes extracted from Figma
			// (file 6LBpKOMFlN8KxaKbut00YW) -- see the file header comment
			// for the full method and the contrast note.
			'heading_color'     => array(
				'label'        => esc_html__( 'Heading Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the section heading.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#3a61b6',
			),
			'content_color'     => array(
				'label'        => esc_html__( 'Body Copy Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour of the body copy.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#6a8090',
			),
			'button_bg_color'   => array(
				'label'        => esc_html__( 'Button Background', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Fill colour of the button.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#000000',
			),
			// Named `button_label_color`, not `button_text_color` -- see the
			// file header comment and the `hide_text_color` comment on the
			// `button` font group in init(): `button_text_color` is the exact
			// prop name Divi's font-group generator would auto-create for a
			// font group named `button` if `hide_text_color` were ever
			// removed from it, so this field avoids that name entirely rather
			// than relying on the flag alone to prevent a collision.
			'button_label_color' => array(
				'label'        => esc_html__( 'Button Label Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Label colour of the button.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#ffffff',
			),
			// The overlay is a real translucent fill in Figma -- a gradient
			// from fully transparent to fully opaque #b8c8e7 -- not a flat
			// wash. A Divi colour field can only hold one value, so this
			// field is the gradient's opaque end (custom_color's colour
			// picker accepts an rgba() value too, if a deployment wants the
			// opaque end itself to carry some transparency); the transparent
			// end is fixed in modules.css, since a 0%-alpha stop of the same
			// hue is not itself a separate colour to expose.
			'overlay_color'     => array(
				'label'        => esc_html__( 'Overlay Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Colour the background image fades into on the content side.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#b8c8e7',
			),
			// Plain solid fill beneath the image+overlay layer. Named
			// `cta_bg_color`, not `background_color` -- see the file header
			// comment for why that name collides with Divi's own native
			// Background option. Only visible when no background image is
			// set (or the chosen one no longer resolves to a real
			// attachment) -- see get_background_image_url(). Defaults to the
			// same hue as the overlay's opaque end so the no-image state
			// reads as a continuation of the same design rather than an
			// unstyled fallback.
			'cta_bg_color'      => array(
				'label'        => esc_html__( 'Background Color', 'honest-divi-modules' ),
				'description'  => esc_html__( 'Solid fill shown if no background image is set.', 'honest-divi-modules' ),
				'type'         => 'color',
				'custom_color' => true,
				'tab_slug'     => 'advanced',
				'toggle_slug'  => 'colors',
				'default'      => '#b8c8e7',
			),
		);
	}

	/**
	 * Resolve the chosen background image back to a real, current media
	 * attachment URL.
	 *
	 * Divi's `upload` field stores whatever URL its media modal set on the
	 * field, which this method never trusts as-is: it looks up the
	 * attachment ID behind that URL and re-derives the URL from
	 * wp_get_attachment_image_url(), per the brief. An empty field, a URL
	 * that no longer matches any attachment (deleted/moved file), or a
	 * pasted external URL all resolve to '' here -- the one signal render()
	 * needs to fall back to a solid cta_bg_color instead of emitting a
	 * background-image at all.
	 *
	 * @return string Attachment URL, or '' if there is no usable image.
	 */
	protected function get_background_image_url() {
		$raw = trim( (string) $this->props['cta_image'] );

		if ( '' === $raw ) {
			return '';
		}

		$attachment_id = attachment_url_to_postid( $raw );

		if ( ! $attachment_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );

		return $url ? $url : '';
	}

	public function render( $attrs, $content, $render_slug ) {
		$align = in_array( $this->props['alignment'], array( 'left', 'right' ), true )
			? $this->props['alignment']
			: 'right';

		$parts = '';

		$heading = trim( (string) $this->props['heading'] );
		if ( '' !== $heading ) {
			$parts .= sprintf( '<h2 class="honest-cta__heading">%s</h2>', esc_html( $heading ) );
		}

		if ( '' !== trim( (string) $this->content ) ) {
			$parts .= sprintf( '<div class="honest-cta__body">%s</div>', et_core_esc_previously( $this->content ) );
		}

		$button_text = trim( (string) $this->props['button_text'] );
		if ( '' !== $button_text ) {
			$button_url = trim( (string) $this->props['button_url'] );
			$parts     .= sprintf(
				'<a class="honest-cta__button" href="%1$s">%2$s</a>',
				esc_url( '' !== $button_url ? $button_url : '#' ),
				esc_html( $button_text )
			);
		}

		// The background image is purely decorative (the design's own real
		// photo has no informational content the surrounding copy doesn't
		// already carry), so it belongs in CSS as a background, not as an
		// <img> that would need alt text. aria-hidden is defensive: this div
		// has no accessible name and is not interactive either way.
		$inner = sprintf(
			'<div class="honest-cta__media" aria-hidden="true"></div><div class="honest-cta__inner"><div class="honest-cta__content">%s</div></div>',
			$parts
		);

		return $this->wrap(
			$render_slug,
			$inner,
			array( 'honest-cta', 'honest-cta--align-' . $align ),
			array(
				'--hh-cta-heading'     => $this->props['heading_color'],
				'--hh-cta-body'        => $this->props['content_color'],
				'--hh-cta-button-bg'   => $this->props['button_bg_color'],
				'--hh-cta-button-text' => $this->props['button_label_color'],
				'--hh-cta-overlay'     => $this->props['overlay_color'],
				'--hh-cta-bg'          => $this->props['cta_bg_color'],
				'--hh-cta-bg-image'    => array( 'url' => $this->get_background_image_url() ),
			)
		);
	}
}
