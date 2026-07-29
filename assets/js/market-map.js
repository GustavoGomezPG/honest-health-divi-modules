/**
 * Lottie playback driver for the animated US market map.
 *
 * The Lottie composition (assets/lottie/market-map.json) contains four
 * segments in one timeline, one per market, in a fixed order: West,
 * Southwest, Midwest, East (assets/lottie/market-map-segments.json).
 * Each segment plays an empty outlined map, then that market's states
 * popping in, then the region label.
 *
 * Switching markets reverses the currently playing segment back to the
 * shared empty-map base, then plays the next segment forward once the
 * reversal completes. Guard frames between segments in the source asset
 * ensure one segment's layers are fully retired before the next begins.
 *
 * Exposes a global `HonestMarketMap` with `init(container)` and
 * `showSegment(index)`, where `index` is the 0-based position in the
 * segments array (not the manifest's 1-based `index` field).
 */
( function () {
	'use strict';

	var anim = null;
	var segments = [];
	var current = -1;

	function play( index, reverse ) {
		var seg = segments[ index ];
		if ( ! anim || ! seg ) { return; }
		anim.setDirection( reverse ? -1 : 1 );
		anim.playSegments( reverse ? [ seg.out, seg.in ] : [ seg.in, seg.out ], true );
	}

	function showSegment( index ) {
		if ( index === current ) { return; }
		if ( current === -1 ) {
			current = index;
			play( index, false );
			return;
		}
		var previous = current;
		current = index;
		play( previous, true );
		anim.addEventListener( 'complete', function handler() {
			anim.removeEventListener( 'complete', handler );
			play( index, false );
		} );
	}

	function init( container ) {
		if ( ! window.lottie || ! container ) { return; }
		segments = JSON.parse( container.getAttribute( 'data-segments' ) || '[]' );
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
