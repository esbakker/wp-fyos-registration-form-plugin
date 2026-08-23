/**
 * FOYS Registration Form — portal fixer.
 *
 * Every FOYS/Bootstrap selector is scoped to `.registration-form`, which keeps
 * the form's CSS out of the theme. BootstrapVue, however, renders its overlays
 * outside the form: the "person already exists" modal goes into a container it
 * appends to <body>, and the `v-b-popover` help bubbles are appended to <body>
 * directly. Both land outside that scope, so they render unstyled.
 *
 * There are therefore two cases to repair:
 *
 *   1. A container whose *descendant* is the overlay (modals). Adding the scope
 *      class to the container is enough — `.registration-form .modal` matches.
 *   2. The overlay *itself* is the body child (popovers, tooltips). A class on
 *      the element cannot help, because the scoped rules are descendant
 *      selectors. These are moved into one shared host element that carries the
 *      scope class. The host is unstyled and unpositioned, so it changes no
 *      layout: the overlays are absolutely positioned and their containing
 *      block is unchanged. BootstrapVue removes its overlays via
 *      `parentNode.removeChild`, so relocating them is safe.
 *
 * The signature is deliberately BootstrapVue-specific: it keys off the ids and
 * class names the library generates (`__bv_popover_7__`, `…__BV_modal_outer_`,
 * `b-popover`), never off generic Bootstrap classes a theme might also use.
 * Only nodes added to <body> after page load are considered, so existing theme
 * markup is never touched.
 */
( function () {
	'use strict';

	// BootstrapVue uses `__bv_` for the elements it generates and `__BV_` for
	// ids derived from a component id, so both cases have to be listed.
	var SIGNATURE = [
		'[id*="__bv_"]',
		'[id*="__BV_"]',
		'[id^="__BVID__"]',
		'.b-popover',
		'.b-tooltip',
		'.b-toast',
		'.b-toaster',
		'.b-sidebar'
	].join( ',' );

	var TAGGED = 'frf-portal';
	var SCOPE = 'registration-form';

	if ( ! window.MutationObserver || ! document.body ) {
		return;
	}

	/** Containers seen being added to <body>, still waiting for content. */
	var pending = [];

	/** Shared, unstyled host for overlays that are body children themselves. */
	var host = null;

	function matches( el, selector ) {
		var fn = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
		return fn ? fn.call( el, selector ) : false;
	}

	function scopeHost() {
		if ( ! host || ! host.parentNode ) {
			host = document.createElement( 'div' );
			host.className = SCOPE + ' ' + TAGGED;
			host.setAttribute( 'data-frf-portal-host', '' );
			document.body.appendChild( host );
		}
		return host;
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

			// Case 2 — the overlay itself. Move it under the scope host.
			if ( matches( el, SIGNATURE ) ) {
				el.classList.add( TAGGED );
				scopeHost().appendChild( el );
				continue;
			}

			// Case 1 — a container holding the overlay.
			if ( el.querySelector( SIGNATURE ) ) {
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

				// Only direct children of <body> are candidate portals, and
				// never anything already inside the scope (the form, the host).
				if (
					node.parentNode === document.body &&
					! node.classList.contains( SCOPE ) &&
					! node.classList.contains( TAGGED )
				) {
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
