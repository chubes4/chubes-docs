/**
 * Admin Sync JavaScript
 *
 * Handles AJAX sync operations on the settings page.
 */

( function() {
	'use strict';

	const button = document.getElementById( 'chubes-docs-sync-all' );
	const status = document.getElementById( 'chubes-docs-sync-status' );

	if ( ! button || ! status ) {
		return;
	}

	button.addEventListener( 'click', function() {
		button.disabled = true;
		status.textContent = chubesDocsSync.strings.syncing;
		status.className = '';

		const data = new FormData();
		data.append( 'action', chubesDocsSync.syncAllAction );
		data.append( 'nonce', chubesDocsSync.nonce );

		fetch( chubesDocsSync.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( response => response.json() )
			.then( response => {
				button.disabled = false;

				if ( response.success ) {
					status.textContent = response.data.message;
					status.className = 'sync-success';

					if ( response.data.errors && response.data.errors.length > 0 ) {
						status.textContent += ' (' + response.data.errors.length + ' errors)';
						status.className = 'sync-warning';
					}
				} else {
					status.textContent = chubesDocsSync.strings.error + ' ' + response.data.message;
					status.className = 'sync-error';
				}
			} )
			.catch( error => {
				button.disabled = false;
				status.textContent = chubesDocsSync.strings.error + ' ' + error.message;
				status.className = 'sync-error';
			} );
	} );
} )();
