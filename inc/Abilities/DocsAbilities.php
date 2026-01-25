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
			'description'         => __( 'Deletes all documentation posts and orphaned project terms, preserving active projects with GitHub URLs', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success' => [
						'type'        => 'boolean',
						'description' => 'Whether the reset operation completed successfully',
					],
					'active_projects_preserved' => [
						'type'        => 'integer',
						'description' => 'Number of active project terms preserved (those with GitHub URLs)',
					],
					'orphaned_terms_deleted' => [
						'type'        => 'integer',
						'description' => 'Number of orphaned project terms deleted (those without GitHub URLs)',
					],
					'documentation_posts_deleted' => [
						'type'        => 'integer',
						'description' => 'Total number of documentation posts deleted',
					],
				],
			],
			'execute_callback'    => [ self::class, 'reset_documentation' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function reset_documentation(): array {
		// Step 1: Get active project terms (depth=0 with github_url)
		$active_projects = self::get_active_project_terms();

		// Step 2: Delete orphaned project terms (depth=0 without github_url)
		$orphaned_deleted = self::delete_orphaned_project_terms();

		// Step 3: Delete all documentation posts
		$posts_deleted = self::bulk_delete_documentation_posts();

		return [
			'success'                     => true,
			'active_projects_preserved' => count( $active_projects ),
			'orphaned_terms_deleted'      => $orphaned_deleted,
			'documentation_posts_deleted' => $posts_deleted,
		];
	}

	/**
	 * Get active project terms (depth=0 with non-empty github_url)
	 *
	 * @return array Array of WP_Term objects
	 */
	private static function get_active_project_terms(): array {
		return get_terms( [
			'taxonomy'   => 'project',
			'parent'     => 0,
			'meta_query' => [
				[
					'key'     => 'project_github_url',
					'value'   => '',
					'compare' => '!=',
					'type'    => 'CHAR',
				],
			],
			'hide_empty' => false,
		] );
	}

	/**
	 * Delete orphaned project terms (depth=0 without github_url)
	 *
	 * @return int Number of terms deleted
	 */
	private static function delete_orphaned_project_terms(): int {
		$orphaned = get_terms( [
			'taxonomy'   => 'project',
			'parent'     => 0,
			'meta_query' => [
				[
					'key'     => 'project_github_url',
					'compare' => 'NOT EXISTS',
				],
			],
			'hide_empty' => false,
		] );

		if ( is_wp_error( $orphaned ) || empty( $orphaned ) ) {
			return 0;
		}

		$deleted_count = 0;
		foreach ( $orphaned as $term ) {
			$result = wp_delete_term( $term->term_id, 'project' );
			if ( $result !== false && $result !== null ) {
				$deleted_count++;
			}
		}

		return $deleted_count;
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
}