<?php
/**
 * Documentation Abilities
 *
 * Provides WP Abilities API integration for managing documentation posts
 * and project taxonomy cleanup. Enables documentation reset via WP-CLI, REST, or MCP.
 */

namespace ChubesDocs\Abilities;

use ChubesDocs\Core\Documentation;
use ChubesDocs\Core\Project;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocsAbilities {

	public static function register(): void {
		self::register_get_doc();
		self::register_reset_documentation();
	}

	private static function register_get_doc(): void {
		wp_register_ability( 'chubes/get-doc', [
			'label'               => __( 'Get Documentation', 'chubes-docs' ),
			'description'         => __( 'Fetch a single documentation post by ID or slug', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => 'Post ID',
					],
					'slug' => [
						'type'        => 'string',
						'description' => 'Post slug',
					],
					'format' => [
						'type'        => 'string',
						'description' => 'Content format: "markdown" (default) or "html"',
						'enum'        => [ 'markdown', 'html' ],
						'default'     => 'markdown',
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'id'           => [ 'type' => 'integer' ],
					'title'        => [ 'type' => 'string' ],
					'content'      => [ 'type' => 'string' ],
					'excerpt'      => [ 'type' => 'string' ],
					'link'         => [ 'type' => 'string' ],
					'project'      => [ 'type' => [ 'object', 'null' ] ],
					'project_type' => [ 'type' => [ 'object', 'null' ] ],
					'meta'         => [ 'type' => 'object' ],
				],
			],
			'execute_callback'    => [ self::class, 'get_doc_callback' ],
			'permission_callback' => '__return_true',
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function get_doc_callback( array $input ): array {
		$post = null;

		if ( ! empty( $input['id'] ) ) {
			$post = get_post( absint( $input['id'] ) );
		} elseif ( ! empty( $input['slug'] ) ) {
			$posts = get_posts( [
				'post_type'      => Documentation::POST_TYPE,
				'post_status'    => 'publish',
				'name'           => sanitize_title( $input['slug'] ),
				'posts_per_page' => 1,
			] );
			$post = ! empty( $posts ) ? $posts[0] : null;
		}

		if ( ! $post || $post->post_type !== Documentation::POST_TYPE ) {
			return [ 'error' => 'Documentation not found' ];
		}

		$terms        = get_the_terms( $post->ID, Project::TAXONOMY );
		$project_term = $terms && ! is_wp_error( $terms ) ? Project::get_project_term( $terms ) : null;
		$project_data = null;
		$project_type_data = null;

		if ( $project_term ) {
			$project_data = [
				'id'   => $project_term->term_id,
				'name' => $project_term->name,
				'slug' => $project_term->slug,
			];

			$type_slug = Project::get_project_type( $project_term );
			if ( $type_slug ) {
				$type_term = get_term_by( 'slug', $type_slug, 'project_type' );
				if ( $type_term && ! is_wp_error( $type_term ) ) {
					$project_type_data = [
						'id'   => $type_term->term_id,
						'name' => $type_term->name,
						'slug' => $type_term->slug,
					];
				}
			}
		}

		$excerpt = $post->post_excerpt;
		if ( empty( $excerpt ) && ! empty( $post->post_content ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
		}

		// Markdown-only output contract.
		$markdown = get_post_meta( $post->ID, '_sync_markdown', true );
		$content = ! empty( $markdown ) ? $markdown : '';
		$content_format = 'markdown';

		return [
			'id'             => $post->ID,
			'title'          => get_the_title( $post ),
			'content'        => $content,
			'content_format' => $content_format,
			'excerpt'      => $excerpt,
			'link'         => get_permalink( $post ),
			'project'      => $project_data,
			'project_type' => $project_type_data,
			'meta'         => [
				'sync_source_file' => get_post_meta( $post->ID, '_sync_source_file', true ),
				'sync_hash'        => get_post_meta( $post->ID, '_sync_hash', true ),
				'sync_timestamp'   => get_post_meta( $post->ID, '_sync_timestamp', true ),
			],
		];
	}

	private static function register_reset_documentation(): void {
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
	 * Note: WordPress get_terms() has no parent__not_in param, so we filter in PHP.
	 *
	 * @return int Number of terms deleted
	 */
	private static function delete_child_terms(): int {
		$deleted = 0;

		do {
			$child_terms = get_terms( [
				'taxonomy'   => 'project',
				'hide_empty' => false,
				'childless'  => true,
			] );

			if ( is_wp_error( $child_terms ) || empty( $child_terms ) ) {
				break;
			}

			$found_any = false;
			foreach ( $child_terms as $term ) {
				if ( $term->parent === 0 ) {
					continue;
				}
				$result = wp_delete_term( $term->term_id, 'project' );
				if ( $result && ! is_wp_error( $result ) ) {
					$deleted++;
					$found_any = true;
				}
			}

			if ( ! $found_any ) {
				break;
			}
		} while ( true );

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