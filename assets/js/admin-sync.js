/**
 * Admin Sync JavaScript
 *
 * Handles AJAX sync operations on the settings page.
 */

document.addEventListener( 'DOMContentLoaded', function() {
	'use strict';

	const button = document.getElementById( 'chubes-docs-sync-all' );
	const status = document.getElementById( 'chubes-docs-sync-status' );

	if ( button && status ) {
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
	}

	// Test Token
	const testButton = document.getElementById( 'chubes-docs-test-token' );
	const testResults = document.getElementById( 'chubes-docs-test-results' );

	if ( testButton && testResults ) {
		testButton.addEventListener( 'click', function() {
			testButton.disabled = true;
			testResults.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>' + chubesDocsSync.strings.testing;

			const data = new FormData();
			data.append( 'action', chubesDocsSync.testTokenAction );
			data.append( 'nonce', chubesDocsSync.nonce );

			fetch( chubesDocsSync.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( response => response.json() )
				.then( response => {
					testButton.disabled = false;
					if ( response.success ) {
						const d = response.data;
						let html = '<div style="background:#f0f6fb; border-left:4px solid #2271b1; padding:10px; margin-top:10px;">';
						html += '<p><strong>User:</strong> <code>' + d.user + '</code></p>';
						html += '<p><strong>Scopes:</strong> <code>' + d.scopes.join( ', ' ) + '</code></p>';
						html += '<p><strong>Organizations:</strong> ' + ( d.orgs.length > 0 ? '<code>' + d.orgs.join( '</code>, <code>' ) + '</code>' : '<em>None visible</em>' ) + '</p>';
						html += '<p><strong>Rate Limit:</strong> ' + d.rate_limit + ' remaining</p>';
						
						if ( d.saml_enforced ) {
							html += '<p style="color:#d63638;"><strong>SAML SSO:</strong> Authorization required for some organizations.</p>';
						} else {
							html += '<p style="color:#00a32a;"><strong>SAML SSO:</strong> No enforcement detected on /user.</p>';
						}
						
						html += '</div>';
						testResults.innerHTML = html;
					} else {
						testResults.innerHTML = '<div style="color:#d63638; padding:10px; background:#fcf0f1; border-left:4px solid #d63638; margin-top:10px;">' + response.data.message + '</div>';
					}
				} )
				.catch( error => {
					testButton.disabled = false;
					testResults.innerHTML = '<div style="color:#d63638;">Error: ' + error.message + '</div>';
				} );
		} );
	}

	// Test Specific Repo (Settings Page)
	const testRepoButton = document.getElementById( 'chubes-docs-test-repo' );
	const testRepoUrl = document.getElementById( 'chubes-docs-test-repo-url' );
	const testRepoResults = document.getElementById( 'chubes-docs-test-repo-results' );

	if ( testRepoButton && testRepoUrl && testRepoResults ) {
		testRepoButton.addEventListener( 'click', function() {
			const url = testRepoUrl.value.trim();
			if ( ! url ) {
				testRepoResults.innerHTML = '<div style="color:#d63638;">Please enter a repository URL.</div>';
				return;
			}

			testRepoButton.disabled = true;
			testRepoResults.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>' + chubesDocsSync.strings.testingRepo;

			const data = new FormData();
			data.append( 'action', chubesDocsSync.testRepoAction );
			data.append( 'nonce', chubesDocsSync.nonce );
			data.append( 'repo_url', url );

			fetch( chubesDocsSync.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( response => response.json() )
				.then( response => {
					testRepoButton.disabled = false;
					if ( response.success ) {
						const d = response.data;
						let html = '<div style="background:#f0faf0; border-left:4px solid #00a32a; padding:10px; margin-top:10px;">';
						html += '<p style="color:#00a32a; margin:0 0 8px;"><strong>Success!</strong> Repository is accessible.</p>';
						html += '<p><strong>Full Name:</strong> <code>' + d.full_name + '</code></p>';
						html += '<p><strong>Default Branch:</strong> <code>' + d.default_branch + '</code></p>';
						html += '<p><strong>Private:</strong> ' + ( d.private ? 'Yes' : 'No' ) + '</p>';
						if ( d.permissions ) {
							html += '<p><strong>Permissions:</strong> admin=' + ( d.permissions.admin ? 'yes' : 'no' ) + ', push=' + ( d.permissions.push ? 'yes' : 'no' ) + ', pull=' + ( d.permissions.pull ? 'yes' : 'no' ) + '</p>';
						}
						html += '</div>';
						testRepoResults.innerHTML = html;
					} else {
						const d = response.data;
						let html = '<div style="background:#fcf0f1; border-left:4px solid #d63638; padding:10px; margin-top:10px;">';
						html += '<p style="color:#d63638; margin:0 0 8px;"><strong>Failed:</strong> ' + d.message + '</p>';
						html += '<p><strong>Parsed as:</strong> <code>' + d.owner + '/' + d.repo + '</code></p>';
						html += '<p><strong>HTTP Status:</strong> ' + d.status + '</p>';
						if ( d.sso_url ) {
							html += '<p style="color:#d63638;"><strong>SSO Required:</strong> <a href="' + d.sso_url + '" target="_blank">Authorize token for this organization</a></p>';
						}
						html += '</div>';
						testRepoResults.innerHTML = html;
					}
				} )
				.catch( error => {
					testRepoButton.disabled = false;
					testRepoResults.innerHTML = '<div style="color:#d63638;">Error: ' + error.message + '</div>';
				} );
		} );
	}

	// Term Page: Sync Now Button
	const termSyncButton = document.querySelector( '.chubes-docs-term-sync' );
	const termTestButton = document.querySelector( '.chubes-docs-term-test' );
	const termResults = document.getElementById( 'chubes-docs-term-results' );

	if ( termSyncButton && termResults ) {
		termSyncButton.addEventListener( 'click', function() {
			const termId = termSyncButton.dataset.termId;
			if ( ! termId ) {
				termResults.innerHTML = '<div style="color:#d63638;">No term ID found.</div>';
				return;
			}

			termSyncButton.disabled = true;
			if ( termTestButton ) termTestButton.disabled = true;
			termResults.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>' + chubesDocsSync.strings.syncing;

			const data = new FormData();
			data.append( 'action', chubesDocsSync.syncTermAction );
			data.append( 'nonce', chubesDocsSync.nonce );
			data.append( 'term_id', termId );
			data.append( 'force', 'true' );

			fetch( chubesDocsSync.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( response => response.json() )
				.then( response => {
					termSyncButton.disabled = false;
					if ( termTestButton ) termTestButton.disabled = false;

					if ( response.success ) {
						let html = '<div style="background:#f0faf0; border-left:4px solid #00a32a; padding:10px; margin-top:10px;">';
						html += '<p style="color:#00a32a; margin:0 0 8px;"><strong>' + chubesDocsSync.strings.success + '</strong></p>';
						html += '<p style="margin:0;">' + response.data.message + '</p>';
						html += '</div>';
						termResults.innerHTML = html;
					} else {
						let html = '<div style="background:#fcf0f1; border-left:4px solid #d63638; padding:10px; margin-top:10px;">';
						html += '<p style="color:#d63638; margin:0;"><strong>' + chubesDocsSync.strings.error + '</strong> ' + response.data.message + '</p>';
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
			termResults.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span>' + chubesDocsSync.strings.testingRepo;

			const data = new FormData();
			data.append( 'action', chubesDocsSync.testRepoAction );
			data.append( 'nonce', chubesDocsSync.nonce );
			data.append( 'repo_url', repoUrl );

			fetch( chubesDocsSync.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( response => response.json() )
				.then( response => {
					termTestButton.disabled = false;
					if ( termSyncButton ) termSyncButton.disabled = false;

					if ( response.success ) {
						const d = response.data;
						let html = '<div style="background:#f0faf0; border-left:4px solid #00a32a; padding:10px; margin-top:10px;">';
						html += '<p style="color:#00a32a; margin:0 0 8px;"><strong>Success!</strong> Repository is accessible.</p>';
						html += '<p><strong>Full Name:</strong> <code>' + d.full_name + '</code></p>';
						html += '<p><strong>Default Branch:</strong> <code>' + d.default_branch + '</code></p>';
						html += '<p><strong>Private:</strong> ' + ( d.private ? 'Yes' : 'No' ) + '</p>';
						if ( d.permissions ) {
							html += '<p><strong>Permissions:</strong> admin=' + ( d.permissions.admin ? 'yes' : 'no' ) + ', push=' + ( d.permissions.push ? 'yes' : 'no' ) + ', pull=' + ( d.permissions.pull ? 'yes' : 'no' ) + '</p>';
						}
						html += '</div>';
						termResults.innerHTML = html;
					} else {
						const d = response.data;
						let html = '<div style="background:#fcf0f1; border-left:4px solid #d63638; padding:10px; margin-top:10px;">';
						html += '<p style="color:#d63638; margin:0 0 8px;"><strong>Failed:</strong> ' + d.message + '</p>';
						html += '<p><strong>Parsed as:</strong> <code>' + d.owner + '/' + d.repo + '</code></p>';
						html += '<p><strong>HTTP Status:</strong> ' + d.status + '</p>';
						if ( d.sso_url ) {
							html += '<p style="color:#d63638;"><strong>SSO Required:</strong> <a href="' + d.sso_url + '" target="_blank">Authorize token for this organization</a></p>';
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
