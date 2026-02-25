/**
 * Admin Sync JavaScript
 *
 * Handles sync operations via REST API on settings and term edit pages.
 */

document.addEventListener( 'DOMContentLoaded', function() {
	'use strict';

	const headers = {
		'X-WP-Nonce': docSyncAdmin.nonce,
		'Content-Type': 'application/json',
	};

	// Settings Page: Sync All Button
	const syncAllButton = document.getElementById( 'docsync-sync-all' );
	const syncAllStatus = document.getElementById( 'docsync-sync-status' );

	if ( syncAllButton && syncAllStatus ) {
		syncAllButton.addEventListener( 'click', function() {
			syncAllButton.disabled = true;
			syncAllStatus.textContent = docSyncAdmin.strings.syncing;
			syncAllStatus.className = '';

			fetch( docSyncAdmin.restUrl + '/abilities/docsync/sync-docs-batch/run', {
				method: 'POST',
				headers: headers,
				body: JSON.stringify( {} ),
				credentials: 'same-origin',
			} )
				.then( response => response.json() )
				.then( data => {
					syncAllButton.disabled = false;

					if ( data.errors && data.errors.length > 0 ) {
						syncAllStatus.textContent = 'Synced ' + data.repos_synced + ' repos (' + data.errors.length + ' errors)';
						syncAllStatus.className = 'sync-warning';
					} else {
						syncAllStatus.textContent = 'Synced ' + data.repos_synced + ' repos';
						syncAllStatus.className = 'sync-success';
					}
				} )
				.catch( error => {
					syncAllButton.disabled = false;
					syncAllStatus.textContent = docSyncAdmin.strings.error + ' ' + error.message;
					syncAllStatus.className = 'sync-error';
				} );
		} );
	}

	// Settings Page: Test Token Button
	const testTokenButton = document.getElementById( 'docsync-test-token' );
	const testTokenResults = document.getElementById( 'docsync-test-results' );

	if ( testTokenButton && testTokenResults ) {
		testTokenButton.addEventListener( 'click', function() {
			testTokenButton.disabled = true;
			testTokenResults.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>' + docSyncAdmin.strings.testing;

			fetch( docSyncAdmin.restUrl + '/sync/test-token', {
				method: 'GET',
				headers: headers,
				credentials: 'same-origin',
			} )
				.then( response => response.json() )
				.then( data => {
					testTokenButton.disabled = false;

					if ( data.user ) {
						let html = '<div style="background:#f0f6fb; border-left:4px solid #2271b1; padding:10px; margin-top:10px;">';
						html += '<p><strong>User:</strong> <code>' + data.user + '</code></p>';
						html += '<p><strong>Scopes:</strong> <code>' + data.scopes.join( ', ' ) + '</code></p>';
						html += '<p><strong>Organizations:</strong> ' + ( data.orgs.length > 0 ? '<code>' + data.orgs.join( '</code>, <code>' ) + '</code>' : '<em>None visible</em>' ) + '</p>';
						html += '<p><strong>Rate Limit:</strong> ' + data.rate_limit + ' remaining</p>';

						if ( data.saml_enforced ) {
							html += '<p style="color:#d63638;"><strong>SAML SSO:</strong> Authorization required for some organizations.</p>';
						} else {
							html += '<p style="color:#00a32a;"><strong>SAML SSO:</strong> No enforcement detected on /user.</p>';
						}

						html += '</div>';
						testTokenResults.innerHTML = html;
					} else if ( data.code ) {
						testTokenResults.innerHTML = '<div style="color:#d63638; padding:10px; background:#fcf0f1; border-left:4px solid #d63638; margin-top:10px;">' + data.message + '</div>';
					}
				} )
				.catch( error => {
					testTokenButton.disabled = false;
					testTokenResults.innerHTML = '<div style="color:#d63638;">Error: ' + error.message + '</div>';
				} );
		} );
	}

	// Term Page: Sync Now Button
	const termSyncButton = document.querySelector( '.docsync-term-sync' );
	const termTestButton = document.querySelector( '.docsync-term-test' );
	const termResults = document.getElementById( 'docsync-term-results' );

	if ( termSyncButton && termResults ) {
		termSyncButton.addEventListener( 'click', function() {
			const termId = termSyncButton.dataset.termId;
			if ( ! termId ) {
				termResults.innerHTML = '<div style="color:#d63638;">No term ID found.</div>';
				return;
			}

			termSyncButton.disabled = true;
			if ( termTestButton ) termTestButton.disabled = true;
			termResults.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>' + docSyncAdmin.strings.syncing;

			fetch( docSyncAdmin.restUrl + '/abilities/docsync/sync-docs/run', {
				method: 'POST',
				headers: headers,
				body: JSON.stringify( { term_id: parseInt( termId ) } ),
				credentials: 'same-origin',
			} )
				.then( response => response.json() )
				.then( data => {
					termSyncButton.disabled = false;
					if ( termTestButton ) termTestButton.disabled = false;

					if ( data.success ) {
						let html = '<div style="background:#f0faf0; border-left:4px solid #00a32a; padding:10px; margin-top:10px;">';
						html += '<p style="color:#00a32a; margin:0 0 8px;"><strong>' + docSyncAdmin.strings.success + '</strong></p>';
						html += '<p style="margin:0;">Added: ' + data.added.length + ', Updated: ' + data.updated.length + ', Removed: ' + data.removed.length + (data.renamed.length > 0 ? ', Renamed: ' + data.renamed.length : '') + '</p>';
						html += '</div>';
						termResults.innerHTML = html;
					} else if ( data.error ) {
						let html = '<div style="background:#fcf0f1; border-left:4px solid #d63638; padding:10px; margin-top:10px;">';
						html += '<p style="color:#d63638; margin:0;"><strong>' + docSyncAdmin.strings.error + '</strong> ' + data.error + '</p>';
						html += '</div>';
						termResults.innerHTML = html;
					}
				} )
				.catch( error => {
					termSyncButton.disabled = false;
					if ( termTestButton ) termTestButton.disabled = false;
					termResults.innerHTML = '<div style="color:#d63638;">Error: ' + error.message + '</div>';
				} );
		} );
	}

	// Term Page: Test Connection Button
	if ( termTestButton && termResults ) {
		termTestButton.addEventListener( 'click', function() {
			const repoUrl = termTestButton.dataset.repoUrl;
			if ( ! repoUrl ) {
				termResults.innerHTML = '<div style="color:#d63638;">No repository URL configured.</div>';
				return;
			}

			termTestButton.disabled = true;
			if ( termSyncButton ) termSyncButton.disabled = true;
			termResults.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>' + docSyncAdmin.strings.testingRepo;

			fetch( docSyncAdmin.restUrl + '/sync/test-repo', {
				method: 'POST',
				headers: headers,
				credentials: 'same-origin',
				body: JSON.stringify( { repo_url: repoUrl } ),
			} )
				.then( response => response.json() )
				.then( data => {
					termTestButton.disabled = false;
					if ( termSyncButton ) termSyncButton.disabled = false;

					if ( data.success || data.full_name ) {
						let html = '<div style="background:#f0faf0; border-left:4px solid #00a32a; padding:10px; margin-top:10px;">';
						html += '<p style="color:#00a32a; margin:0 0 8px;"><strong>Success!</strong> Repository is accessible.</p>';
						html += '<p><strong>Full Name:</strong> <code>' + data.full_name + '</code></p>';
						html += '<p><strong>Default Branch:</strong> <code>' + data.default_branch + '</code></p>';
						html += '<p><strong>Private:</strong> ' + ( data.private ? 'Yes' : 'No' ) + '</p>';
						if ( data.permissions ) {
							html += '<p><strong>Permissions:</strong> admin=' + ( data.permissions.admin ? 'yes' : 'no' ) + ', push=' + ( data.permissions.push ? 'yes' : 'no' ) + ', pull=' + ( data.permissions.pull ? 'yes' : 'no' ) + '</p>';
						}
						html += '</div>';
						termResults.innerHTML = html;
					} else if ( data.code ) {
						const errData = data.data || {};
						let html = '<div style="background:#fcf0f1; border-left:4px solid #d63638; padding:10px; margin-top:10px;">';
						html += '<p style="color:#d63638; margin:0 0 8px;"><strong>Failed:</strong> ' + data.message + '</p>';
						if ( errData.owner && errData.repo ) {
							html += '<p><strong>Parsed as:</strong> <code>' + errData.owner + '/' + errData.repo + '</code></p>';
						}
						if ( errData.status ) {
							html += '<p><strong>HTTP Status:</strong> ' + errData.status + '</p>';
						}
						if ( errData.sso_url ) {
							html += '<p style="color:#d63638;"><strong>SSO Required:</strong> <a href="' + errData.sso_url + '" target="_blank">Authorize token for this organization</a></p>';
						}
						html += '</div>';
						termResults.innerHTML = html;
					}
				} )
				.catch( error => {
					termTestButton.disabled = false;
					if ( termSyncButton ) termSyncButton.disabled = false;
					termResults.innerHTML = '<div style="color:#d63638;">Error: ' + error.message + '</div>';
				} );
		} );
	}
} );
