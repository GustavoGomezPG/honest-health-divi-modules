/**
 * Quote carousel controller for the Testimonials module.
 *
 * Only one slide is ever exposed to assistive tech at a time. That used to be
 * the `hidden` attribute, but `display: none` cannot crossfade, so the current
 * slide is marked with `is-current` and the stylesheet keeps the rest at
 * `opacity: 0; visibility: hidden` -- visibility being what pulls them from the
 * accessibility tree and the tab order, the job `hidden` was doing. The fade
 * itself is entirely CSS; this file only moves the class.
 *
 * Dots are real <button> elements; the active one carries `aria-current`
 * (removed on the rest, not set to "false" -- unlike aria-selected, aria-current
 * is not a required tri-state) rather than `aria-selected`, which belongs to tab
 * widgets, not carousels.
 *
 * Keyboard: Left/Right move between dots and activate the newly focused one,
 * Home/End jump to the first/last, mirroring the tab controller in
 * market-map.js. Clicking a dot does the same via the mouse.
 *
 * Autoplay yields to the visitor rather than competing with them:
 * - it pauses while the pointer is over the carousel and while focus is inside
 *   it, so a quote cannot slide away mid-read or mid-interaction;
 * - it stops entirely while the tab is in the background, where an interval
 *   would otherwise bank up advances that all fire on return;
 * - operating a dot restarts the interval instead of resuming it, so a
 *   deliberately chosen quote gets a full reading period rather than whatever
 *   was left of the previous one;
 * - `prefers-reduced-motion: reduce` disables it, leaving the dots as the only
 *   way slides change.
 *
 * Note for review: pausing on hover and focus is a mitigation, not a formal
 * pause control. WCAG 2.2.2 asks for an explicit pause/stop/hide mechanism for
 * anything that auto-updates for more than five seconds, and the design carries
 * only dots. Worth a decision if this section has to meet that criterion.
 */
( function () {
	'use strict';

	var DEFAULT_SLIDE_MS = 6000;
	var MIN_SLIDE_MS = 2000;
	var MAX_SLIDE_MS = 20000;

	// Mirrors the PHP validation: a value that is missing, non-numeric or outside
	// the range the field advertises falls back to the documented default rather
	// than being snapped to a bound. A 0 here would advance on every tick.
	function parseDuration( raw ) {
		var value = parseFloat( raw );

		if ( ! isFinite( value ) || value < MIN_SLIDE_MS || value > MAX_SLIDE_MS ) {
			return DEFAULT_SLIDE_MS;
		}

		return value;
	}

	function setup( root ) {
		var dots = [].slice.call( root.querySelectorAll( '.honest-testimonials__dot' ) );
		var slides = [].slice.call( root.querySelectorAll( '.honest-testimonials__slide' ) );

		if ( ! dots.length || ! slides.length ) { return; }

		var region = root.querySelector( '.honest-testimonials__region' );
		var reduced = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

		// Autoplay needs somewhere to go: with a single quote there is no next
		// slide, and a running interval would re-select the same one forever.
		var wants = !! region &&
			'off' !== region.getAttribute( 'data-autoplay' ) &&
			slides.length > 1 &&
			! reduced;

		var slideMs = region ? parseDuration( region.getAttribute( 'data-slide-duration' ) ) : DEFAULT_SLIDE_MS;
		var timer = null;
		var current = 0;
		var held = false;

		function stop() {
			if ( timer ) {
				clearInterval( timer );
				timer = null;
			}
		}

		// Restarts rather than resumes, so every dwell begins with a full
		// interval. `held` covers hover and focus; document.hidden covers the
		// background tab.
		function start() {
			stop();

			if ( ! wants || held || document.hidden ) { return; }

			timer = setInterval( function () {
				select( ( current + 1 ) % slides.length, false );
			}, slideMs );
		}

		function select( index, restart ) {
			if ( index < 0 || index >= slides.length ) { return; }

			current = index;

			dots.forEach( function ( dot, i ) {
				if ( i === index ) {
					dot.setAttribute( 'aria-current', 'true' );
				} else {
					dot.removeAttribute( 'aria-current' );
				}
			} );

			slides.forEach( function ( slide, i ) {
				slide.classList.toggle( 'is-current', i === index );
			} );

			if ( restart ) { start(); }
		}

		// Read where the server left things rather than assuming slide 0, so an
		// autoplay interval measured from here matches what is actually on screen.
		slides.forEach( function ( slide, i ) {
			if ( slide.classList.contains( 'is-current' ) ) { current = i; }
		} );

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () { select( i, true ); } );

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
				select( next, true );
			} );
		} );

		if ( ! wants ) { return; }

		function hold( on ) {
			held = on;

			if ( on ) {
				stop();
			} else {
				start();
			}
		}

		root.addEventListener( 'mouseenter', function () { hold( true ); } );
		root.addEventListener( 'mouseleave', function () { hold( false ); } );

		// focusin/focusout rather than focus/blur: those do not bubble, and focus
		// lands on a dot, not on the carousel root.
		root.addEventListener( 'focusin', function () { hold( true ); } );
		root.addEventListener( 'focusout', function () { hold( false ); } );

		document.addEventListener( 'visibilitychange', start );

		start();
	}

	// Booting is idempotent and repeatable rather than a one-shot on
	// DOMContentLoaded, because Divi's builder renders module HTML into its
	// preview iframe well after that event and replaces it on every settings
	// change. A one-shot boot leaves the carousel inert there: dots that do
	// nothing and no autoplay.
	//
	// The flag lives on the element, so a re-render -- which produces a new one --
	// is wired again automatically while the existing instance is left alone.
	function boot() {
		[].slice.call( document.querySelectorAll( '.honest-testimonials' ) ).forEach( function ( root ) {
			if ( root.honestTestimonialsReady ) { return; }
			root.honestTestimonialsReady = true;
			setup( root );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', boot );

	// Also boot immediately when the document is already past parsing, which is
	// the case inside the builder's iframe.
	if ( 'loading' !== document.readyState ) { boot(); }

	// Coalesced to one pass per batch of mutations. setTimeout and NOT
	// requestAnimationFrame: rAF does not fire in a hidden document, and the
	// builder builds its preview iframe offscreen -- a mutation arriving then
	// would latch the queued flag and no later mutation would ever be examined.
	if ( window.MutationObserver ) {
		var queued = false;
		new MutationObserver( function () {
			// Mid-parse a root can be visible to querySelector before its slides
			// are; DOMContentLoaded is about to boot it properly anyway.
			if ( 'loading' === document.readyState ) { return; }
			if ( queued ) { return; }
			queued = true;
			setTimeout( function () {
				queued = false;
				boot();
			}, 0 );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
