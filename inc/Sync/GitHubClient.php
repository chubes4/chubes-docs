<?php
/**
 * GitHub API Client
 *
 * Handles all communication with the GitHub REST API for fetching
 * repository data, commit information, and file contents.
 */

namespace ChubesDocs\Sync;

class GitHubClient {

	private const API_BASE = 'https://api.github.com';

	private string $pat;

	public function __construct( string $pat ) {
		$this->pat = $pat;
	}

	/**
	 * Get the latest commit SHA for a branch.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo Repository name.
	 * @param string $branch Branch name.
	 * @return string|\WP_Error Commit SHA or WP_Error on failure.
	 */
	public function get_latest_commit_sha( string $owner, string $repo, string $branch = 'main' ) {
		$endpoint = "/repos/{$owner}/{$repo}/commits/{$branch}";
		$response = $this->request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['sha'] ) ) {
			return new \WP_Error( 'github_missing_sha', 'Commit SHA missing from response' );
		}

		return $response['sha'];
	}

	/**
	 * Get the file tree for a path at a specific ref.
	 * Returns only .md files within the docs/ directory.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo Repository name.
	 * @param string $path Directory path (default: 'docs').
	 * @param string $ref Git reference (branch, tag, or SHA).
	 * @return array Array of file info: ['relative_path' => ['path' => 'full/path', 'sha' => '...', 'size' => 123]]
	 */
	public function get_tree( string $owner, string $repo, string $path = 'docs', string $ref = 'main' ): array {
		$endpoint = "/repos/{$owner}/{$repo}/git/trees/{$ref}?recursive=1";
		$response = $this->request( $endpoint );

		if ( is_wp_error( $response ) || ! isset( $response['tree'] ) ) {
			return [];
		}

		$files = [];
		$path_prefix = empty( $path ) ? '' : rtrim( $path, '/' ) . '/';

		foreach ( $response['tree'] as $item ) {
			if ( $item['type'] !== 'blob' ) {
				continue;
			}

			if ( $path_prefix !== '' && strpos( $item['path'], $path_prefix ) !== 0 ) {
				continue;
			}

			if ( substr( $item['path'], -3 ) !== '.md' ) {
				continue;
			}

			// Skip root-level README.md when syncing from root
			if ( empty( $path ) && $item['path'] === 'README.md' ) {
				continue;
			}

			$relative_path = $path_prefix !== '' ? substr( $item['path'], strlen( $path_prefix ) ) : $item['path'];

			$files[ $relative_path ] = [
				'path' => $item['path'],
				'sha'  => $item['sha'],
				'size' => $item['size'] ?? 0,
			];
		}

		return $files;
	}

	/**
	 * Get raw file content from a repository.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo Repository name.
	 * @param string $path File path within the repository.
	 * @param string $ref Git reference (branch, tag, or SHA).
	 * @return string|null File content or null on failure.
	 */
	public function get_file_content( string $owner, string $repo, string $path, string $ref = 'main' ): ?string {
		$endpoint = "/repos/{$owner}/{$repo}/contents/{$path}?ref={$ref}";
		$response = $this->request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( isset( $response['content'] ) && isset( $response['encoding'] ) ) {
			if ( $response['encoding'] === 'base64' ) {
				return base64_decode( $response['content'] );
			}
		}

		return null;
	}

	/**
	 * Compare two commits and get changed files.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo Repository name.
	 * @param string $base Base commit SHA.
	 * @param string $head Head commit SHA.
	 * @param string $path Filter to files in this path (default: 'docs').
	 * @return array ['added' => [...], 'modified' => [...], 'removed' => [...], 'renamed' => [...]]
	 */
	public function compare_commits( string $owner, string $repo, string $base, string $head, string $path = 'docs' ): array {
		$endpoint = "/repos/{$owner}/{$repo}/compare/{$base}...{$head}";
		$response = $this->request( $endpoint );

		$result = [
			'added'    => [],
			'modified' => [],
			'removed'  => [],
			'renamed'   => [],
		];

		if ( is_wp_error( $response ) || ! isset( $response['files'] ) ) {
			return $result;
		}

		$path_prefix = empty( $path ) ? '' : rtrim( $path, '/' ) . '/';

		foreach ( $response['files'] as $file ) {
			if ( $path_prefix !== '' && strpos( $file['filename'], $path_prefix ) !== 0 ) {
				continue;
			}

			if ( substr( $file['filename'], -3 ) !== '.md' ) {
				continue;
			}

			// Skip root-level README.md when syncing from root
			if ( empty( $path ) && $file['filename'] === 'README.md' ) {
				continue;
			}

			$relative_path = $path_prefix !== '' ? substr( $file['filename'], strlen( $path_prefix ) ) : $file['filename'];

			switch ( $file['status'] ) {
				case 'added':
					$result['added'][] = $relative_path;
					break;
				case 'modified':
					$result['modified'][] = $relative_path;
					break;
				case 'removed':
					$result['removed'][] = $relative_path;
					break;
				case 'renamed':
					$previous = $path_prefix !== '' ? substr( $file['previous_filename'], strlen( $path_prefix ) ) : $file['previous_filename'];
					$result['renamed'][] = [
						'previous' => $previous,
						'new'      => $relative_path,
					];
					break;
			}
		}

		return $result;
	}

	/**
	 * Parse owner and repo from a GitHub URL.
	 *
	 * @param string $url GitHub repository URL.
	 * @return array|null ['owner' => '...', 'repo' => '...'] or null if invalid.
	 */
	public static function parse_repo_url( string $url ): ?array {
		$patterns = [
			'#github\.com/([^/]+)/([^/]+?)(?:\.git)?(?:/|$)#i',
			'#github\.com:([^/]+)/([^/]+?)(?:\.git)?$#i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $matches ) ) {
				$repo = $matches[2];
				if ( str_ends_with( $repo, '.git' ) ) {
					$repo = substr( $repo, 0, -4 );
				}
				return [
					'owner' => $matches[1],
					'repo'  => $repo,
				];
			}
		}

		return null;
	}

	/**
	 * Test the connection and return diagnostic info.
	 *
	 * @return array|\WP_Error Connection info or WP_Error.
	 */
	public function test_connection(): array|\WP_Error {
		$response = $this->request_with_headers( '/user' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$headers = $response['headers'];
		$body = $response['body'];

		$orgs_response = $this->request_with_headers( '/user/orgs' );
		$orgs = [];
		if ( ! is_wp_error( $orgs_response ) && is_array( $orgs_response['body'] ) ) {
			foreach ( $orgs_response['body'] as $org ) {
				$orgs[] = $org['login'] ?? 'unknown';
			}
		}

		return [
			'user'          => $body['login'] ?? 'unknown',
			'scopes'        => array_map( 'trim', explode( ',', $headers['x-oauth-scopes'] ?? '' ) ),
			'orgs'          => $orgs,
			'saml_enforced' => isset( $headers['x-github-sso'] ),
			'saml_message'  => $headers['x-github-sso'] ?? null,
			'rate_limit'    => $headers['x-ratelimit-remaining'] ?? 'unknown',
		];
	}

	/**
	 * Test access to a specific repository.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo Repository name.
	 * @return array|\WP_Error Repository info or WP_Error with details.
	 */
	public function test_repo( string $owner, string $repo ): array|\WP_Error {
		$response = $this->request_with_headers( "/repos/{$owner}/{$repo}" );

		if ( is_wp_error( $response ) ) {
			$data = $response->get_error_data();
			return new \WP_Error(
				$response->get_error_code(),
				$response->get_error_message(),
				[
					'status'      => $data['status'] ?? 'unknown',
					'headers'     => $data['headers'] ?? [],
					'sso_url'     => $data['headers']['x-github-sso'] ?? null,
				]
			);
		}

		$body = $response['body'];

		return [
			'success'     => true,
			'full_name'   => $body['full_name'] ?? "{$owner}/{$repo}",
			'private'     => $body['private'] ?? false,
			'default_branch' => $body['default_branch'] ?? 'main',
			'permissions' => $body['permissions'] ?? [],
		];
	}

	/**
	 * Get the default branch for a repository.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo  Repository name.
	 * @return string|null Default branch name or null on failure.
	 */
	public function get_default_branch( string $owner, string $repo ): ?string {
		$endpoint = "/repos/{$owner}/{$repo}";
		$response = $this->request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		return $response['default_branch'] ?? null;
	}

	/**
	 * Get repository description from GitHub.
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo Repository name.
	 * @return string|null Description or null on failure/empty.
	 */
	public function get_repo_description( string $owner, string $repo ): ?string {
		$endpoint = "/repos/{$owner}/{$repo}";
		$response = $this->request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$description = $response['description'] ?? null;

		return ! empty( $description ) ? $description : null;
	}

	/**
	 * Make an authenticated request to the GitHub API.
	 *
	 * @param string $endpoint API endpoint (relative to API_BASE).
	 * @param string $method HTTP method.
	 * @return array|\WP_Error Response data or WP_Error on failure.
	 */
	private function request( string $endpoint, string $method = 'GET' ) {
		$response = $this->request_with_headers( $endpoint, $method );
		return is_wp_error( $response ) ? $response : $response['body'];
	}

	/**
	 * Make an authenticated request and return both body and headers.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @return array|\WP_Error ['body' => ..., 'headers' => ...] or WP_Error.
	 */
	private function request_with_headers( string $endpoint, string $method = 'GET' ) {
		$url = self::API_BASE . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Accept'               => 'application/vnd.github+json',
				'Authorization'        => 'Bearer ' . $this->pat,
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'ChubesDocs/1.0',
			],
		];

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$headers = wp_remote_retrieve_headers( $response )->getAll();
		$body    = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = $body['message'] ?? "HTTP {$code}";

			if ( $code === 404 && isset( $headers['x-github-sso'] ) ) {
				$message .= ' (SAML SSO authorization required for this organization)';
			}

			return new \WP_Error( 'github_api_error', $message, [
				'status'  => $code,
				'headers' => $headers,
			] );
		}

		return [
			'body'    => $body,
			'headers' => $headers,
		];
	}
}
