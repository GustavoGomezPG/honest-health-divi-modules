/**
 * Lottie playback driver for the animated US market map.
 *
 * The Lottie composition (assets/lottie/market-map.json) contains four
 * segments in one timeline, one per market, in a fixed order: West,
 * Southwest, Midwest, East (assets/lottie/market-map-segments.json).
 * Each segment plays an empty outlined map, then that market's states
 * popping in, then the region label.
 *
 * Switching markets reverses the currently displayed segment back to the
 * shared empty-map base, then plays the requested segment forward once
 * that reversal completes. Guard frames between segments in the source
 * asset ensure one segment's layers are fully retired before the next
 * begins.
 *
 * Exposes a global `HonestMarketMap` with `init(container)` and
 * `showSegment(index)`, where `index` is the 0-based position in the
 * segments array (not the manifest's 1-based `index` field).
 *
 * `init()` also reads `data-speed` off the container and applies it via
 * lottie-web's `setSpeed()` -- a plain playback-rate multiplier, not a change
 * to the segment frame ranges above. It is parsed defensively (see
 * `parseSpeed`) because `setSpeed(0)` freezes the animation and a negative
 * value reverses it; anything missing, non-numeric, non-positive or absurd
 * falls back to `DEFAULT_SPEED`.
 *
 * Playback state is tracked as two separate things on purpose:
 * - `displayed`: the segment actually rendered on screen right now (only
 *   ever updated once a `playSegments` call for it has actually been
 *   issued), used as the source for the next reversal.
 * - `pendingTarget`: the segment queued to play forward once an in-flight
 *   reversal completes. While a reversal is in flight, later calls only
 *   update this value rather than re-issuing another reversal (lottie-web
 *   applies `playSegments` synchronously, so re-issuing one mid-flight
 *   would overwrite the in-progress animation and skip its `complete`
 *   event) or attaching another `complete` listener - at most one is ever
 *   live at a time.
 */
