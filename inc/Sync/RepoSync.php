<?php
/**
 * Repository Sync Orchestrator
 *
 * Handles the complete sync process for a single GitHub repository,
 * including fetching files, processing content, and updating WordPress.
 */

namespace ChubesDocs\Sync;

use ChubesDocs\Core\Project;

class RepoSync {

	private GitHubClient $github;

	public function __construct( GitHubClient $github ) {
		$this->github = $github;
	}

	/**
	 * Sync documentation for a codebase term.
	 *
	 * @param int $term_id The codebase term ID (project level, depth 1).
	 * @return array Sync results with counts and details.
	 */
	public function sync( int $term_id ): array {
		$result = [
			'success'       => false,
			'term_id'       => $term_id,
			'added'         => [],
			'updated'       => [],
			'removed'       => [],
			'renamed'       => [],
			'unchanged'     => [],
			'terms_created' => [],
			'error'         => null,
			'old_sha'       => null,
			'new_sha'       => null,
		];

		$this->update_sync_status( $term_id, 'syncing' );

		$github_url = get_term_meta( $term_id, 'project_github_url', true );
		if ( empty( $github_url ) ) {
			$result['error'] = 'No GitHub URL configured';
			$this->update_sync_status( $term_id, 'failed', $result['error'] );
			return $result;
		}

		$repo_info = GitHubClient::parse_repo_url( $github_url );
		if ( ! $repo_info ) {
			$result['error'] = 'Invalid GitHub URL format';
			$this->update_sync_status( $term_id, 'failed', $result['error'] );
			return $result;
		}

		$owner = $repo_info['owner'];
		$repo  = $repo_info['repo'];

		$new_sha = $this->github->get_latest_commit_sha( $owner, $repo, 'main' );
		if ( is_wp_error( $new_sha ) ) {
			$result['error'] = 'GitHub API: ' . $new_sha->get_error_message();
			$this->update_sync_status( $term_id, 'failed', $result['error'] );
			return $result;
		}

		if ( ! $new_sha ) {
			$result['error'] = 'Failed to fetch latest commit from GitHub';
			$this->update_sync_status( $term_id, 'failed', $result['error'] );
			return $result;
		}

		$result['new_sha'] = $new_sha;
		$old_sha = get_term_meta( $term_id, 'project_last_sync_sha', true );
		$result['old_sha'] = $old_sha ?: null;

		// Sync repository description to term
		$description = $this->github->get_repo_description( $owner, $repo );
		if ( $description !== null ) {
			wp_update_term( $term_id, Project::TAXONOMY, [
				'description' => $description,
			] );
		}

		if ( $old_sha === $new_sha ) {
			$result['success'] = true;
			$result['error'] = 'No changes detected';
			return $result;
		}

		if ( empty( $old_sha ) ) {
			$sync_result = $this->full_sync( $term_id, $owner, $repo, $new_sha );
		} else {
			$sync_result = $this->incremental_sync( $term_id, $owner, $repo, $old_sha, $new_sha );
		}

		$result = array_merge( $result, $sync_result );

		if ( $result['success'] ) {
			update_term_meta( $term_id, 'project_last_sync_sha', $new_sha );
			update_term_meta( $term_id, 'project_last_sync_time', time() );
			$files_synced = count( $result['added'] ) + count( $result['updated'] ) + count( $result['unchanged'] );
			update_term_meta( $term_id, 'project_files_synced', $files_synced );
			$this->update_sync_status( $term_id, 'success' );
		} else {
			$this->update_sync_status( $term_id, 'failed', $result['error'] ?? 'Unknown error' );
		}

		return $result;
	}

