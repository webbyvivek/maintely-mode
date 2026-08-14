/**
 * Maintely Mode - Particle Background
 *
 * A small, dependency-free canvas particle field. Only loaded when the
 * "Particle Background" toggle is enabled. Particle count scales with
 * viewport size (capped) to stay lightweight on large screens, the
 * animation pauses while the tab is hidden, and it never runs at all
 * for visitors who have asked for reduced motion.
 */
( function () {
	'use strict';

	var canvas = document.getElementById( 'maintely-mode-particles' );

	if ( ! canvas || ! canvas.getContext ) {
		return;
	}

	var prefersReducedMotion = window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( prefersReducedMotion ) {
		return;
	}

	var ctx           = canvas.getContext( '2d' );
	var dpr           = window.devicePixelRatio || 1;
	var particles     = [];
	var width         = 0;
	var height        = 0;
	var color         = '#4f46e5';
	var rafId         = null;
	var resizeTimer   = null;
	var MAX_PARTICLES = 70;

	/**
	 * Read the current theme's accent color from the CSS custom
	 * property, so particles always match light/dark mode without any
	 * JS-side theme logic of their own.
	 */
	function readAccentColor() {
		var value = getComputedStyle( document.documentElement )
			.getPropertyValue( '--mwp-accent' )
			.trim();

		return value || color;
	}

	/**
	 * Size the canvas to the viewport, accounting for device pixel
	 * ratio so particles stay crisp on retina displays.
	 */
	function resizeCanvas() {
		width  = window.innerWidth;
		height = window.innerHeight;

		canvas.width  = width * dpr;
		canvas.height = height * dpr;
		canvas.style.width  = width + 'px';
		canvas.style.height = height + 'px';

		ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
	}

	/**
	 * Create a single particle with a random position, size, drift
	 * velocity, and opacity.
	 */
	function createParticle() {
		return {
			x: Math.random() * width,
			y: Math.random() * height,
			r: Math.random() * 1.6 + 0.6,
			vx: ( Math.random() - 0.5 ) * 0.25,
			vy: ( Math.random() - 0.5 ) * 0.25,
			alpha: Math.random() * 0.35 + 0.15
		};
	}

	/**
	 * (Re)build the particle field. Count scales with screen area so
	 * small screens never pay for a desktop-sized particle count.
	 */
	function initParticles() {
		resizeCanvas();
		color = readAccentColor();

		var target = Math.floor( ( width * height ) / 18000 );
		var count  = Math.max( 20, Math.min( MAX_PARTICLES, target ) );

		particles = [];
		for ( var i = 0; i < count; i++ ) {
			particles.push( createParticle() );
		}
	}

	/**
	 * Advance and redraw every particle for one animation frame.
	 */
	function step() {
		ctx.clearRect( 0, 0, width, height );
		ctx.fillStyle = color;

		for ( var i = 0; i < particles.length; i++ ) {
			var p = particles[ i ];

			p.x += p.vx;
			p.y += p.vy;

			if ( p.x < 0 ) {
				p.x = width;
			} else if ( p.x > width ) {
				p.x = 0;
			}

			if ( p.y < 0 ) {
				p.y = height;
			} else if ( p.y > height ) {
				p.y = 0;
			}

			ctx.globalAlpha = p.alpha;
			ctx.beginPath();
			ctx.arc( p.x, p.y, p.r, 0, Math.PI * 2 );
			ctx.fill();
		}

		ctx.globalAlpha = 1;
		rafId = window.requestAnimationFrame( step );
	}

	window.addEventListener( 'resize', function () {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( initParticles, 200 );
	} );

	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			window.cancelAnimationFrame( rafId );
			rafId = null;
		} else if ( ! rafId ) {
			rafId = window.requestAnimationFrame( step );
		}
	} );

	initParticles();
	rafId = window.requestAnimationFrame( step );
} )();
