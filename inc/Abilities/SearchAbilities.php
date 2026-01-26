<?php
/**
 * Search Abilities
 *
 * Provides WP Abilities API integration for documentation search.
 * Enables AI agents and external clients to search published documentation.
 */

namespace ChubesDocs\Abilities;

use ChubesDocs\Core\Documentation;
use ChubesDocs\Core\Project;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SearchAbilities {

	public static function register(): void {
		wp_register_ability( 'chubes/search-docs', [
			'label'               => __( 'Search Documentation', 'chubes-docs' ),
			'description'         => __( 'Search published documentation by query string. Optionally filter by project.', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'required'   => [ 'query' ],
				'properties' => [
					'query'    => [
						'type'        => 'string',
						'description' => 'Search query string',
					],
					'project' => [
						'type'        => 'integer',
						'description' => 'Project term ID to filter results',
					],
					'per_page' => [
						'type'        => 'integer',
						'description' => 'Number of results per page (max 50)',
						'default'     => 10,
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'items' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'       => [ 'type' => 'integer' ],
								'title'    => [ 'type' => 'string' ],
								'excerpt'  => [ 'type' => 'string' ],
								'link'     => [ 'type' => 'string' ],
								'project' => [
									'type'       => 'object',
									'properties' => [
										'id'   => [ 'type' => 'integer' ],
										'name' => [ 'type' => 'string' ],
										'slug' => [ 'type' => 'string' ],
									],
								],
							],
						],
					],
					'total' => [ 'type' => 'integer' ],
					'query' => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ self::class, 'search_callback' ],
			'permission_callback' => '__return_true',
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function search_callback( array $input ): array {
		$query    = sanitize_text_field( $input['query'] ?? '' );
		$project  = absint( $input['project'] ?? 0 );
		$per_page = min( absint( $input['per_page'] ?? 10 ), 50 );

		if ( empty( $query ) ) {
			return [
				'items' => [],
				'total' => 0,
				'query' => $query,
			];
		}

		$args = [
			'post_type'      => Documentation::POST_TYPE,
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => $per_page,
			'orderby'        => 'relevance',
		];

		if ( $project > 0 ) {
			$args['tax_query'] = [
				[
					'taxonomy'         => Project::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $project,
					'include_children' => true,
				],
			];
		}

		$search_query = new \WP_Query( $args );
		$items        = [];

		foreach ( $search_query->posts as $post ) {
			$terms         = get_the_terms( $post->ID, Project::TAXONOMY );
			$project_term  = $terms && ! is_wp_error( $terms ) ? Project::get_project_term( $terms ) : null;
			$project_data  = null;

			if ( $project_term ) {
				$project_data = [
					'id'   => $project_term->term_id,
					'name' => $project_term->name,
					'slug' => $project_term->slug,
				];
			}

			$items[] = [
				'id'      => $post->ID,
				'title'   => get_the_title( $post ),
				'excerpt' => wp_trim_words( get_the_excerpt( $post ), 20, '...' ),
				'link'    => get_permalink( $post ),
				'project' => $project_data,
			];
		}

		return [
			'items' => $items,
			'total' => $search_query->found_posts,
			'query' => $query,
		];
	}
}
