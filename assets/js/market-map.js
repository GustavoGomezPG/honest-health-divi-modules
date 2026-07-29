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
			}
			return;
		}

		if ( ! play( displayed, true ) ) { return; }

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

	window.HonestMarketMap = { init: init, showSegment: showSegment };

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
 * map's text alternative says) and asks HonestMarketMap for the animation. It
 * deliberately does not touch the driver's internals -- rapid clicking is
 * already handled there by retargeting the in-flight reversal, so every click
 * can just fire showSegment() and the panel/caption swap stays instant.
 *
 * The driver is single-instance by design (one `anim`, bound to the first
 * `.honest-market-map` on the page). Only the module containing that element
 * drives the map; any further instance still gets working tabs, panels and
 * captions rather than dead buttons.
 */
( function () {
	'use strict';

	function setup( root, drivesMap ) {
		var tabs = [].slice.call( root.querySelectorAll( '.honest-market__tab' ) );

		if ( ! tabs.length ) { return; }

		var captions = [].slice.call( root.querySelectorAll( '.honest-market__caption' ) );
		var map = root.querySelector( '.honest-market-map' );

		function select( index ) {
			if ( index < 0 || index >= tabs.length ) { return; }

			tabs.forEach( function ( tab, i ) {
				var on = i === index;
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', on ? '0' : '-1' );
				var panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );
				if ( panel ) { panel.hidden = ! on; }
			} );

			captions.forEach( function ( caption, i ) {
				caption.hidden = i !== index;
			} );

			if ( map ) {
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

			if ( drivesMap && window.HonestMarketMap ) {
				var segment = parseInt( tabs[ index ].getAttribute( 'data-segment' ), 10 );
				window.HonestMarketMap.showSegment( isNaN( segment ) ? index : segment );
			}
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