	/**
	 * Perform a full sync of all docs in the repository.
	 *
	 * @param int    $term_id Project term ID.
	 * @param string $owner   Repository owner.
	 * @param string $repo    Repository name.
	 * @param string $sha     Commit SHA.
	 * @return array Sync results.
	 */
	private function full_sync( int $term_id, string $owner, string $repo, string $sha ): array {
		$result = [
			'success'       => true,
			'added'         => [],
			'updated'       => [],
			'removed'       => [],
			'unchanged'     => [],
			'terms_created' => [],
			'error'         => null,
		];

		$files = $this->github->get_tree( $owner, $repo, 'docs', $sha );
		if ( empty( $files ) ) {
			$result['error'] = 'No documentation files found in docs/ directory';
			$result['success'] = false;
			return $result;
		}

		$synced_source_files = [];

		foreach ( $files as $relative_path => $file_info ) {
			$process_result = $this->process_file( $term_id, $owner, $repo, $relative_path, $sha );

			if ( $process_result['success'] ) {
				$synced_source_files[] = $relative_path;

				switch ( $process_result['action'] ) {
					case 'created':
						$result['added'][] = $relative_path;
						break;
					case 'updated':
						$result['updated'][] = $relative_path;
						break;
					case 'unchanged':
						$result['unchanged'][] = $relative_path;
						break;
				}

				if ( ! empty( $process_result['terms_created'] ) ) {
					$result['terms_created'] = array_merge( $result['terms_created'], $process_result['terms_created'] );
				}
			} else {
				$result['error'] = $process_result['error'];
				$result['success'] = false;
			}
		}

		$orphans = $this->find_orphaned_posts( $term_id, $synced_source_files );
		foreach ( $orphans as $orphan ) {
			if ( $this->delete_post( $orphan['id'] ) ) {
				$result['removed'][] = $orphan['source_file'];
			}
		}

		return $result;
	}

	/**
	 * Perform an incremental sync based on changed files.
	 *
	 * @param int    $term_id Project term ID.
	 * @param string $owner   Repository owner.
	 * @param string $repo    Repository name.
	 * @param string $old_sha Previous commit SHA.
	 * @param string $new_sha New commit SHA.
	 * @return array Sync results.
	 */
	private function incremental_sync( int $term_id, string $owner, string $repo, string $old_sha, string $new_sha ): array {
		$result = [
			'success'       => true,
			'added'         => [],
			'updated'       => [],
			'removed'       => [],
			'renamed'       => [],
			'unchanged'     => [],
			'terms_created' => [],
			'error'         => null,
		];

		$changes = $this->github->compare_commits( $owner, $repo, $old_sha, $new_sha, 'docs' );

		$files_to_process = array_merge( $changes['added'], $changes['modified'] );

		foreach ( $files_to_process as $relative_path ) {
			$process_result = $this->process_file( $term_id, $owner, $repo, $relative_path, $new_sha );

			if ( $process_result['success'] ) {
				switch ( $process_result['action'] ) {
					case 'created':
						$result['added'][] = $relative_path;
						break;
					case 'updated':
						$result['updated'][] = $relative_path;
						break;
					case 'unchanged':
						$result['unchanged'][] = $relative_path;
						break;
				}

				if ( ! empty( $process_result['terms_created'] ) ) {
					$result['terms_created'] = array_merge( $result['terms_created'], $process_result['terms_created'] );
				}
			} else {
				$result['error'] = $process_result['error'];
				$result['success'] = false;
			}
		}

		foreach ( $changes['removed'] as $relative_path ) {
			if ( $this->delete_by_source_file( $term_id, $relative_path ) ) {
				$result['removed'][] = $relative_path;
			}
		}

		foreach ( $changes['renamed'] ?? [] as $rename ) {
			$post_id = SyncManager::find_post_by_source( $rename['previous'], $term_id );
			if ( $post_id ) {
				update_post_meta( $post_id, '_sync_source_file', $rename['new'] );
				$process_result = $this->process_file( $term_id, $owner, $repo, $rename['new'], $new_sha );
				if ( $process_result['success'] ) {
					$result['renamed'][] = $rename['new'];
				} else {
					$result['error'] = $process_result['error'];
					$result['success'] = false;
				}
			}
		}

		return $result;

	}

	/**
	 * Process a single documentation file.
	 *
	 * @param int    $term_id       Project term ID.
	 * @param string $owner         Repository owner.
	 * @param string $repo          Repository name.
	 * @param string $relative_path Relative path within docs/.
	 * @param string $ref           Git reference.
	 * @return array Process result.
	 */
	private function process_file( int $term_id, string $owner, string $repo, string $relative_path, string $ref ): array {
		$result = [
			'success'       => false,
			'action'        => null,
			'terms_created' => [],
			'error'         => null,
		];

		$full_path = 'docs/' . $relative_path;
		$content = $this->github->get_file_content( $owner, $repo, $full_path, $ref );

		if ( $content === null ) {
			$result['error'] = "Failed to fetch file content: {$relative_path}";
			return $result;
		}

		$title = $this->extract_title( $content );
		if ( ! $title ) {
			$result['error'] = "No H1 header found in: {$relative_path}";
			return $result;
		}

		$content_body = $this->strip_first_h1( $content );
		$subpath = $this->build_subpath( $relative_path );
		$filesize = strlen( $content );
		$timestamp = gmdate( 'c' );

		$sync_result = SyncManager::sync_post(
			$relative_path,
			$title,
			$content_body,
			$term_id,
			$filesize,
			$timestamp,
			$subpath,
			'',
			true
		);

		if ( $sync_result['success'] ) {
			$result['success'] = true;
			$result['action'] = $sync_result['action'];
		} else {
			$result['error'] = $sync_result['error'] ?? 'Unknown sync error';
		}

		return $result;
	}

