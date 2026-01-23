<?php
/**
 * Search Abilities
 *
 * Provides WP Abilities API integration for documentation search.
 * Enables AI agents and external clients to search published documentation.
 */

namespace ChubesDocs\Abilities;

use ChubesDocs\Core\Documentation;
use ChubesDocs\Core\Codebase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SearchAbilities {

	public static function register(): void {
		wp_register_ability( 'chubes/search-docs', [
			'label'               => __( 'Search Documentation', 'chubes-docs' ),
			'description'         => __( 'Search published documentation by query string. Optionally filter by codebase.', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'required'   => [ 'query' ],
				'properties' => [
					'query'    => [
						'type'        => 'string',
						'description' => 'Search query string',
					],
					'codebase' => [
						'type'        => 'integer',
						'description' => 'Codebase term ID to filter results',
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
								'codebase' => [
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
		$codebase = absint( $input['codebase'] ?? 0 );
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

		if ( $codebase > 0 ) {
			$args['tax_query'] = [
				[
					'taxonomy'         => Codebase::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $codebase,
					'include_children' => true,
				],
			];
		}

		$search_query = new \WP_Query( $args );
		$items        = [];

		foreach ( $search_query->posts as $post ) {
			$terms         = get_the_terms( $post->ID, Codebase::TAXONOMY );
			$project_term  = $terms && ! is_wp_error( $terms ) ? Codebase::get_project_term( $terms ) : null;
			$codebase_data = null;

			if ( $project_term ) {
				$codebase_data = [
					'id'   => $project_term->term_id,
					'name' => $project_term->name,
					'slug' => $project_term->slug,
				];
			}

			$items[] = [
				'id'       => $post->ID,
				'title'    => get_the_title( $post ),
				'excerpt'  => wp_trim_words( get_the_excerpt( $post ), 20, '...' ),
				'link'     => get_permalink( $post ),
				'codebase' => $codebase_data,
			];
		}

		return [
			'items' => $items,
			'total' => $search_query->found_posts,
			'query' => $query,
		];
	}
}
