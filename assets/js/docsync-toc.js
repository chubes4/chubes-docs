/**
 * DocSync Table of Contents — scroll spy and smooth navigation.
 *
 * Highlights the current section in the TOC sidebar as the user scrolls.
 * Uses IntersectionObserver for performant scroll tracking.
 */
( function() {
	'use strict';

	const toc = document.querySelector( '.docsync-toc' );
	if ( ! toc ) {
		return;
	}

	const links = toc.querySelectorAll( '.docsync-toc-link' );
	if ( ! links.length ) {
		return;
	}

	const ACTIVE_CLASS = 'docsync-toc-active';

	// Collect target sections.
	const sections = [];
	links.forEach( function( link ) {
		const id = link.getAttribute( 'href' ).replace( '#', '' );
		const el = document.getElementById( id );
		if ( el ) {
			sections.push( { id: id, el: el, link: link } );
		}
	} );

	if ( ! sections.length ) {
		return;
	}

	// Scroll spy via IntersectionObserver.
	let currentActive = null;

	const observer = new IntersectionObserver(
		function( entries ) {
			entries.forEach( function( entry ) {
				if ( entry.isIntersecting ) {
					const section = sections.find( function( s ) {
						return s.el === entry.target;
					} );

					if ( section && section.link !== currentActive ) {
						if ( currentActive ) {
							currentActive.classList.remove( ACTIVE_CLASS );
						}
						section.link.classList.add( ACTIVE_CLASS );
						currentActive = section.link;
					}
				}
			} );
		},
		{
			rootMargin: '-80px 0px -60% 0px',
			threshold: 0,
		}
	);

	sections.forEach( function( section ) {
		observer.observe( section.el );
	} );

	// Smooth scroll on click.
	links.forEach( function( link ) {
		link.addEventListener( 'click', function( e ) {
			e.preventDefault();
			const id = this.getAttribute( 'href' ).replace( '#', '' );
			const target = document.getElementById( id );
			if ( target ) {
				target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				history.replaceState( null, '', '#' + id );
			}
		} );
	} );
} )();
