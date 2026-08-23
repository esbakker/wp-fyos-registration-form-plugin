/**
 * FOYS Registration Form — portal fixer.
 *
 * Every FOYS/Bootstrap selector is scoped to `.registration-form`, which keeps
 * the form's CSS out of the theme. BootstrapVue, however, moves its overlays
 * (the "person already exists" modal, tooltips, toasts) to a container it
 * appends to <body> — outside that scope, so they render unstyled.
 *
 * This script watches for those containers and tags them with
 * `registration-form frf-portal`, putting them back inside the scope.
 *
 * The signature is deliberately BootstrapVue-specific: it keys off the ids the
 * library generates (`…__BV_modal_outer_`, `__BVID__12`), never off generic
 * Bootstrap classes a theme might also use. Only nodes added to <body> after
 * page load are considered, so existing theme markup is never touched.
 */
( function () {
	'use strict';

	var SIGNATURE = '[id*="__BV_"],[id^="__BVID__"],.b-toast,.b-toaster,.b-sidebar';
	var TAGGED = 'frf-portal';
	var SCOPE = 'registration-form';

	if ( ! window.MutationObserver || ! document.body ) {
		return;
	}

	/** Containers seen being added to <body>, still waiting for content. */
	var pending = [];

	function matches( el, selector ) {
		var fn = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
		return fn ? fn.call( el, selector ) : false;
	}

	function isPortal( el ) {
		return matches( el, SIGNATURE ) || !! el.querySelector( SIGNATURE );
	}

	function tag( el ) {
		el.classList.add( SCOPE );
		el.classList.add( TAGGED );
	}

	/**
	 * Re-check the containers we are still waiting on. A transported modal is
	 * mounted into its container a tick after the container is appended, so a
	 * node that looked empty on arrival can become a portal later.
	 */
	function sweep() {
		var still = [];

		for ( var i = 0; i < pending.length; i++ ) {
			var el = pending[ i ];

			if ( ! el.parentNode || el.classList.contains( TAGGED ) ) {
				continue;
			}

			if ( isPortal( el ) ) {
				tag( el );
				continue;
			}

			still.push( el );
		}

		// Anything that never received content is dropped after a while, so
		// the list cannot grow unbounded on a long-lived page.
		pending = still.slice( -20 );
	}

	var scheduled = false;

	function schedule() {
		if ( scheduled ) {
			return;
		}
		scheduled = true;
		window.setTimeout( function () {
			scheduled = false;
			sweep();
		}, 0 );
	}

	var observer = new MutationObserver( function ( mutations ) {
		var touched = false;

		for ( var i = 0; i < mutations.length; i++ ) {
			var added = mutations[ i ].addedNodes;

			for ( var j = 0; j < added.length; j++ ) {
				var node = added[ j ];

				if ( 1 !== node.nodeType ) {
					continue;
				}

				// Only direct children of <body> are candidate portals.
				if ( node.parentNode === document.body && ! node.classList.contains( SCOPE ) ) {
					pending.push( node );
					touched = true;
					continue;
				}

				// Content landing inside a container we are already watching.
				if ( pending.length ) {
					touched = true;
				}
			}
		}

		if ( touched ) {
			schedule();
		}
	} );

	observer.observe( document.body, { childList: true, subtree: true } );
} )();
