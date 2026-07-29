/**
 * React components giving this plugin's modules full Visual Builder
 * compatibility (vb_support = 'on').
 *
 * Divi's compatibility levels are documented at
 * elegantthemes.com/documentation/developers/divi-module/compatibility-levels/.
 * 'partial' makes the builder re-render a module by AJAX-ing its PHP on every
 * change, which is what produces the "not fully compatible ... will take longer
 * to update" notice. Reaching 'on' requires the module to draw itself in the
 * builder through the JavaScript API; PHP alone cannot do it.
 *
 * Registration contract, per the builder JavaScript API docs:
 *
 *   jQuery( window ).on( 'et_builder_api_ready', function ( event, API ) {
 *     API.registerModules( [ { slug: '...', render: function () {} } ] );
 *   } );
 *
 * Two things about that are easy to get wrong, both established by inspecting
 * the running builder rather than from the docs:
 *
 * - `ET_Builder.API` and the `et_builder_api_ready` event live on the builder's
 *   PREVIEW IFRAME window, not the top window. The bundle assigns them to
 *   `appWindow()`. A script running in the top frame sees `ET_Builder.API` as an
 *   empty object and the event never fires for it. This file is enqueued through
 *   `et_fb_enqueue_assets` and runs inside that iframe, so its own `window` is
 *   already the right one.
 * - React and ReactDOM are globals in that iframe, so no JSX, bundler or build
 *   step is involved -- these components are plain ES5 calling
 *   React.createElement.
 *
 * No component here re-implements markup that PHP already owns. Anything driven
 * by the database -- member cards, article cards, the map -- arrives as
 * server-rendered HTML through a Divi computed property and is injected as-is.
 * That is the same pattern Divi's own Blog module uses for `__posts`, and it
 * keeps a single source of truth for the markup: the PHP partials the front end
 * renders. Only genuinely prop-driven markup is mirrored here, which is what
 * makes text, colour and typography edits instant.
 */
( function () {
	'use strict';

	var registered = false;

	function register( API ) {
		// The event can fire more than once across a builder session, and this
		// file also probes for an API that is already up, so both paths can
		// arrive. Registering the same slug twice throws.
		if ( registered || ! API || ! API.registerModules ) {
			return;
		}
		registered = true;

		var e = window.React.createElement;

		/**
		 * Divi hands the module's attributes to the component as props, keyed by
		 * the field names from get_fields(). An unset field can arrive as
		 * undefined or as the literal string 'undefined' (Divi stringifies
		 * attributes through the shortcode layer), and a field the user has
		 * cleared arrives as ''. All three mean "nothing here".
		 */
		function text( value ) {
			if ( ! value || 'undefined' === value ) {
				return '';
			}

			return String( value ).trim();
		}

		/**
		 * Content fields hold HTML authored in Divi's rich-text editor, so they
		 * cannot go through a text node. This is the builder's own preview of
		 * content the editor just typed; it is the same trust boundary as the
		 * editor itself.
		 */
		function html( value ) {
			var raw = text( value );

			return raw ? { __html: raw } : null;
		}

		/**
		 * Server-rendered markup from a computed property. Divi resolves the
		 * property to a string of HTML; before the first AJAX round-trip
		 * completes it is undefined, which renders nothing rather than an error.
		 */
		function computed( value ) {
			return 'string' === typeof value && '' !== value ? { __html: value } : null;
		}

		/**
		 * Build the CSS custom properties for a module's own div, mirroring
		 * Honest_Divi_Module_Base::wrap().
		 *
		 * `vars` maps a custom-property name to a prop value. A blank value is
		 * omitted rather than set empty, so the `var(--token, fallback)` in the
		 * stylesheet supplies the default -- setting the property to '' would
		 * defeat that fallback and render the value as nothing at all.
		 *
		 * React writes custom properties from a style object as-is, so no
		 * camelCase conversion applies here.
		 */
		function cssVars( vars ) {
			var style = {};
			var name;

			for ( name in vars ) {
				if ( Object.prototype.hasOwnProperty.call( vars, name ) ) {
					var value = text( vars[ name ] );

					if ( value ) {
						style[ name ] = value;
					}
				}
			}

			return style;
		}

		var modules = [];

		/**
		 * Text Hero.
		 *
		 * Entirely prop-driven, so it is mirrored in full and every keystroke is
		 * instant with no server round-trip.
		 *
		 * Mirrors Honest_Divi_Module_Text_Hero::render(). Deliberately matched to
		 * it detail for detail, because the two outputs have to be
		 * indistinguishable:
		 * - each part is omitted entirely when its field is blank, so an empty
		 *   eyebrow leaves no floating highlight shape behind;
		 * - the eyebrow's inner span is what carries the blue banner (its
		 *   ::before) and has to shrink-wrap the text, which is the whole reason
		 *   the eyebrow is wrapped rather than styled on the <p>;
		 * - the animation classes are NOT emitted. PHP already withholds them for
		 *   builder renders (honest_team_is_builder_render): they start the
		 *   element at opacity 0 waiting for a scroll handler that never runs
		 *   here, which would leave the builder showing an empty section.
		 *
		 * The outer div here is the same one PHP emits -- component class plus the
		 * editable colours as CSS custom properties. It has to be ours rather than
		 * Divi's builder wrapper, which takes no custom classes; see the note on
		 * Honest_Divi_Module_Base::wrap().
		 */
		modules.push( {
			slug: 'honest_text_hero',

			render: function () {
				var props = this.props || {};
				var parts = [];

				var eyebrow = text( props.eyebrow );
				var headline = text( props.headline );
				var body = html( props.content );

				if ( eyebrow ) {
					parts.push( e(
						'p',
						{ className: 'honest-text-hero__eyebrow', key: 'eyebrow' },
						e( 'span', { className: 'honest-text-hero__eyebrow-text' }, eyebrow )
					) );
				}

				if ( headline ) {
					parts.push( e( 'h1', { className: 'honest-text-hero__headline', key: 'headline' }, headline ) );
				}

				if ( body ) {
					parts.push( e( 'div', {
						className: 'honest-text-hero__body',
						key: 'body',
						dangerouslySetInnerHTML: body
					} ) );
				}

				return e(
					'div',
					{
						className: 'honest-text-hero',
						style: cssVars( {
							'--hh-hero-from': props.gradient_start_color,
							'--hh-hero-to': props.gradient_end_color,
							'--hh-hero-text': props.text_color,
							'--hh-hero-headline': props.headline_color,
							'--hh-hero-eyebrow-highlight': props.eyebrow_highlight_color
						} )
					},
					e( 'div', { className: 'honest-text-hero__inner' }, parts )
				);
			}
		} );

		API.registerModules( modules );
	}

	function boot() {
		var API = window.ET_Builder && window.ET_Builder.API;

		if ( API && API.registerModules ) {
			register( API );
		}
	}

	// Both paths are needed and each is insufficient alone: the event is missed
	// if the API came up before this file ran, and the probe finds nothing if it
	// ran first. `registered` makes the overlap harmless.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'et_builder_api_ready', function ( event, API ) {
			register( API );
		} );
	}

	boot();
}() );