	/**
	 * Extract the title from the first H1 header.
	 *
	 * @param string $markdown Markdown content.
	 * @return string|null Title or null if not found.
	 */
	private function extract_title( string $markdown ): ?string {
		if ( preg_match( '/^#\s+(.+)/mu', $markdown, $matches ) ) {
			return trim( $matches[1] );
		}
		return null;
	}

	/**
	 * Strip the first H1 header from markdown content.
	 *
	 * @param string $markdown Markdown content.
	 * @return string Content without first H1.
	 */
	private function strip_first_h1( string $markdown ): string {
		return preg_replace( '/^#\s+.+\n*/mu', '', $markdown, 1 );
	}

	/**
	 * Build subpath array from relative path.
	 * Converts directory structure to taxonomy term hierarchy.
	 *
	 * @param string $relative_path Relative path (e.g., 'guides/advanced/config.md').
	 * @return array Subpath array (e.g., ['Guides', 'Advanced']).
	 */
	private function build_subpath( string $relative_path ): array {
		$subpath = [];
		$dir_parts = explode( '/', dirname( $relative_path ) );

		foreach ( $dir_parts as $part ) {
			if ( $part !== '.' && ! empty( $part ) ) {
				$clean_part = str_replace( [ '-', '_' ], ' ', $part );
				$subpath[] = mb_convert_case( $clean_part, MB_CASE_TITLE, 'UTF-8' );
			}
		}

		return $subpath;
	}

	/**
	 * Find orphaned posts that no longer exist in the source.
	 *
	 * @param int   $term_id           Project term ID.
	 * @param array $synced_source_files Array of source files that were synced.
	 * @return array Array of orphaned posts with 'id' and 'source_file'.
	 */
	private function find_orphaned_posts( int $term_id, array $synced_source_files ): array {
		$orphans = [];

		$term = get_term( $term_id, Project::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return $orphans;
		}

		$posts = get_posts( [
			'post_type'      => 'documentation',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'tax_query'      => [
				[
					'taxonomy'         => Project::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $term_id,
					'include_children' => true,
				],
			],
			'fields' => 'ids',
		] );

		foreach ( $posts as $post_id ) {
			$source_file = get_post_meta( $post_id, '_sync_source_file', true );
			if ( $source_file && ! in_array( $source_file, $synced_source_files, true ) ) {
				$orphans[] = [
					'id'          => $post_id,
					'source_file' => $source_file,
				];
			}
		}

		return $orphans;
	}

	/**
	 * Delete a post by its source file path.
	 *
	 * @param string $source_file Source file path.
	 * @return bool True if deleted, false otherwise.
	 */
	private function delete_by_source_file( int $term_id, string $source_file ): bool {
		$post_id = SyncManager::find_post_by_source( $source_file, $term_id );
		if ( $post_id ) {
			return $this->delete_post( $post_id );
		}
		return false;
	}

	/**
	 * Delete a post by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if deleted, false otherwise.
	 */
	private function delete_post( int $post_id ): bool {
		$result = wp_delete_post( $post_id, true );
		return $result !== false && $result !== null;
	}

	/**
	 * Update sync status for a term.
	 *
	 * @param int         $term_id Term ID.
	 * @param string      $status  Status: 'syncing', 'success', 'failed'.
	 * @param string|null $error   Error message if failed.
	 */
	private function update_sync_status( int $term_id, string $status, ?string $error = null ): void {
		update_term_meta( $term_id, 'project_sync_status', $status );
		if ( $error ) {
			update_term_meta( $term_id, 'project_sync_error', $error );
		} elseif ( $status === 'success' ) {
			delete_term_meta( $term_id, 'project_sync_error' );
		}
	}
}
