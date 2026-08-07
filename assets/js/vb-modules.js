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
		 * Build the block that holds a module's `content` field.
		 *
		 * The content field is NOT a string in the builder. Divi hands it over as
		 * a React COMPONENT, so the field stays inline-editable on the canvas --
		 * the function it passes closes over the module's props and renders Divi's
		 * own rich-text component (createElement(E.default, { rawContentProcesser:
		 * ... }, e.props)).
		 *
		 * Coercing that to a string is what printed
		 * `function(t){return o.default.createElement(...)}` into the hero. It has
		 * to be rendered, not read. All three shapes are handled, because the same
		 * field is a plain HTML string in other contexts (a saved shortcode
		 * attribute) and could already be an element:
		 *
		 * - function      -> render it, which also gives inline editing for free
		 * - React element -> use as-is
		 * - string        -> HTML authored in Divi's editor, injected as markup
		 *
		 * Returns null when there is genuinely nothing, so the wrapper is omitted
		 * exactly as the PHP render omits it. In the component form the block
		 * always renders, because Divi supplies the component whether or not the
		 * field has text -- which is also what gives the editor something to click.
		 */
		function contentBlock( value, className, key ) {
			if ( 'function' === typeof value ) {
				return e( 'div', { className: className, key: key }, e( value ) );
			}

			if ( value && value.$$typeof ) {
				return e( 'div', { className: className, key: key }, value );
			}

			var raw = text( value );

			if ( ! raw ) {
				return null;
			}

			return e( 'div', {
				className: className,
				key: key,
				dangerouslySetInnerHTML: { __html: raw }
			} );
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
		 * A computed property that carries a structure rather than a string.
		 *
		 * Divi wp_json_encode()s whatever the callback returns, and the value can
		 * reach the component either already parsed or still as JSON text, so both
		 * are accepted. A PHP callback returning array() arrives as [] -- an empty
		 * ARRAY, not an object -- which is how "nothing to show" is signalled.
		 */
		function computedData( value ) {
			var data = value;

			if ( 'string' === typeof data ) {
				try {
					data = JSON.parse( data );
				} catch ( err ) {
					return null;
				}
			}

			if ( ! data || 'object' !== typeof data ) {
				return null;
			}

			return ( Array.isArray( data ) && 0 === data.length ) ? false : data;
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

		/**
		 * A CSS <image> value for a custom property, from a raw URL prop.
		 *
		 * Only http(s) and protocol-relative URLs are accepted, and the quotes
		 * are added here, so a value carrying `)` or a `javascript:` scheme cannot
		 * break out of the url() and inject further declarations. Mirrors
		 * Honest_Divi_Module_Base::is_valid_css_url().
		 *
		 * PHP additionally resolves the URL back to a media-library attachment and
		 * drops anything that is not one. That check needs the database, so the
		 * builder preview accepts the field's URL as given -- a URL from outside
		 * the library would preview here and not render on the front end. It is a
		 * preview-only difference on a decorative background, not a divergence in
		 * what ships.
		 */
		function cssImage( value ) {
			var url = text( value );

			if ( ! url || ! /^(https?:)?\/\//i.test( url ) || /["')\\]|\s/.test( url ) ) {
				return '';
			}

			return 'url("' + url + '")';
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
				var body = contentBlock( props.content, 'honest-text-hero__body', 'body' );

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
					parts.push( body );
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

		/**
		 * Call To Action.
		 *
		 * Prop-driven like Text Hero, so it is mirrored in full and edits are
		 * instant. Mirrors Honest_Divi_Module_Call_To_Action::render().
		 *
		 * The media div is decorative -- the design's photo carries nothing the
		 * copy does not already say -- so it stays a CSS background rather than an
		 * <img> needing alt text, and keeps its aria-hidden.
		 *
		 * The alignment modifier falls back to 'right' on any unrecognised value,
		 * matching the PHP whitelist rather than trusting the stored attribute.
		 */
		modules.push( {
			slug: 'honest_call_to_action',

			render: function () {
				var props = this.props || {};
				var align = ( 'left' === props.alignment || 'right' === props.alignment ) ? props.alignment : 'right';
				var parts = [];

				var heading = text( props.heading );
				var body = contentBlock( props.content, 'honest-cta__body', 'body' );
				var buttonText = text( props.button_text );

				if ( heading ) {
					parts.push( e( 'h2', { className: 'honest-cta__heading', key: 'heading' }, heading ) );
				}

				if ( body ) {
					parts.push( body );
				}

				if ( buttonText ) {
					var url = text( props.button_url );

					parts.push( e( 'a', {
						className: 'honest-cta__button',
						key: 'button',
						href: url ? url : '#'
					}, buttonText ) );
				}

				var style = cssVars( {
					'--hh-cta-heading': props.heading_color,
					'--hh-cta-body': props.content_color,
					'--hh-cta-button-bg': props.button_bg_color,
					'--hh-cta-button-text': props.button_label_color,
					'--hh-cta-overlay': props.overlay_color,
					'--hh-cta-bg': props.cta_bg_color
				} );

				var image = cssImage( props.cta_image );

				if ( image ) {
					style['--hh-cta-bg-image'] = image;
				}

				return e(
					'div',
					{ className: 'honest-cta honest-cta--align-' + align, style: style },
					e( 'div', { className: 'honest-cta__media', 'aria-hidden': 'true' } ),
					e( 'div', { className: 'honest-cta__inner' },
						e( 'div', { className: 'honest-cta__content' }, parts )
					)
				);
			}
		} );

		/**
		 * Executive Leadership.
		 *
		 * The heading and intro are prop-driven and mirrored here, so editing
		 * either is instant. The card grid is not re-implemented: it arrives as
		 * server-rendered HTML in the `__cards` computed property, straight from
		 * the same get_cards_html() the front end uses, so there is one source of
		 * truth for that markup. This is the pattern Divi's own Blog module uses
		 * for `__posts`.
		 *
		 * PHP returns nothing at all when the roster is empty. That is mirrored,
		 * but only once the cards are known to be empty -- an undefined `__cards`
		 * means the round-trip has not landed yet, and blanking the module then
		 * would make it flash out and back on every builder load.
		 */
		modules.push( {
			slug: 'honest_executive_leadership',

			render: function () {
				var props = this.props || {};
				var cards = props.__cards;

				if ( '' === cards ) {
					return null;
				}

				var parts = [];
				var heading = text( props.heading );
				var intro = contentBlock( props.content, 'honest-exec__intro', 'intro' );

				if ( heading ) {
					parts.push( e( 'h2', { className: 'honest-exec__heading', key: 'heading' }, heading ) );
				}

				if ( intro ) {
					parts.push( intro );
				}

				var columns = text( props.columns ) || '4';
				var grid = computed( cards );

				parts.push( e( 'div', {
					className: 'honest-exec__grid honest-exec__grid--' + columns,
					key: 'grid',
					dangerouslySetInnerHTML: grid
				} ) );

				return e(
					'div',
					{
						className: 'honest-exec',
						style: cssVars( {
							'--hh-exec-heading': props.heading_color,
							'--hh-exec-intro': props.intro_color,
							'--hh-card-bg': props.card_bg_color,
							'--hh-card-rule': props.card_rule_color,
							'--hh-card-hover-bg': props.card_hover_bg_color,
							'--hh-card-hover-shadow': props.card_hover_shadow_color,
							'--hh-card-name': props.card_name_color,
							'--hh-card-title': props.card_title_color
						} )
					},
					parts
				);
			}
		} );

		/**
		 * Leadership by Market.
		 *
		 * Heading and intro are mirrored as props, so editing them is instant. The
		 * tabs and the panel/map body arrive as server-rendered HTML in the
		 * `__market` computed property, from the same get_market_parts() the front
		 * end calls -- so the interlocked tab/panel/caption ARIA wiring exists in
		 * exactly one place.
		 *
		 * The three divs are built here rather than injected as one block on
		 * purpose: `.honest-market__inner` is a two-column grid and head, tabs and
		 * body are direct grid items with explicit grid-column / grid-row. They
		 * must stay direct children, so only their contents are injected.
		 *
		 * The map animates here too, now that the driver script is enqueued into
		 * the builder iframe: market-map.js boots on any newly inserted
		 * `.honest-market` rather than only at DOMContentLoaded, which is what a
		 * builder re-render produces.
		 */
		modules.push( {
			slug: 'honest_leadership_by_market',

			render: function () {
				var props = this.props || {};
				var market = computedData( props.__market );

				// false means the callback found no markets, which is when PHP
				// renders nothing at all. null means the round-trip has not landed
				// yet, so the head still renders and the rest fills in.
				if ( false === market ) {
					return null;
				}

				var parts = [];
				var heading = text( props.heading );
				var intro = contentBlock( props.content, 'honest-market__intro', 'intro' );

				if ( heading ) {
					parts.push( e( 'h2', { className: 'honest-market__heading', key: 'heading' }, heading ) );
				}

				if ( intro ) {
					parts.push( intro );
				}

				var children = [ e( 'div', { className: 'honest-market__head', key: 'head' }, parts ) ];

				if ( market ) {
					children.push( e( 'div', {
						className: 'honest-market__tabs',
						key: 'tabs',
						role: 'tablist',
						// Not run through Divi's i18n: the builder API exposes no
						// translation bridge, and PHP still emits the translated
						// string on the front end.
						'aria-label': 'Markets',
						dangerouslySetInnerHTML: computed( market.tabs )
					} ) );

					children.push( e( 'div', {
						className: 'honest-market__body',
						key: 'body',
						dangerouslySetInnerHTML: computed( market.body )
					} ) );
				}

				return e(
					'div',
					{
						className: 'honest-market',
						style: cssVars( {
							'--hh-market-heading': props.heading_color,
							'--hh-market-intro': props.intro_color,
							'--hh-market-tab': props.tab_color,
							'--hh-market-tab-bg': props.tab_bg_color,
							'--hh-market-tab-active': props.tab_active_color,
							'--hh-market-tab-active-bg': props.tab_active_bg_color,
							'--hh-market-caption': props.caption_color,
							'--hh-card-bg': props.card_bg_color,
							'--hh-card-rule': props.card_rule_color,
							'--hh-card-hover-bg': props.card_hover_bg_color,
							'--hh-card-hover-shadow': props.card_hover_shadow_color,
							'--hh-card-name': props.card_name_color,
							'--hh-card-title': props.card_title_color
						} )
					},
					e( 'div', { className: 'honest-market__inner' }, children )
				);
			}
		} );

		/**
		 * Featured Insights.
		 *
		 * Eyebrow, heading, intro and button are prop-driven and mirrored here, so
		 * those edits are instant. The card grid arrives as server-rendered HTML in
		 * the `__cards` computed property, from the same get_cards_html() the front
		 * end calls, so the article card markup exists in one place.
		 *
		 * Two treatments, chosen by `style`, exactly as the PHP builds them:
		 * `member` folds the button into the head row beside the heading (the
		 * member page), `feature` drops it into a centred foot below the grid (Our
		 * Team). Both emit their modifier class, and the class is what carries the
		 * heading scale and the colours -- so the editor shows the treatment
		 * without the settings having to describe it.
		 *
		 * The heading is rendered as typed, including a literal `%first_name%`.
		 * Resolving that needs the member in context, which is a server concern;
		 * showing the token is also the more useful thing for whoever is editing,
		 * since it is what they wrote.
		 */
		modules.push( {
			slug: 'honest_featured_insights',

			render: function () {
				var props = this.props || {};
				var cards = props.__cards;

				if ( '' === cards ) {
					return null;
				}

				var headtext = [];
				var eyebrow = text( props.eyebrow );
				var heading = text( props.heading );
				var intro = contentBlock( props.content, 'honest-insights__intro', 'intro' );

				// %first_name% is resolved server-side on the front end. Here the
				// name arrives in the `__first_name` computed property, because a
				// member's name cannot be worked out in JavaScript -- without it the
				// builder printed the raw token.
				//
				// The three states are deliberately distinct: a known name
				// substitutes, a known-empty name drops the heading entirely (what
				// PHP does, rather than leave "Articles by " dangling), and an
				// undefined one leaves the token visible because the round-trip has
				// not landed yet -- blanking it then would make the heading flicker
				// away on every load.
				if ( -1 !== heading.indexOf( '%first_name%' ) ) {
					if ( 'string' === typeof props.__first_name ) {
						heading = props.__first_name
							? heading.split( '%first_name%' ).join( props.__first_name )
							: '';
					}
				}

				if ( eyebrow ) {
					headtext.push( e( 'p', { className: 'honest-insights__eyebrow', key: 'eyebrow' }, eyebrow ) );
				}

				if ( heading ) {
					headtext.push( e( 'h2', { className: 'honest-insights__heading', key: 'heading' }, heading ) );
				}

				if ( intro ) {
					headtext.push( intro );
				}

				var label = text( props.button_text );
				var button = label
					? e( 'a', {
						className: 'honest-insights__button',
						key: 'button',
						href: text( props.button_url ) || '#'
					}, label )
					: null;

				var style     = 'member' === props.style ? 'member' : 'feature';
				var topButton = 'member' === style && !! button;

				var head = [ e( 'div', { className: 'honest-insights__headtext', key: 'headtext' }, headtext ) ];

				if ( topButton ) {
					head.push( button );
				}

				var children = [
					e( 'div', { className: 'honest-insights__head', key: 'head' }, head ),
					e( 'div', {
						className: 'honest-insights__grid',
						key: 'grid',
						dangerouslySetInnerHTML: computed( cards )
					} )
				];

				if ( ! topButton && button ) {
					children.push( e( 'div', { className: 'honest-insights__foot', key: 'foot' }, button ) );
				}

				return e(
					'div',
					{
						className: 'honest-insights honest-insights--' + style,
						style: cssVars( {
							'--hh-insights-eyebrow': props.eyebrow_color,
							'--hh-insights-heading': props.heading_color,
							'--hh-insights-intro': props.intro_color,
							'--hh-insights-button-bg': props.button_bg_color,
							'--hh-insights-button-text': props.button_label_color,
							'--hh-insights-button-border': props.button_border_color
						} )
					},
					children
				);
			}
		} );

		/**
		 * Testimonials.
		 *
		 * The quotes come from the executive roster, so the slides and their dots
		 * arrive as server-rendered HTML in the `__testimonials` computed property
		 * -- that keeps the quote markup and the ARIA wiring tying each dot to its
		 * slide in PHP alone.
		 *
		 * The carousel region is built here rather than injected so the playback
		 * settings stay prop-driven: autoplay and the durations are plain
		 * attributes and a CSS custom property, and routing them through a server
		 * round-trip would make every nudge of a slider wait on AJAX.
		 *
		 * Durations are clamped to the same bounds the PHP enforces, so a value
		 * from a hand-edited shortcode cannot preview differently to how it ships.
		 */
		modules.push( {
			slug: 'honest_testimonials',

			render: function () {
				var props = this.props || {};
				var parts = computedData( props.__testimonials );

				if ( false === parts ) {
					return null;
				}

				var seconds = parseFloat( props.slide_duration );
				if ( ! isFinite( seconds ) || seconds < 2 || seconds > 20 ) {
					seconds = 6;
				}

				var fade = parseFloat( props.fade_duration );
				if ( ! isFinite( fade ) || fade < 0 || fade > 2000 ) {
					fade = 400;
				}

				var region = [];

				if ( parts ) {
					region.push( e( 'div', {
						className: 'honest-testimonials__slides',
						key: 'slides',
						dangerouslySetInnerHTML: computed( parts.slides )
					} ) );

					region.push( e( 'div', {
						className: 'honest-testimonials__dots',
						key: 'dots',
						role: 'group',
						'aria-label': 'Choose which quote to display',
						dangerouslySetInnerHTML: computed( parts.dots )
					} ) );
				}

				return e(
					'div',
					{
						className: 'honest-testimonials',
						style: cssVars( {
							'--hh-testimonials-quote': props.quote_color,
							'--hh-testimonials-attribution': props.attribution_color,
							'--hh-testimonials-dot': props.dot_color,
							'--hh-testimonials-dot-active': props.dot_active_color,
							'--hh-testimonials-fade': fade + 'ms'
						} )
					},
					e( 'div', { className: 'honest-testimonials__inner' },
						e( 'div', {
							className: 'honest-testimonials__region',
							role: 'region',
							'aria-roledescription': 'carousel',
							'aria-label': 'Testimonials',
							'data-autoplay': 'off' === props.autoplay ? 'off' : 'on',
							'data-slide-duration': String( Math.round( seconds * 1000 ) )
						}, region )
					)
				);
			}
		} );

		/**
		 * Team Member Header.
		 *
		 * Everything drawn here belongs to the member being viewed, so the whole
		 * body -- back bar included -- arrives as one server-rendered string and
		 * this is a thin shell around it. Folding the back bar in keeps its two
		 * inline SVGs out of JavaScript, at the cost of a round-trip when its label
		 * or URL changes, which is a fair trade for a field edited once.
		 *
		 * Colours and the portrait ring stay instant: they are custom properties
		 * and a modifier class on the wrapper built here.
		 */
		modules.push( {
			slug: 'honest_team_member_header',

			render: function () {
				var props = this.props || {};
				var body = props.__body;

				// '' means the post in context is not a team member, which is what
				// PHP renders nothing for. undefined only means the round-trip has
				// not landed yet.
				if ( '' === body ) {
					return null;
				}

				return e( 'div', {
					className: 'honest-member',
					style: cssVars( {
						'--hh-member-name': props.name_color,
						'--hh-member-title': props.job_title_color,
						'--hh-member-bio': props.bio_color,
						'--hh-member-linkedin': props.linkedin_color,
						'--hh-member-back-bg': props.back_bg_color,
						'--hh-member-back-label': props.back_label_color
					} ),
					dangerouslySetInnerHTML: computed( body )
				} );
			}
		} );

		/**
		 * Member Statement.
		 *
		 * Server-rendered for the same reason as the header above it: the markup
		 * depends on which of the member's two fields are filled in -- quote only,
		 * why only, or both in two columns -- and duplicating that decision in
		 * JavaScript is how the two drift apart. The label is the only prop the
		 * body depends on, so editing it costs one round trip; the five colours are
		 * custom properties on the wrapper built here and stay instant.
		 */
		modules.push( {
			slug: 'honest_member_statement',

			render: function () {
				var props = this.props || {};
				var body = props.__body;

				// '' is PHP's answer for "no member, or nothing to show"; undefined
				// only means the round-trip has not landed yet.
				if ( '' === body ) {
					return null;
				}

				return e( 'div', {
					className: 'honest-statement',
					style: cssVars( {
						'--hh-statement-band': props.band_color,
						'--hh-statement-quote': props.quote_color,
						'--hh-statement-label': props.label_color,
						'--hh-statement-banner': props.banner_color,
						'--hh-statement-why': props.why_color
					} ),
					dangerouslySetInnerHTML: computed( body )
				} );
			}
		} );

		API.registerModules( modules );

		registerFields( API );
	}

	/**
	 * Custom settings-modal fields.
	 *
	 * Registered through `API.registerModalFields`, which the builder resolves by
	 * matching a component's slug against a field's `type` in get_fields(). A
	 * registered field component is handed, among others:
	 *
	 *   name            the attribute name, e.g. 'manual_ids'
	 *   value           its current stored value
	 *   fieldDefinition the PHP definition, including `options`
	 *   _onChange       ( name, value ) -- how a field writes back
	 *
	 * Note the modal renders in the builder's TOP window while this file runs in
	 * the preview iframe, and the two hold separate React instances. Registration
	 * still happens from here because that is the only window where
	 * `ET_Builder.API` exists at all -- on the top window it is an empty object.
	 */
	function registerFields( API ) {
		if ( ! API.registerModalFields ) {
			return;
		}

		var e = window.React.createElement;

		// Divi's multi-value controls store a pipe-delimited string. Kept in that
		// shape so the value stays a plain scalar attribute, and so it round-trips
		// through the shortcode exactly like any built-in field. The PHP side
		// (Honest_Divi_Module_Featured_Insights::parse_post_ids) accepts this and
		// the older comma form.
		function items( value, token ) {
			if ( ! value || 'undefined' === value ) {
				return [];
			}

			var seen = {};

			return String( value ).split( /[|,]/ )
				.map( function ( part ) {
					part = String( part ).trim();

					// The token is kept as-is; everything else is a post ID. It is
					// non-numeric by design, so the two can never be confused.
					return ( token && token === part ) ? part : parseInt( part, 10 );
				} )
				.filter( function ( item ) {
					if ( item !== token && ! ( item > 0 ) ) {
						return false;
					}

					if ( seen[ item ] ) {
						return false;
					}

					seen[ item ] = true;

					return true;
				} );
		}

		function move( list, from, to ) {
			var next = list.slice();
			var moved = next.splice( from, 1 )[ 0 ];

			next.splice( to, 0, moved );

			return next;
		}

		API.registerModalFields( [ {
			slug: 'honest_post_picker',

			render: function () {
				var props = this.props || {};
				var definition = props.fieldDefinition || {};
				var options = definition.options || {};

				// The member token is only meaningful for the source that expands
				// it, so it is offered only there rather than sitting in the list
				// as a dead entry under plain manual selection.
				var settings = props.moduleSettings || {};
				var token = 'current_member_custom' === settings.source
					? ( definition.honest_member_token || 'member' )
					: null;
				var tokenLabel = definition.honest_member_token_label || 'Member posts';

				var chosen = items( props.value, token );

				// Every interaction is a whole new value written straight back, so
				// the control keeps no state of its own and always redraws from
				// what is actually stored.
				var commit = function ( list ) {
					props._onChange( props.name, list.join( '|' ) );
				};

				var isToken = function ( item ) {
					return null !== token && token === item;
				};

				var titleOf = function ( item ) {
					if ( isToken( item ) ) {
						return tokenLabel;
					}

					return options[ String( item ) ] || ( '#' + item );
				};

				var chosenRows = chosen.map( function ( id, index ) {
					return e( 'li', {
						className: 'honest-picker__item' + ( isToken( id ) ? ' honest-picker__item--token' : '' ),
						key: 'sel-' + id
					},
						e( 'span', { className: 'honest-picker__position' }, ( index + 1 ) + '.' ),
						e( 'span', { className: 'honest-picker__title', title: titleOf( id ) }, titleOf( id ) ),
						e( 'button', {
							type: 'button',
							className: 'honest-picker__button',
							disabled: 0 === index,
							title: 'Move up',
							onClick: function () { commit( move( chosen, index, index - 1 ) ); }
						}, '\u2191' ),
						e( 'button', {
							type: 'button',
							className: 'honest-picker__button',
							disabled: index === chosen.length - 1,
							title: 'Move down',
							onClick: function () { commit( move( chosen, index, index + 1 ) ); }
						}, '\u2193' ),
						e( 'button', {
							type: 'button',
							className: 'honest-picker__button honest-picker__button--remove',
							title: 'Remove',
							onClick: function () {
								commit( chosen.filter( function ( other ) { return other !== id; } ) );
							}
						}, '\u00d7' )
					);
				} );

				var addRow = function ( value, label, extraClass ) {
					var add = function () { commit( chosen.concat( [ value ] ) ); };

					return e( 'li', {
						className: 'honest-picker__item honest-picker__item--available' + ( extraClass || '' ),
						key: 'opt-' + value,
						onClick: add
					},
						e( 'span', { className: 'honest-picker__title', title: label }, label ),
						e( 'button', {
							type: 'button',
							className: 'honest-picker__button',
							title: 'Add',
							onClick: function ( event ) {
								// The row already handles the click; without this the
								// entry would be added twice.
								event.stopPropagation();
								add();
							}
						}, '+' )
					);
				};

				var availableRows = [];

				// Offered first so it is the obvious thing to place, and only while
				// it is not already in the running order.
				if ( token && -1 === chosen.indexOf( token ) ) {
					availableRows.push( addRow( token, tokenLabel, ' honest-picker__item--token' ) );
				}

				Object.keys( options )
					.filter( function ( id ) { return -1 === chosen.indexOf( parseInt( id, 10 ) ); } )
					.forEach( function ( id ) {
						availableRows.push( addRow( parseInt( id, 10 ), options[ id ] ) );
					} );

				return e( 'div', { className: 'honest-picker' },
					e( 'div', { className: 'honest-picker__group' },
						e( 'span', { className: 'honest-picker__label' },
							'Chosen ',
							e( 'span', { className: 'honest-picker__count' }, '(' + chosen.length + ', in order)' )
						),
						chosen.length
							? e( 'ul', { className: 'honest-picker__list' }, chosenRows )
							: e( 'div', { className: 'honest-picker__list honest-picker__empty' }, 'Nothing chosen yet.' )
					),
					e( 'div', { className: 'honest-picker__group' },
						e( 'span', { className: 'honest-picker__label' }, 'Available posts' ),
						availableRows.length
							? e( 'ul', { className: 'honest-picker__list honest-picker__list--available' }, availableRows )
							: e( 'div', { className: 'honest-picker__list honest-picker__empty' }, 'Every listed post has been chosen.' )
					),
					e( 'p', { className: 'honest-picker__hint' },
						token
							? 'Cards appear in the order above, with "' + tokenLabel + '" standing in for this member\'s own articles. Leave the list empty to show only theirs. The Number of Articles setting still caps the total.'
							: 'Cards appear in the order above. The Number of Articles setting still caps how many are shown.'
					)
				);
			}
		} ] );
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
