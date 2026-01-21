<?php
/**
 * Codebase Abilities
 *
 * Provides WP Abilities API integration for inspecting the codebase taxonomy
 * hierarchy and metadata. Enables inspection via WP-CLI, REST, or MCP.
 */

namespace ChubesDocs\Abilities;

use ChubesDocs\Core\Codebase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodebaseAbilities {

	public static function register(): void {
		wp_register_ability( 'chubes/get-codebase-tree', [
			'label'               => __( 'Get Codebase Tree', 'chubes-docs' ),
			'description'         => __( 'Returns hierarchical codebase taxonomy with metadata', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'parent_id' => [
						'type'        => 'integer',
						'default'     => 0,
						'description' => 'Parent term ID to start from (0 for root)',
					],
					'include_empty' => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Include terms with no documentation posts',
					],
					'include_meta' => [
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Include repository metadata (github_url, wp_url, installs)',
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'tree'    => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'term_id'   => [ 'type' => 'integer' ],
								'name'      => [ 'type' => 'string' ],
								'slug'      => [ 'type' => 'string' ],
								'depth'     => [ 'type' => 'integer' ],
								'doc_count' => [ 'type' => 'integer' ],
								'meta'      => [ 'type' => 'object' ],
								'children'  => [ 'type' => 'array' ],
							],
						],
					],
				],
			],
			'execute_callback'    => [ self::class, 'get_tree' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function get_tree( array $input ): array {
		$parent_id     = $input['parent_id'] ?? 0;
		$include_empty = $input['include_empty'] ?? false;
		$include_meta  = $input['include_meta'] ?? true;

		$tree = self::build_tree( $parent_id, 0, $include_empty, $include_meta );

		return [
			'success' => true,
			'tree'    => $tree,
		];
	}

	private static function build_tree( int $parent_id, int $depth, bool $include_empty, bool $include_meta ): array {
		$terms = get_terms( [
			'taxonomy'   => Codebase::TAXONOMY,
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
			$node = [
				'term_id'   => $term->term_id,
				'name'      => $term->name,
				'slug'      => $term->slug,
				'depth'     => $depth,
				'doc_count' => (int) $term->count,
				'children'  => self::build_tree( $term->term_id, $depth + 1, $include_empty, $include_meta ),
			];

			if ( $include_meta && $depth === 1 ) {
				$node['meta'] = self::get_term_meta( $term );
			}

			$tree[] = $node;
		}

		return $tree;
	}

	private static function get_term_meta( \WP_Term $term ): array {
		$github_url = get_term_meta( $term->term_id, 'codebase_github_url', true );
		$wp_url     = get_term_meta( $term->term_id, 'codebase_wp_url', true );
		$installs   = (int) get_term_meta( $term->term_id, 'codebase_installs', true );
		$last_sync  = get_term_meta( $term->term_id, 'codebase_last_sync', true );

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
