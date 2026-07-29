<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Honest_Divi_Module_Base extends ET_Builder_Module {

	/**
	 * Full Visual Builder compatibility. Requires a React component per module
	 * registered through Divi's builder JavaScript API -- see
	 * assets/js/vb-modules.js. PHP alone cannot reach this level.
	 *
	 * Declared per module rather than here, and deliberately left at 'partial'
	 * as the default: a module set to 'on' without a matching React component
	 * has no way to draw itself in the builder. Each module raises its own level
	 * as its component lands, so the two can never drift apart.
	 *
	 * The level also decides who owns the module wrapper.
	 * ET_Builder_Element::_render_module_wrapper() runs only when
	 * `'on' === $this->vb_support && ! $this->_is_official_module`
	 * (class-et-builder-element.php:3458), and it emits BOTH an outer and an
	 * inner div -- so a module at 'on' must not emit a wrapper of its own, while
	 * one at 'partial' must. wrap() branches on exactly that.
	 *
	 * @var string
	 */
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
	 * Hand the module's classes and CSS custom properties to the wrapper Divi
	 * builds, and return the inner content unwrapped.
	 *
	 * The element carrying the component class and the custom properties must be
	 * one we own, because it has to be reproducible in the builder too. Divi's
	 * builder builds its own wrapper in React from the module's type and order
	 * and offers no supported way to add a class to it -- `moduleInfo` exposes
	 * `orderClassName` and nothing writable. Putting the class on Divi's wrapper
	 * server-side (via add_classname, or via the
	 * et_builder_module_{slug}_outer_wrapper_attrs filter for the properties)
	 * therefore works on the front end and cannot work in the builder, which
	 * would style a different element in each context -- the exact divergence
	 * full compatibility is supposed to remove.
	 *
	 * So at 'on' this emits one plain div holding the component class and the
	 * properties, nested inside the outer/inner pair Divi draws, and the React
	 * component in assets/js/vb-modules.js emits the identical div. At 'partial'
	 * Divi wraps nothing, so this div is also the module wrapper and carries
	 * Divi's own classes and id as well.
	 *
	 * Note that add_classname() is deliberately NOT called at 'on': it would put
	 * the component class on Divi's outer wrapper as well as on our div, and a
	 * band like the hero's would then paint twice, nested.
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
		$classes = array_values( array_filter( array_map( 'strval', (array) $extra_classes ) ) );

		if ( 'on' === $this->vb_support ) {
			return sprintf(
				'<div class="%1$s"%3$s>%2$s</div>',
				esc_attr( implode( ' ', $classes ) ),
				$inner,
				$this->build_style_attr( $css_vars )
			);
		}

		foreach ( $classes as $class ) {
			$this->add_classname( $class );
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
		$declarations = $this->build_css_var_declarations( $css_vars );

		if ( '' === $declarations ) {
			return '';
		}

		return sprintf( ' style="%s"', esc_attr( $declarations ) );
	}

	/**
	 * Validate a map of CSS custom properties into a declaration string.
	 *
	 * Returns the raw, UNESCAPED declarations (e.g. `--a:red;--b:blue;`) so the
	 * caller can decide on escaping: build_style_attr() escapes for direct
	 * output, while filter_outer_wrapper_attrs() must not, because Divi escapes
	 * the attribute itself.
	 *
	 * @param array $css_vars Map of '--custom-property-name' => value.
	 * @return string Either '' (no valid pairs) or 'name:value;name:value;'.
	 */
	private function build_css_var_declarations( $css_vars ) {
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
				if ( array_key_exists( 'url', $value ) ) {
					if ( ! $this->is_valid_css_url( $value['url'] ) ) {
						continue;
					}

					$declarations[] = sprintf( '%s:url("%s");', $name, trim( (string) $value['url'] ) );
					continue;
				}

				// Duration form: array( 'ms' => $milliseconds ). Emitted as a CSS
				// <time>, so a module never assembles the unit itself. Bounded at
				// one minute: the values behind this are animation durations, and a
				// larger number is a mistake or a stuck transition rather than an
				// intent. Anything non-numeric, negative or out of range is dropped
				// like an invalid colour, leaving the stylesheet's
				// var(--token, fallback) to decide.
				if ( array_key_exists( 'ms', $value ) ) {
					if ( ! is_numeric( $value['ms'] ) ) {
						continue;
					}

					$ms = (int) round( (float) $value['ms'] );

					if ( $ms < 0 || $ms > 60000 ) {
						continue;
					}

					$declarations[] = sprintf( '%s:%dms;', $name, $ms );
					continue;
				}

				continue;
			}

			if ( ! is_string( $value ) || ! $this->is_valid_css_color( $value ) ) {
				continue;
			}

			$declarations[] = "{$name}:{$value};";
		}

		return implode( '', $declarations );
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
		return array(
			'fonts'          => $selectors,
			'background'     => array(),
			'margin_padding' => array(),
			'borders'        => array( 'default' => array() ),
			'box_shadow'     => array( 'default' => array() ),
			'button'         => false,
		);
	}
}