( function () {
	'use strict';

	var anim = null;
	var segments = [];
	var displayed = -1;
	var pendingTarget = -1;
	var completeHandler = null;

	// Fallback used whenever `data-speed` is missing or unusable. lottie-web's
	// setSpeed(0) freezes playback and negative values reverse it, so anything
	// that isn't a sane positive multiplier must not reach setSpeed() as-is.
	var DEFAULT_SPEED = 3;
	var MAX_SPEED = 16;

	function parseSpeed( raw ) {
		var value = parseFloat( raw );
		if ( ! isFinite( value ) || value <= 0 || value > MAX_SPEED ) {
			return DEFAULT_SPEED;
		}
		return value;
	}

	/**
	 * How long a segment takes to play, in milliseconds.
	 *
	 * Derived rather than tabulated, because both inputs are editable: the frame
	 * ranges come from the manifest (West 82 frames, Southwest 54, Midwest 86,
	 * East 70) and the speed is a Divi field. At the default speed of 3 these
	 * come out 911 / 600 / 956 / 778ms.
	 */
	function segmentDurationMs( index ) {
		var seg = segments[ index ];
		if ( ! seg ) { return 0; }

		var fps = ( anim && anim.frameRate ) ? anim.frameRate : 30;
		var speed = ( anim && anim.playSpeed ) ? anim.playSpeed : DEFAULT_SPEED;

		return Math.abs( seg.out - seg.in ) / fps / speed * 1000;
	}

	// Choreography hooks, consumed by the tab controller to time the member
	// cards against the map.
	//
	// The controller cannot compute these moments itself. Segment durations
	// depend on the per-market frame ranges and the editable speed multiplier,
	// both of which live here; and the instant a reversal finishes is not
	// predictable from the click, because a second click retargets an in-flight
	// reversal without restarting it, so the forward play still begins at the
	// FIRST click's reversal end. Emitting from the two places a play is
	// actually issued makes that correct by construction.
	//
	// Not reset by init(): listeners belong to the page's controllers, which
	// register once at DOMContentLoaded, whereas init() may re-run per
	// animation instance.
	var listeners = { reversestart: [], forwardstart: [] };

	function on( name, fn ) {
		if ( listeners[ name ] && 'function' === typeof fn ) {
			listeners[ name ].push( fn );
		}
	}

	function emit( name, index ) {
		var detail = { index: index, durationMs: segmentDurationMs( index ) };
		( listeners[ name ] || [] ).forEach( function ( fn ) { fn( detail ); } );
	}

	function isPlayable( index ) {
		return typeof index === 'number' &&
			isFinite( index ) &&
			index === Math.floor( index ) &&
			index >= 0 &&
			!! ( segments && segments[ index ] );
	}

	function play( index, reverse ) {
		var seg = segments[ index ];
		if ( ! anim || ! seg ) { return false; }
		anim.setDirection( reverse ? -1 : 1 );
		anim.playSegments( reverse ? [ seg.out, seg.in ] : [ seg.in, seg.out ], true );
		return true;
	}

	function detachHandler() {
		if ( completeHandler && anim ) {
			anim.removeEventListener( 'complete', completeHandler );
		}
		completeHandler = null;
	}

	function onReverseComplete() {
		completeHandler = null;
		var target = pendingTarget;
		pendingTarget = -1;
		if ( ! isPlayable( target ) ) { return; }
		if ( play( target, false ) ) {
			displayed = target;
			emit( 'forwardstart', target );
		}
	}

	function showSegment( index ) {
		if ( ! anim || ! isPlayable( index ) ) { return; }

		if ( pendingTarget !== -1 ) {
			// A reversal is already in flight, reversing the segment that was
			// genuinely on screen when it started. Don't re-issue it - just
			// retarget where it lands once it completes.
			if ( index === pendingTarget ) { return; }
			pendingTarget = index;
			return;
		}

		if ( index === displayed ) { return; }

		if ( displayed === -1 ) {
			// Nothing shown yet: play forward immediately, no reverse.
			if ( play( index, false ) ) {
				displayed = index;
				emit( 'forwardstart', index );
			}
			return;
		}

		if ( ! play( displayed, true ) ) { return; }

		emit( 'reversestart', displayed );

		pendingTarget = index;
		completeHandler = function handler() {
			anim.removeEventListener( 'complete', handler );
			onReverseComplete();
		};
		anim.addEventListener( 'complete', completeHandler );
	}

	function init( container ) {
		if ( ! window.lottie || ! container ) { return; }

		detachHandler();

		var raw = container.getAttribute( 'data-segments' );
		var parsed = [];
		if ( raw ) {
			try {
				parsed = JSON.parse( raw );
			} catch ( e ) {
				parsed = [];
			}
		}
		segments = Array.isArray( parsed ) ? parsed : [];

		displayed = -1;
		pendingTarget = -1;

		anim = window.lottie.loadAnimation( {
			container: container,
			renderer: 'svg',
			loop: false,
			autoplay: false,
			path: container.getAttribute( 'data-lottie' )
		} );
		anim.setSpeed( parseSpeed( container.getAttribute( 'data-speed' ) ) );

		// Settle onto whatever segment is actually wanted, once the composition
		// is real.
		//
		// The tab controller becomes clickable the moment DOMContentLoaded
		// fires, which is before market-map.json finishes downloading, so a
		// click can legitimately arrive while the animation is still empty.
		// Two things are true of that window on lottie-web 5.12.2, both
		// measured rather than assumed:
		//
		//   1. playSegments() IS accepted synchronously -- straight after the
		//      click, firstFrame/totalFrames read 148/86 for segment 2.
		//   2. It does NOT survive the data load. When the JSON arrives lottie
		//      installs the full composition and firstFrame/totalFrames snap
		//      back to 0/316, discarding the requested segment while leaving
		//      the animation playing, so the whole map animates through and
		//      parks on the final frame.
		//
		// So neither "always showSegment(0)" nor "showSegment(0) only if
		// nothing was requested" is correct: the first reverses the user's
		// choice back out, the second leaves the full composition playing.
		// The request has to be re-issued here instead. `displayed` is reset
		// first so showSegment() takes its "nothing shown yet" branch and
		// plays the target forward -- there is no meaningful segment to
		// reverse out of, since the timeline is sitting at the end of the
		// whole composition rather than on a segment. pendingTarget wins when
		// set, because two clicks during loading leave the second one there.
		anim.addEventListener( 'DOMLoaded', function () {
			var target = -1 !== pendingTarget
				? pendingTarget
				: ( -1 === displayed ? 0 : displayed );

			detachHandler();
			displayed = -1;
			pendingTarget = -1;
			showSegment( target );
		} );
	}

	window.HonestMarketMap = { init: init, showSegment: showSegment, on: on, segmentDurationMs: segmentDurationMs };

	document.addEventListener( 'DOMContentLoaded', function () {
		var el = document.querySelector( '.honest-market-map' );
		if ( el ) { init( el ); }
	} );
}() );

