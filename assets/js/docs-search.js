/**
 * Documentation Archive Search
 *
 * Handles live search on the documentation archive page using the REST API.
 */

( function() {
	const input = document.getElementById( 'docs-search-input' );
	const results = document.getElementById( 'docs-search-results' );
	const form = document.querySelector( '.search-form-inline' );

	if ( ! input || ! results || ! form || ! window.docSyncSearch ) {
		return;
	}

	const { restUrl, strings } = window.docSyncSearch;
	let debounceTimer = null;
	let abortController = null;

	function debounce( fn, delay ) {
		return function( ...args ) {
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( () => fn.apply( this, args ), delay );
		};
	}

	function showResults() {
		results.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
	}

	function hideResults() {
		results.hidden = true;
		input.setAttribute( 'aria-expanded', 'false' );
	}

	function renderLoading() {
		results.innerHTML = `<div class="docs-search-loading">${ strings.loading }</div>`;
		showResults();
	}

	function renderError() {
		results.innerHTML = `<div class="docs-search-error">${ strings.error }</div>`;
		showResults();
	}

	function renderEmpty( query ) {
		results.innerHTML = `<div class="docs-search-empty">${ strings.noResults.replace( '%s', query ) }</div>`;
		showResults();
	}

	function renderResults( items, total, query ) {
		if ( items.length === 0 ) {
			renderEmpty( query );
			return;
		}

		let html = '<ul class="docs-search-list">';

		items.forEach( item => {
			const projectBadge = item.project
				? `<span class="docs-search-project">${ item.project.name }</span>`
				: '';

			html += `
				<li class="docs-search-item" role="option">
					<a href="${ item.link }" class="docs-search-link">
						<span class="docs-search-title">${ item.title }</span>
						${ projectBadge }
						<span class="docs-search-excerpt">${ item.excerpt }</span>
					</a>
				</li>
			`;
		} );

		html += '</ul>';

		if ( total > 10 ) {
			const archiveUrl = new URL( window.location.href );
			archiveUrl.searchParams.set( 's', query );
			html += `<a href="${ archiveUrl.toString() }" class="docs-search-view-all">${ strings.viewAll.replace( '%d', total ) }</a>`;
		}

		results.innerHTML = html;
		showResults();
	}

	async function performSearch( query ) {
		if ( abortController ) {
			abortController.abort();
		}

		abortController = new AbortController();

		renderLoading();

		try {
			const url = new URL( restUrl );
			url.searchParams.set( 'search', query );
			url.searchParams.set( 'per_page', '10' );

			const response = await fetch( url.toString(), {
				signal: abortController.signal,
				headers: {
					'Content-Type': 'application/json',
				},
			} );

			if ( ! response.ok ) {
				throw new Error( 'Search failed' );
			}

			const data = await response.json();
			const total = parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 );

			const items = data.map( post => ( {
				id: post.id,
				title: post.title.rendered,
				excerpt: post.excerpt.rendered.replace( /<[^>]+>/g, '' ).trim().substring( 0, 100 ) + '...',
				link: post.link,
				project: post.project_info || null,
			} ) );

			renderResults( items, total, query );
		} catch ( error ) {
			if ( error.name === 'AbortError' ) {
				return;
			}
			renderError();
		}
	}

	const debouncedSearch = debounce( performSearch, 300 );

	input.addEventListener( 'input', function() {
		const query = this.value.trim();

		if ( query.length < 2 ) {
			hideResults();
			if ( abortController ) {
				abortController.abort();
			}
			return;
		}

		debouncedSearch( query );
	} );

	form.addEventListener( 'submit', function( e ) {
		e.preventDefault();
		const query = input.value.trim();

		if ( query.length >= 2 ) {
			if ( abortController ) {
				abortController.abort();
			}
			performSearch( query );
		}
	} );

	document.addEventListener( 'keydown', function( e ) {
		if ( e.key === 'Escape' && ! results.hidden ) {
			hideResults();
			input.blur();
		}
	} );

	document.addEventListener( 'click', function( e ) {
		if ( ! results.hidden && ! results.contains( e.target ) && e.target !== input ) {
			hideResults();
		}
	} );

	input.addEventListener( 'focus', function() {
		if ( this.value.trim().length >= 2 && results.innerHTML ) {
			showResults();
		}
	} );
} )();
