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
		anim.addEventListener( 'DOMLoaded', function () { showSegment( 0 ); } );
	}

	window.HonestMarketMap = { init: init, showSegment: showSegment };

	document.addEventListener( 'DOMContentLoaded', function () {
		var el = document.querySelector( '.honest-market-map' );
		if ( el ) { init( el ); }
	} );
}() );