/**
 * Tab controller for the Leadership by Market module.
 *
 * Kept separate from the playback driver above: this only knows about DOM
 * state (which tab is selected, which panel and caption are visible, what the
 * map's text alternative says) and asks HonestMarketMap for the animation.
 *
 * The member cards are choreographed against the map rather than swapped
 * instantly. Both hand-offs land three quarters of the way through the map
 * animation that motivates them, which is late enough to read as caused by the
 * map and early enough to overlap it:
 *
 *   market change -> map reverses out
 *                    |-- 75% -> visible cards fade out, finishing as the
 *                    |          reversal ends
 *                    '-- reversal ends -> panels swap; the incoming cards are
 *                        held invisible while the new segment plays forward
 *                        |-- 75% -> incoming cards rise in, staggered
 *
 * The two moments come from the driver's `reversestart` / `forwardstart`
 * events, never from timers started at the click: a second click retargets an
 * in-flight reversal without restarting it, so a click-relative timer would
 * fire against the wrong segment's duration. Only the 75% offsets are timed
 * here, each measured from the event that just fired and carrying that
 * segment's own duration.
 *
 * Consequence worth knowing: the panel swap deliberately LAGS the click by the
 * reversal's length (600-956ms at the default speed). Tab `aria-selected` and
 * focus move immediately so the control never feels dead; the panel, caption
 * and the map's own text alternative move together, one beat later, so they are
 * never describing a region the map is not showing.
 *
 * The driver is single-instance by design (one `anim`, bound to the first
 * `.honest-market-map` on the page). Only the module containing that element
 * choreographs; any further instance falls back to an instant swap rather than
 * dead buttons.
 */
