/**
 * Dot-driven quote carousel controller for the Testimonials module.
 *
 * Only one slide is ever exposed to assistive tech at a time: every slide
 * that is not current carries the `hidden` attribute (not just a visual
 * "display: none" class), so it is pulled from both the accessibility tree
 * and the tab order. Dots are real <button> elements; the active one carries
 * `aria-current` (removed on the rest, not set to "false" -- unlike
 * aria-selected, aria-current is not a required tri-state) rather than
 * `aria-selected`, which belongs to tab widgets, not carousels.
 *
 * Keyboard: Left/Right move between dots and activate the newly focused one,
 * Home/End jump to the first/last, mirroring the tab controller in
 * market-map.js. Clicking a dot does the same via the mouse.
 *
 * No autoplay: the brief did not require it, and offering it would have
 * required a visible pause control and prefers-reduced-motion handling. That
 * is a deliberate omission, not an oversight -- slides only change when a
 * visitor operates a dot.
 */
( function () {
	'use strict';

	function setup( root ) {
		var dots = [].slice.call( root.querySelectorAll( '.honest-testimonials__dot' ) );
		var slides = [].slice.call( root.querySelectorAll( '.honest-testimonials__slide' ) );

		if ( ! dots.length || ! slides.length ) { return; }

		function select( index ) {
			if ( index < 0 || index >= dots.length ) { return; }

			dots.forEach( function ( dot, i ) {
				if ( i === index ) {
					dot.setAttribute( 'aria-current', 'true' );
				} else {
					dot.removeAttribute( 'aria-current' );
				}
			} );

			slides.forEach( function ( slide, i ) {
				slide.hidden = i !== index;
			} );
		}

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () { select( i ); } );

			dot.addEventListener( 'keydown', function ( event ) {
				var next = null;

				if ( 'ArrowRight' === event.key ) { next = i + 1; }
				else if ( 'ArrowLeft' === event.key ) { next = i - 1; }
				else if ( 'Home' === event.key ) { next = 0; }
				else if ( 'End' === event.key ) { next = dots.length - 1; }

				if ( null === next ) { return; }

				event.preventDefault();
				next = ( next + dots.length ) % dots.length;
				dots[ next ].focus();
				select( next );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		[].slice.call( document.querySelectorAll( '.honest-testimonials' ) ).forEach( setup );
	} );
}() );
