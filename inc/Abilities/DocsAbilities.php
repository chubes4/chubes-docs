<?php
/**
 * Documentation Abilities
 *
 * Provides WP Abilities API integration for managing documentation posts
 * and project taxonomy cleanup. Enables documentation reset via WP-CLI, REST, or MCP.
 */

namespace ChubesDocs\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocsAbilities {

	public static function register(): void {
		wp_register_ability( 'chubes/reset-documentation', [
			'label'               => __( 'Reset Documentation', 'chubes-docs' ),
			'description'         => __( 'Deletes all documentation posts and child terms, preserving top-level projects with GitHub URLs', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success'                     => [
						'type'        => 'boolean',
						'description' => 'Whether the reset operation completed successfully',
					],
					'documentation_posts_deleted' => [
						'type'        => 'integer',
						'description' => 'Total number of documentation posts deleted',
					],
					'child_terms_deleted'         => [
						'type'        => 'integer',
						'description' => 'Number of child project terms deleted (depth >= 1)',
					],
					'orphaned_terms_deleted'      => [
						'type'        => 'integer',
						'description' => 'Number of orphaned top-level project terms deleted (no GitHub URL)',
					],
					'sync_metadata_reset'         => [
						'type'        => 'integer',
						'description' => 'Number of preserved project terms with sync metadata cleared',
					],
				],
			],
			'execute_callback'    => [ self::class, 'reset_documentation' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function reset_documentation(): array {
		// Step 1: Delete all documentation posts
		$posts_deleted = self::bulk_delete_documentation_posts();

		// Step 2: Delete ALL child terms (depth >= 1)
		$child_terms_deleted = self::delete_child_terms();

		// Step 3: Delete orphaned top-level terms (no GitHub URL)
		$orphaned_deleted = self::delete_orphaned_project_terms();

		// Step 4: Reset sync metadata on preserved terms
		$terms_reset = self::reset_sync_metadata();

		return [
			'success'                     => true,
			'documentation_posts_deleted' => $posts_deleted,
			'child_terms_deleted'         => $child_terms_deleted,
			'orphaned_terms_deleted'      => $orphaned_deleted,
			'sync_metadata_reset'         => $terms_reset,
		];
	}

	/**
	 * Delete all documentation posts
	 *
	 * @return int Number of posts deleted
	 */
	private static function bulk_delete_documentation_posts(): int {
		$posts = get_posts( [
			'post_type'      => 'documentation',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		if ( is_wp_error( $posts ) || empty( $posts ) ) {
			return 0;
		}

		$deleted_count = 0;
		foreach ( $posts as $post_id ) {
			$result = wp_delete_post( $post_id, true );
			if ( $result !== false && $result !== null ) {
				$deleted_count++;
			}
		}

		return $deleted_count;
	}

	/**
	 * Delete all child terms (depth >= 1)
	 * Deletes from deepest to shallowest to avoid orphan issues.
	 *
	 * @return int Number of terms deleted
	 */
	private static function delete_child_terms(): int {
		$deleted = 0;

		// Loop until no more child terms exist
		do {
			$child_terms = get_terms( [
				'taxonomy'   => 'project',
				'hide_empty' => false,
				'childless'  => true,
				'parent__not_in' => [ 0 ],
			] );

			if ( is_wp_error( $child_terms ) || empty( $child_terms ) ) {
				break;
			}

			foreach ( $child_terms as $term ) {
				$result = wp_delete_term( $term->term_id, 'project' );
				if ( $result && ! is_wp_error( $result ) ) {
					$deleted++;
				}
			}
		} while ( ! empty( $child_terms ) );

		return $deleted;
	}

	/**
	 * Delete orphaned top-level project terms (no GitHub URL)
	 *
	 * @return int Number of terms deleted
	 */
	private static function delete_orphaned_project_terms(): int {
		$orphaned = get_terms( [
			'taxonomy'   => 'project',
			'parent'     => 0,
			'hide_empty' => false,
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => 'project_github_url',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'project_github_url',
					'value'   => '',
					'compare' => '=',
				],
			],
		] );

		if ( is_wp_error( $orphaned ) || empty( $orphaned ) ) {
			return 0;
		}

		$deleted_count = 0;
		foreach ( $orphaned as $term ) {
			$result = wp_delete_term( $term->term_id, 'project' );
			if ( $result && ! is_wp_error( $result ) ) {
				$deleted_count++;
			}
		}

		return $deleted_count;
	}

	/**
	 * Reset sync metadata on preserved top-level project terms
	 *
	 * @return int Number of terms with metadata reset
	 */
	private static function reset_sync_metadata(): int {
		$projects = get_terms( [
			'taxonomy'   => 'project',
			'parent'     => 0,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $projects ) || empty( $projects ) ) {
			return 0;
		}

		$reset_count = 0;
		foreach ( $projects as $term ) {
			delete_term_meta( $term->term_id, 'project_last_sync_sha' );
			delete_term_meta( $term->term_id, 'project_last_sync_time' );
			delete_term_meta( $term->term_id, 'project_files_synced' );
			delete_term_meta( $term->term_id, 'project_sync_status' );
			delete_term_meta( $term->term_id, 'project_sync_error' );
			$reset_count++;
		}

		return $reset_count;
	}
}