( function () {
	'use strict';

	// Fraction of the map animation elapsed before the cards react.
	var STAGE_FRACTION = 0.75;

	// Safety net for the held cards. The incoming panel's cards start invisible
	// and are revealed by `forwardstart`, which is emitted once the Lottie JSON
	// has loaded. If that fetch fails outright the event never comes, and
	// without this the section would render permanently empty -- a broken
	// decorative animation must not be able to hide the actual content.
	var LOAD_GRACE_MS = 5000;

	function setup( root, drivesMap ) {
		var tabs = [].slice.call( root.querySelectorAll( '.honest-market__tab' ) );

		if ( ! tabs.length ) { return; }

		var captions = [].slice.call( root.querySelectorAll( '.honest-market__caption' ) );
		var map = root.querySelector( '.honest-market-map' );
		var timers = [];
		var staged = drivesMap && window.HonestMarketMap && window.HonestMarketMap.on;

		function panelAt( index ) {
			var tab = tabs[ index ];
			return tab ? document.getElementById( tab.getAttribute( 'aria-controls' ) ) : null;
		}

		function segmentOf( index ) {
			var raw = parseInt( tabs[ index ].getAttribute( 'data-segment' ), 10 );
			return isNaN( raw ) ? index : raw;
		}

		// Segments are authored in tab order, but the mapping is read off the
		// markup rather than assumed, because `data-segment` is what the driver
		// is actually given.
		function indexOfSegment( segment ) {
			var i;
			for ( i = 0; i < tabs.length; i++ ) {
				if ( segmentOf( i ) === segment ) { return i; }
			}
			return -1;
		}

		function clearTimers() {
			while ( timers.length ) { clearTimeout( timers.pop() ); }
		}

		function later( fn, ms ) {
			if ( ! ( ms > 0 ) ) { fn(); return; }
			timers.push( setTimeout( fn, ms ) );
		}

		function cardsOf( panel ) {
			return panel ? [].slice.call( panel.querySelectorAll( '.honest-member-card' ) ) : [];
		}

		// Cleared on every state change: `honest-market-card-in` and
		// `-out` both use `animation-fill-mode: both`, so a stale class would
		// keep pinning the opacity and offset it finished on.
		function reset( card ) {
			card.classList.remove( 'honest-market-enter', 'honest-market-leave' );
			card.style.animationDuration = '';
		}

		function hold( panel ) {
			cardsOf( panel ).forEach( function ( card ) {
				reset( card );
				card.classList.add( 'honest-market-hold' );
			} );
		}

		// Removing the class, reading offsetWidth, then re-adding is the standard
		// way to restart a CSS animation -- the read forces a style flush, without
		// which the browser coalesces both changes and nothing replays. Necessary
		// because the same cards are shown many times. The per-card stagger comes
		// from :nth-child delays in the stylesheet.
		function enter( panel ) {
			var cards = cardsOf( panel );
			cards.forEach( reset );
			if ( cards.length ) { void panel.offsetWidth; }
			cards.forEach( function ( card ) {
				card.classList.remove( 'honest-market-hold' );
				card.classList.add( 'honest-market-enter' );
			} );
		}

		// `ms` is the animation time left in the reversal, so the fade lands
		// exactly as the map reaches its empty state instead of being cut off
		// mid-way by the panel swap. It varies per market (150-240ms at the
		// default speed), which is why it is set inline rather than in the
		// stylesheet.
		function leave( panel, ms ) {
			cardsOf( panel ).forEach( function ( card ) {
				reset( card );
				if ( ms > 0 ) { card.style.animationDuration = ms + 'ms'; }
				card.classList.add( 'honest-market-leave' );
			} );
		}

		// Everything that must agree with the region the map is showing.
		function reveal( index ) {
			tabs.forEach( function ( tab, i ) {
				var panel = panelAt( i );
				if ( panel ) { panel.hidden = i !== index; }
			} );

			captions.forEach( function ( caption, i ) {
				caption.hidden = i !== index;
			} );

			if ( ! map ) { return; }

			var label = tabs[ index ].getAttribute( 'data-map-label' );
			if ( label ) { map.setAttribute( 'aria-label', label ); }

			// The visible caption is the map's description. An empty one
			// describes nothing, so the attribute is dropped rather than
			// pointed at a blank node.
			var current = captions[ index ];
			if ( current && current.id && '' !== current.textContent.replace( /\s+/g, '' ) ) {
				map.setAttribute( 'aria-describedby', current.id );
			} else {
				map.removeAttribute( 'aria-describedby' );
			}
		}

		// The panel actually on screen, which during a market change is NOT the
		// selected one: select() moves `aria-selected` immediately so the control
		// does not feel dead, while the panel swap waits for the map. Reading
		// `hidden` is the only source that stays true across that gap -- keying
		// off aria-selected here faded the incoming panel's cards instead of the
		// outgoing ones, and the visible cards were cut at the swap.
		function visiblePanel() {
			var i;
			for ( i = 0; i < tabs.length; i++ ) {
				var panel = panelAt( i );
				if ( panel && ! panel.hidden ) { return panel; }
			}
			return panelAt( 0 );
		}

		function select( index ) {
			if ( index < 0 || index >= tabs.length ) { return; }

			tabs.forEach( function ( tab, i ) {
				var on = i === index;
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', on ? '0' : '-1' );
			} );

			if ( staged ) {
				window.HonestMarketMap.showSegment( segmentOf( index ) );
				return;
			}

			// No map driving this instance: nothing to choreograph against.
			clearTimers();
			reveal( index );
			enter( panelAt( index ) );
		}

		if ( staged ) {
			window.HonestMarketMap.on( 'reversestart', function ( detail ) {
				clearTimers();

				// detail.index is the segment being retracted, so its panel is the
				// one holding the cards that have to go. visiblePanel() covers the
				// case where the map's segment has no tab on this instance.
				var outgoing = indexOfSegment( detail.index );
				var panel = outgoing < 0 ? visiblePanel() : panelAt( outgoing );
				var elapsed = detail.durationMs * STAGE_FRACTION;

				later( function () {
					leave( panel, detail.durationMs - elapsed );
				}, elapsed );
			} );

			window.HonestMarketMap.on( 'forwardstart', function ( detail ) {
				var index = indexOfSegment( detail.index );
				if ( index < 0 ) { return; }

				clearTimers();
				reveal( index );

				var panel = panelAt( index );
				hold( panel );
				later( function () { enter( panel ); }, detail.durationMs * STAGE_FRACTION );
			} );

			setTimeout( function () {
				var panel = visiblePanel();
				var stuck = cardsOf( panel ).some( function ( card ) {
					return card.classList.contains( 'honest-market-hold' );
				} );
				if ( stuck ) { enter( panel ); }
			}, LOAD_GRACE_MS );
		} else {
			// Reveal what the server rendered as selected: these cards ship
			// hidden so the staged path can time their entrance, and nothing
			// else is going to un-hide them here.
			enter( visiblePanel() );
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () { select( i ); } );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;

				if ( 'ArrowRight' === event.key ) { next = i + 1; }
				else if ( 'ArrowLeft' === event.key ) { next = i - 1; }
				else if ( 'Home' === event.key ) { next = 0; }
				else if ( 'End' === event.key ) { next = tabs.length - 1; }

				if ( null === next ) { return; }

				event.preventDefault();
				next = ( next + tabs.length ) % tabs.length;
				tabs[ next ].focus();
				select( next );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var driven = document.querySelector( '.honest-market-map' );

		[].slice.call( document.querySelectorAll( '.honest-market' ) ).forEach( function ( root ) {
			setup( root, !! driven && root.contains( driven ) );
		} );
	} );
}() );
