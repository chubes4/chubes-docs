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
	 * @return string|null Commit SHA or null on failure.
	 */
	public function get_latest_commit_sha( string $owner, string $repo, string $branch = 'main' ): ?string {
		$endpoint = "/repos/{$owner}/{$repo}/commits/{$branch}";
		$response = $this->request( $endpoint );

		if ( is_wp_error( $response ) || ! isset( $response['sha'] ) ) {
			return null;
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
		$path_prefix = rtrim( $path, '/' ) . '/';

		foreach ( $response['tree'] as $item ) {
			if ( $item['type'] !== 'blob' ) {
				continue;
			}

			if ( strpos( $item['path'], $path_prefix ) !== 0 ) {
				continue;
			}

			if ( substr( $item['path'], -3 ) !== '.md' ) {
				continue;
			}

			$relative_path = substr( $item['path'], strlen( $path_prefix ) );

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
	 * @return array ['added' => [...], 'modified' => [...], 'removed' => [...]]
	 */
	public function compare_commits( string $owner, string $repo, string $base, string $head, string $path = 'docs' ): array {
		$endpoint = "/repos/{$owner}/{$repo}/compare/{$base}...{$head}";
		$response = $this->request( $endpoint );

		$result = [
			'added'    => [],
			'modified' => [],
			'removed'  => [],
		];

		if ( is_wp_error( $response ) || ! isset( $response['files'] ) ) {
			return $result;
		}

		$path_prefix = rtrim( $path, '/' ) . '/';

		foreach ( $response['files'] as $file ) {
			if ( strpos( $file['filename'], $path_prefix ) !== 0 ) {
				continue;
			}

			if ( substr( $file['filename'], -3 ) !== '.md' ) {
				continue;
			}

			$relative_path = substr( $file['filename'], strlen( $path_prefix ) );

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
					$result['removed'][] = substr( $file['previous_filename'], strlen( $path_prefix ) );
					$result['added'][] = $relative_path;
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
				return [
					'owner' => $matches[1],
					'repo'  => rtrim( $matches[2], '.git' ),
				];
			}
		}

		return null;
	}

	/**
	 * Make an authenticated request to the GitHub API.
	 *
	 * @param string $endpoint API endpoint (relative to API_BASE).
	 * @param string $method HTTP method.
	 * @return array|\WP_Error Response data or WP_Error on failure.
	 */
	private function request( string $endpoint, string $method = 'GET' ) {
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

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = $data['message'] ?? "HTTP {$code}";
			return new \WP_Error( 'github_api_error', $message, [ 'status' => $code ] );
		}

		return $data;
	}
}
