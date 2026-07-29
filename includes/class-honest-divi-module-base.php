<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Honest_Divi_Module_Base extends ET_Builder_Module {

	public $vb_support = 'partial';

	protected $module_credits = array(
		'module_uri' => '',
		'author'     => 'Honest Health',
		'author_uri' => '',
	);

	/**
	 * CSS named colours (CSS Color Module Level 4 keyword set) plus the two
	 * special keywords, allowed as custom-property values by
	 * is_valid_css_color(). Lower-case; matched case-insensitively.
	 *
	 * @var string[]
	 */
	private static $css_named_colors = array(
		'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black',
		'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
		'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan',
		'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki', 'darkmagenta',
		'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon', 'darkseagreen',
		'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet', 'deeppink',
		'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen',
		'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'grey', 'green',
		'greenyellow', 'honeydew', 'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender',
		'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan',
		'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink', 'lightsalmon',
		'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue',
		'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine',
		'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue',
		'mediumspringgreen', 'mediumturquoise', 'mediumvioletred', 'midnightblue', 'mintcream',
		'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace', 'olive', 'olivedrab', 'orange',
		'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise', 'palevioletred',
		'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple',
		'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell',
		'sienna', 'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen',
		'steelblue', 'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
		'whitesmoke', 'yellow', 'yellowgreen', 'transparent', 'currentcolor',
	);

	/**
	 * Emit the outer module wrapper. Required because vb_support='partial'
	 * means Divi does not wrap third-party module output.
	 *
	 * @param string $render_slug   Slug passed to render().
	 * @param string $inner         Inner HTML already built by the module.
	 * @param array  $extra_classes Extra classnames to add to the wrapper.
	 * @param array  $css_vars      Optional map of CSS custom-property name
	 *                              (e.g. '--hh-hero-from') => value. The value
	 *                              is normally a raw colour string, used as-is.
	 *                              A value can instead be `array( 'url' => $url )`
	 *                              to expose an editable CSS <image> (e.g. a
	 *                              background image chosen via an upload
	 *                              field) as a custom property -- the raw URL
	 *                              is validated and wrapped as `url("...")`
	 *                              here, the same way colour values are
	 *                              validated, so a module never builds a
	 *                              `url(...)` string itself. Used by modules
	 *                              to expose editable colours (and, via the
	 *                              `url` form, background images) as inline
	 *                              custom properties on their own wrapper.
	 *                              Every name/value pair is validated and
	 *                              escaped here, centrally; invalid names or
	 *                              values are dropped rather than emitted.
	 */
	protected function wrap( $render_slug, $inner, $extra_classes = array(), $css_vars = array() ) {
		foreach ( (array) $extra_classes as $class ) {
			if ( '' !== $class ) {
				$this->add_classname( $class );
			}
		}

		return sprintf(
			'<div%2$s class="%1$s"%4$s>%3$s</div>',
			$this->module_classname( $render_slug ),
			$this->module_id(),
			$inner,
			$this->build_style_attr( $css_vars )
		);
	}

	/**
	 * Build a validated, escaped style="" attribute string from a map of
	 * CSS custom-property name => value.
	 *
	 * Both the property name and the value are validated. A pair that fails
	 * validation is omitted entirely -- it never reaches the output, even
	 * as an empty or partial declaration -- so a bad value or name can never
	 * smuggle extra CSS into the wrapper's style attribute.
	 *
	 * @param array $css_vars Map of '--custom-property-name' => value.
	 * @return string Either '' (no valid pairs) or ' style="--a:b;--c:d;"'.
	 */
	private function build_style_attr( $css_vars ) {
		$declarations = array();

		foreach ( (array) $css_vars as $name => $value ) {
			if ( ! is_string( $name ) || ! preg_match( '/^--[A-Za-z0-9_-]+$/', $name ) ) {
				continue;
			}

			// URL form: array( 'url' => $raw_url ). Validated and wrapped into
			// a CSS <image> value (`url("...")`) here, centrally, the same
			// way a colour value is validated below -- a module never builds
			// the `url(...)` string itself. An invalid or empty URL (e.g. no
			// image chosen) is dropped entirely, same as an invalid colour:
			// the property is left unset so the module's own var(..., fallback)
			// at the point of use decides what renders instead.
			if ( is_array( $value ) ) {
				if ( ! array_key_exists( 'url', $value ) || ! $this->is_valid_css_url( $value['url'] ) ) {
					continue;
				}

				$declarations[] = sprintf( '%s:url("%s");', $name, trim( (string) $value['url'] ) );
				continue;
			}

			if ( ! is_string( $value ) || ! $this->is_valid_css_color( $value ) ) {
				continue;
			}

			$declarations[] = "{$name}:{$value};";
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		return sprintf( ' style="%s"', esc_attr( implode( '', $declarations ) ) );
	}

	/**
	 * Whether a string is safe to embed as a CSS <image> URL, i.e. wrapped
	 * as `url("...")` inside a custom-property declaration.
	 *
	 * Requires a well-formed http/https URL (via WordPress's own
	 * wp_http_validate_url(), the same validity check WordPress applies
	 * before ever fetching a URL server-side) and rejects any character that
	 * could break out of the double-quoted CSS string literal the value is
	 * wrapped in -- a literal quote, backslash, or line break. A legitimate
	 * WordPress attachment URL never contains any of those.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private function is_valid_css_url( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return false;
		}

		if ( preg_match( '/["\\\\\r\n]/', $value ) ) {
			return false;
		}

		return false !== wp_http_validate_url( $value );
	}

	/**
	 * Whether a string is safe to use as a CSS colour value: 3/4/6/8-digit
	 * hex, rgb()/rgba(), hsl()/hsla(), or a CSS named colour keyword.
	 * Anything else (including a value that merely starts with a valid
	 * colour but carries extra content, e.g. "red;background:url(...)") is
	 * rejected.
	 *
	 * @param string $value
	 * @return bool
	 */
	private function is_valid_css_color( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return false;
		}

		if ( preg_match( '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
			return true;
		}

		if ( preg_match( '/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.]+%?\s*,\s*[0-9.]+%?\s*,\s*[0-9.]+%?\s*(?:,\s*[0-9.]+%?\s*)?\)$/i', $value ) ) {
			return true;
		}

		return in_array( strtolower( $value ), self::$css_named_colors, true );
	}

	/**
	 * Standard design-tab options every module gets.
	 *
	 * @param array $selectors Optional font groups keyed by slug.
	 */
	protected function base_advanced_fields( $selectors = array() ) {
		return array_merge(
			array(
				'fonts'          => $selectors,
				'background'     => array(),
				'margin_padding' => array(),
				'borders'        => array( 'default' => array() ),
				'box_shadow'     => array( 'default' => array() ),
				'button'         => false,
			)
		);
	}
}
