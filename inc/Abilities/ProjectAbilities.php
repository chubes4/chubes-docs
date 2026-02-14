<?php
/**
 * Project Abilities
 *
 * Provides WP Abilities API integration for inspecting project taxonomy
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
			'description'         => __( 'Returns hierarchical projects with project types and bi-directional associations', 'chubes-docs' ),
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
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'data' => [
						'type'  => 'object',
						'properties' => [
							'projects' => [
								'type' => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'term_id' => [ 'type' => 'integer' ],
										'name' => [ 'type' => 'string' ],
										'slug' => [ 'type' => 'string' ],
										'project_type' => [
											'type' => [ 'object', 'null' ],
											'properties' => [
												'id' => [ 'type' => 'integer' ],
												'name' => [ 'type' => 'string' ],
												'slug' => [ 'type' => 'string' ],
											],
										],
										'depth' => [ 'type' => 'integer' ],
										'doc_count' => [ 'type' => 'integer' ],
										'meta' => [ 'type' => 'object' ],
										'children' => [ 'type' => 'array' ],
									],
								],
							],
						],
					],
				],
			],
			'execute_callback'    => [ self::class, 'get_projects' ],
			'permission_callback' => '__return_true',
			'meta'                => [ 'show_in_rest' => true ],
		] );

		wp_register_ability( 'chubes/get-project-types', [
			'label'               => __( 'Get Project Types', 'chubes-docs' ),
			'description'         => __( 'Returns all project types with associated projects and full metadata', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'include_empty' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include project types with zero associated projects',
					],
				],
			],
									'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'data' => [
						'type'  => 'object',
						'properties' => [
							'project_types' => [
								'type' => 'array',
								'items' => [
									'type' => 'object',
									'properties' => [
										'id' => [ 'type' => 'integer' ],
										'name' => [ 'type' => 'string' ],
										'slug' => [ 'type' => 'string' ],
										'project_count' => [ 'type' => 'integer' ],
										'total_doc_count' => [ 'type' => 'integer' ],
										'associated_projects' => [
											'type' => 'array',
											'items' => [
												'type' => 'object',
												'properties' => [
													'id' => [ 'type' => 'integer' ],
													'name' => [ 'type' => 'string' ],
													'slug' => [ 'type' => 'string' ],
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
			'execute_callback'    => [ self::class, 'get_project_types' ],
			'permission_callback' => '__return_true',
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function get_projects( array $input ): array {
		$project_types = $input['project_types'] ?? [];
		$parent_id     = $input['parent_id'] ?? 0;
		$post_ids       = $input['post_ids'] ?? [];
		$include_empty = $input['include_empty'] ?? false;

		// Case 1: Filter by post IDs
		if ( ! empty( $post_ids ) ) {
			$projects = self::get_projects_by_post_ids( $post_ids );
			return [ 
				'success' => true, 
				'data' => [
					'projects' => $projects,
				]
			];
		}

		// Case 2: Filter by project types
		if ( ! empty( $project_types ) ) {
			$projects = self::get_projects_by_types( $project_types, $include_empty );
			$tree = self::build_tree_from_projects( $projects, 0 );
			return [ 
				'success' => true, 
				'data' => [
					'projects' => $tree,
				]
			];
		}

		// Case 3: Tree format from parent_id (default)
		$tree = self::build_tree( $parent_id, 0, $include_empty );
		
		return [ 
			'success' => true, 
			'data' => [
				'projects' => $tree,
			]
		];
	}

	public static function get_project_types( array $input ): array {
		$include_empty = $input['include_empty'] ?? false;
		
		return [
			'success' => true,
			'data' => [
				'project_types' => self::get_all_project_types_with_associations( $include_empty )
			]
		];
	}

	private static function get_projects_by_post_ids( array $post_ids ): array {
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
			$project_node = self::build_project_node( $project_term, 0 );
			$projects[] = $project_node;
		}

		return $projects;
	}

	private static function get_projects_by_types( array $project_types, bool $include_empty ): array {
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

			$project_node = self::build_project_node( $project, 0 );
			$filtered_projects[] = $project_node;
		}

		return $filtered_projects;
	}

	private static function get_all_project_types_with_associations( bool $include_empty ): array {
		$type_terms = get_terms( [
			'taxonomy'   => 'project_type',
			'hide_empty' => ! $include_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $type_terms ) ) {
			return [];
		}

		$project_types = [];

		foreach ( $type_terms as $type_term ) {
			$project_types[] = self::build_project_type_node( $type_term, $include_empty );
		}

		return $project_types;
	}



	private static function build_project_type_node( \WP_Term $type_term, bool $include_empty ): array {
		$depth_zero_projects = self::get_depth_zero_projects_by_type( $type_term->slug, $include_empty );
		
		$total_doc_count = 0;
		foreach ( $depth_zero_projects as $project ) {
			$total_doc_count += $project['doc_count'];
		}

		return [
			'id' => $type_term->term_id,
			'name' => $type_term->name,
			'slug' => $type_term->slug,
			'project_count' => count( $depth_zero_projects ),
			'total_doc_count' => $total_doc_count,
			'associated_projects' => $depth_zero_projects,
		];
	}

	private static function get_depth_zero_projects_by_type( string $type_slug, bool $include_empty ): array {
		// Query documentation posts tagged with this project_type
		$docs = get_posts( [
			'post_type'      => 'documentation',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => 'project_type',
					'field'    => 'slug',
					'terms'    => $type_slug,
				],
			],
		] );

		if ( empty( $docs ) ) {
			return [];
		}

		// Group by depth-0 project term and count docs per project
		$project_counts = [];
		$project_terms  = [];

		foreach ( $docs as $doc_id ) {
			$terms = get_the_terms( $doc_id, Project::TAXONOMY );
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			$project_term = Project::get_project_term( $terms );
			if ( ! $project_term ) {
				continue;
			}

			$tid = $project_term->term_id;
			if ( ! isset( $project_counts[ $tid ] ) ) {
				$project_counts[ $tid ] = 0;
				$project_terms[ $tid ]  = $project_term;
			}
			$project_counts[ $tid ]++;
		}

		$filtered_projects = [];
		foreach ( $project_terms as $tid => $term ) {
			$filtered_projects[] = [
				'id'        => $term->term_id,
				'name'      => $term->name,
				'slug'      => $term->slug,
				'doc_count' => $project_counts[ $tid ],
			];
		}

		// Sort by name
		usort( $filtered_projects, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

		return $filtered_projects;
	}

	private static function build_project_node( \WP_Term $project, int $depth ): array {
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
			'meta'        => self::get_term_meta( $project ),
		];

		return $node;
	}

	private static function build_tree( int $parent_id, int $depth, bool $include_empty ): array {
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
			$node = self::build_project_node( $term, $depth );
			$node['children'] = self::build_tree( $term->term_id, $depth + 1, $include_empty );
			$tree[] = $node;
		}

		return $tree;
	}

	private static function build_tree_from_projects( array $projects, int $depth ): array {
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