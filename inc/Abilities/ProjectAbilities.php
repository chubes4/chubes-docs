<?php
/**
 * Project Abilities
 *
 * Provides WP Abilities API integration for inspecting the project taxonomy
 * hierarchy and metadata. Enables inspection via WP-CLI, REST, or MCP.
 */

namespace ChubesDocs\Abilities;

use ChubesDocs\Core\Project;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProjectAbilities {

	public static function register(): void {
		wp_register_ability( 'chubes/get-projects', [
			'label'               => __( 'Get Projects', 'chubes-docs' ),
			'description'         => __( 'Returns projects with optional filtering by type and hierarchical structure', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'project_types' => [
						'type'        => 'array',
						'description' => 'Filter projects by these project type slugs',
						'items' => [ 'type' => 'string' ],
					],
					'parent_id' => [
						'type'        => 'integer',
						'default'     => 0,
						'description' => 'Parent term ID for tree structure (0 for root)',
					],
					'post_ids' => [
						'type'        => 'array',
						'description' => 'Get specific projects by post IDs',
						'items' => [ 'type' => 'integer' ],
					],
					'include_empty' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include projects with no documentation posts',
					],
					'include_meta' => [
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include repository metadata',
					],
					'tree_format' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Return as hierarchical tree (true) or flat list ordered by count (false)',
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'projects' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'term_id' => [ 'type' => 'integer' ],
								'name' => [ 'type' => 'string' ],
								'slug' => [ 'type' => 'string' ],
								'project_type' => [
									'type' => 'object',
									'properties' => [
										'id' => [ 'type' => 'integer' ],
										'name' => [ 'type' => 'string' ],
										'slug' => [ 'type' => 'string' ],
									],
								],
								'depth' => [ 'type' => 'integer' ],
								'doc_count' => [ 'type' => 'integer' ],
								'meta' => [ 'type' => 'object' ],
								'children' => [ 'type' => 'array' ], // Only in tree format
							],
						],
					],
				],
			],
			'execute_callback'    => [ self::class, 'get_projects' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function get_projects( array $input ): array {
		$project_types = $input['project_types'] ?? [];
		$parent_id     = $input['parent_id'] ?? 0;
		$post_ids       = $input['post_ids'] ?? [];
		$include_empty = $input['include_empty'] ?? false;
		$include_meta  = $input['include_meta'] ?? true;
		$tree_format   = $input['tree_format'] ?? false;

		// Case 1: Filter by post IDs
		if ( ! empty( $post_ids ) ) {
			$projects = self::get_projects_by_post_ids( $post_ids, $include_meta );
			return [ 'success' => true, 'projects' => $projects ];
		}

		// Case 2: Filter by project types
		if ( ! empty( $project_types ) ) {
			$projects = self::get_projects_by_types( $project_types, $include_empty, $include_meta );
			if ( $tree_format ) {
				$projects = self::build_tree_from_projects( $projects, 0, $include_meta );
			} else {
				// Sort by doc_count (descending) as default
				usort( $projects, function( $a, $b ) {
					return $b['doc_count'] - $a['doc_count'];
				} );
			}
			return [ 'success' => true, 'projects' => $projects ];
		}

		// Case 3: Tree format or all projects (default)
		if ( $tree_format ) {
			$tree = self::build_tree( $parent_id, 0, $include_empty, $include_meta );
			return [ 'success' => true, 'projects' => $tree ];
		} else {
			// Get all projects, sorted by doc_count
			$projects = self::get_all_projects_sorted( $include_empty, $include_meta );
			return [ 'success' => true, 'projects' => $projects ];
		}
	}

	private static function get_projects_by_post_ids( array $post_ids, bool $include_meta ): array {
		$projects = [];
		$processed_project_ids = [];

		foreach ( $post_ids as $post_id ) {
			$terms = get_the_terms( $post_id, Project::TAXONOMY );
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			$project_term = Project::get_project_term( $terms );
			if ( ! $project_term || in_array( $project_term->term_id, $processed_project_ids ) ) {
				continue;
			}

			$processed_project_ids[] = $project_term->term_id;
			$project_node = self::build_project_node( $project_term, 0, $include_meta );
			$projects[] = $project_node;
		}

		return $projects;
	}

	private static function get_projects_by_types( array $project_types, bool $include_empty, bool $include_meta ): array {
		$all_projects = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'hide_empty' => ! $include_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $all_projects ) ) {
			return [];
		}

		$filtered_projects = [];

		foreach ( $all_projects as $project ) {
			$project_type = Project::get_project_type( $project );
			
			if ( ! $project_type || ! in_array( $project_type, $project_types, true ) ) {
				continue;
			}

			$project_node = self::build_project_node( $project, 0, $include_meta );
			$filtered_projects[] = $project_node;
		}

		return $filtered_projects;
	}

	private static function get_all_projects_sorted( bool $include_empty, bool $include_meta ): array {
		$all_projects = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'hide_empty' => ! $include_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $all_projects ) ) {
			return [];
		}

		$projects = [];
		foreach ( $all_projects as $project ) {
			$project_node = self::build_project_node( $project, 0, $include_meta );
			$projects[] = $project_node;
		}

		// Sort by doc_count (descending)
		usort( $projects, function( $a, $b ) {
			return $b['doc_count'] - $a['doc_count'];
		} );

		return $projects;
	}

	private static function build_project_node( \WP_Term $project, int $depth, bool $include_meta ): array {
		$project_type = Project::get_project_type( $project );
		$project_type_obj = null;

		if ( $project_type ) {
			$type_term = get_term_by( 'slug', $project_type, 'project_type' );
			if ( $type_term && ! is_wp_error( $type_term ) ) {
				$project_type_obj = [
					'id'   => $type_term->term_id,
					'name' => $type_term->name,
					'slug' => $type_term->slug,
				];
			}
		}

		$node = [
			'term_id'     => $project->term_id,
			'name'        => $project->name,
			'slug'        => $project->slug,
			'project_type' => $project_type_obj,
			'depth'       => $depth,
			'doc_count'   => (int) $project->count,
		];

		if ( $include_meta && $depth === 1 ) {
			$node['meta'] = self::get_term_meta( $project );
		}

		return $node;
	}

	private static function build_tree( int $parent_id, int $depth, bool $include_empty, bool $include_meta ): array {
		$terms = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'parent'     => $parent_id,
			'hide_empty' => ! $include_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$tree = [];

		foreach ( $terms as $term ) {
			$node = self::build_project_node( $term, $depth, $include_meta );
			$node['children'] = self::build_tree( $term->term_id, $depth + 1, $include_empty, $include_meta );
			$tree[] = $node;
		}

		return $tree;
	}

	private static function build_tree_from_projects( array $projects, int $depth, bool $include_meta ): array {
		// This is a simplified tree builder - for full hierarchical structure, use parent_id based tree_format
		return $projects;
	}

	private static function get_term_meta( \WP_Term $term ): array {
		$github_url = get_term_meta( $term->term_id, 'project_github_url', true );
		$wp_url     = get_term_meta( $term->term_id, 'project_wp_url', true );
		$installs   = (int) get_term_meta( $term->term_id, 'project_installs', true );
		$last_sync  = get_term_meta( $term->term_id, 'project_last_sync', true );

		$meta = [];

		if ( ! empty( $github_url ) ) {
			$meta['github_url'] = $github_url;
		}

		if ( ! empty( $wp_url ) ) {
			$meta['wp_url'] = $wp_url;
		}

		if ( $installs > 0 ) {
			$meta['installs'] = $installs;
		}

		if ( ! empty( $last_sync ) ) {
			$meta['last_sync'] = $last_sync;
		}

		return $meta;
	}
}
