<?php
/**
 * Sync Trigger Controller
 *
 * Generic endpoint to trigger a documentation sync. Accepts three input
 * formats so any caller — GitHub webhooks, Data Machine, curl, CI/CD,
 * n8n, or any HTTP client — can trigger a project sync with one POST.
 *
 * Input formats (checked in order):
 *   1. { "project": "<slug>" }           — sync by project slug
 *   2. { "repo_url": "<github_url>" }    — sync by repository URL
 *   3. GitHub push event payload         — auto-detected by payload shape
 *
 * Authentication (at least one required):
 *   - Authorization: Bearer <token>      — matches docsync_sync_token option
 *   - x-hub-signature-256: sha256=<sig>  — validates against docsync_webhook_secret
 *
 * @package DocSync
 * @since 1.0.0
 */

namespace DocSync\Api\Controllers;

use DocSync\Core\Project;
use DocSync\PluginConfig;
use DocSync\Sync\CronSync;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SyncTriggerController {

	/**
	 * Option key for the Bearer token used to authenticate sync triggers.
	 */
	const OPTION_SYNC_TOKEN = 'docsync_sync_token';

	/**
	 * Option key for the GitHub webhook secret (x-hub-signature-256).
	 */
	const OPTION_WEBHOOK_SECRET = 'docsync_webhook_secret';

	/**
	 * Validate the request has a valid Bearer token or GitHub signature.
	 *
	 * This runs as the permission_callback for the route. It does NOT
	 * require a logged-in WordPress user — the token IS the credential.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return bool|WP_Error True if authenticated, WP_Error otherwise.
	 */
	public static function check_auth( WP_REST_Request $request ): bool|WP_Error {
		// Try Bearer token first.
		$token = self::get_option( self::OPTION_SYNC_TOKEN );
		if ( ! empty( $token ) ) {
			$auth_header = $request->get_header( 'Authorization' );
			if ( $auth_header && str_starts_with( $auth_header, 'Bearer ' ) ) {
				$provided = substr( $auth_header, 7 );
				if ( hash_equals( $token, $provided ) ) {
					return true;
				}
			}
		}

		// Try GitHub webhook signature.
		$secret = self::get_option( self::OPTION_WEBHOOK_SECRET );
		if ( ! empty( $secret ) ) {
			$signature = $request->get_header( 'x-hub-signature-256' );
			if ( $signature && str_starts_with( $signature, 'sha256=' ) ) {
				$expected = 'sha256=' . hash_hmac( 'sha256', $request->get_body(), $secret );
				if ( hash_equals( $expected, $signature ) ) {
					return true;
				}
			}
		}

		// Neither method authenticated.
		$has_token  = ! empty( $token );
		$has_secret = ! empty( $secret );

		if ( ! $has_token && ! $has_secret ) {
			return new WP_Error(
				'sync_not_configured',
				'No sync token or webhook secret configured. Set one in DocSync settings.',
				[ 'status' => 403 ]
			);
		}

		return new WP_Error(
			'sync_unauthorized',
			'Invalid authentication. Provide a valid Bearer token or GitHub webhook signature.',
			[ 'status' => 401 ]
		);
	}

	/**
	 * Handle the sync trigger request.
	 *
	 * Detects the input format, resolves the project, runs the sync,
	 * and returns structured results.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Response with sync results.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$term = self::resolve_project( $request );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$result = CronSync::sync_term( $term->term_id );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'sync_failed',
				$result['error'] ?? 'Sync failed',
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( [
			'success'   => true,
			'project'   => $term->slug,
			'term_id'   => $term->term_id,
			'added'     => count( $result['added'] ?? [] ),
			'updated'   => count( $result['updated'] ?? [] ),
			'removed'   => count( $result['removed'] ?? [] ),
			'unchanged' => count( $result['unchanged'] ?? [] ),
		] );
	}

	/**
	 * Resolve which project to sync from the request payload.
	 *
	 * Checks in order:
	 *   1. "project" param (slug)
	 *   2. "repo_url" param (GitHub URL → term meta lookup)
	 *   3. GitHub push payload shape (repository.html_url)
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return \WP_Term|WP_Error The resolved project term, or error.
	 */
	private static function resolve_project( WP_REST_Request $request ): \WP_Term|WP_Error {
		$params = $request->get_json_params();

		// 1. By project slug.
		$slug = $params['project'] ?? null;
		if ( ! empty( $slug ) ) {
			return self::find_project_by_slug( sanitize_text_field( $slug ) );
		}

		// 2. By repo URL.
		$repo_url = $params['repo_url'] ?? null;
		if ( ! empty( $repo_url ) ) {
			return self::find_project_by_repo_url( esc_url_raw( $repo_url ) );
		}

		// 3. GitHub push event payload.
		$github_url = $params['repository']['html_url'] ?? null;
		if ( ! empty( $github_url ) ) {
			return self::find_project_by_repo_url( esc_url_raw( $github_url ) );
		}

		return new WP_Error(
			'sync_no_project',
			'Could not determine which project to sync. Provide "project" (slug), "repo_url", or a GitHub push payload.',
			[ 'status' => 400 ]
		);
	}

	/**
	 * Find a project term by its slug.
	 *
	 * @param string $slug Project slug.
	 * @return \WP_Term|WP_Error The term, or error if not found.
	 */
	private static function find_project_by_slug( string $slug ): \WP_Term|WP_Error {
		$term = get_term_by( 'slug', $slug, Project::TAXONOMY );

		if ( ! $term ) {
			return new WP_Error(
				'sync_project_not_found',
				"Project '{$slug}' not found.",
				[ 'status' => 404 ]
			);
		}

		// Ensure the term has a GitHub URL configured.
		$github_url = get_term_meta( $term->term_id, 'project_github_url', true );
		if ( empty( $github_url ) ) {
			return new WP_Error(
				'sync_no_github_url',
				"Project '{$slug}' has no GitHub URL configured.",
				[ 'status' => 400 ]
			);
		}

		return $term;
	}

	/**
	 * Find a project term by its GitHub repository URL.
	 *
	 * Normalizes the URL (strips trailing slashes, .git suffix) and
	 * searches term meta for a match.
	 *
	 * @param string $repo_url GitHub repository URL.
	 * @return \WP_Term|WP_Error The term, or error if not found.
	 */
	private static function find_project_by_repo_url( string $repo_url ): \WP_Term|WP_Error {
		$normalized = self::normalize_github_url( $repo_url );

		$terms = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'     => 'project_github_url',
					'compare' => 'EXISTS',
				],
			],
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return new WP_Error(
				'sync_project_not_found',
				'No projects with GitHub URLs configured.',
				[ 'status' => 404 ]
			);
		}

		foreach ( $terms as $term ) {
			$stored_url = get_term_meta( $term->term_id, 'project_github_url', true );
			if ( self::normalize_github_url( $stored_url ) === $normalized ) {
				return $term;
			}
		}

		return new WP_Error(
			'sync_project_not_found',
			"No project found matching repository URL: {$repo_url}",
			[ 'status' => 404 ]
		);
	}

	/**
	 * Normalize a GitHub URL for comparison.
	 *
	 * Strips trailing slashes, .git suffix, and lowercases.
	 *
	 * @param string $url GitHub URL.
	 * @return string Normalized URL.
	 */
	private static function normalize_github_url( string $url ): string {
		$url = strtolower( trim( $url ) );
		$url = rtrim( $url, '/' );
		$url = preg_replace( '/\.git$/', '', $url );

		return $url;
	}

	/**
	 * Get an option value (wrapper for testability).
	 *
	 * @param string $key    Option key.
	 * @param mixed  $default Default value.
	 * @return mixed Option value.
	 */
	private static function get_option( string $key, mixed $default = '' ): mixed {
		return get_option( $key, $default );
	}
}
