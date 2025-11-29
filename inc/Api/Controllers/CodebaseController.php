<?php

namespace ChubesDocs\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class CodebaseController {

	public static function list_terms( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$parent     = absint( $request->get_param( 'parent' ) ?? 0 );
		$hide_empty = (bool) $request->get_param( 'hide_empty' );

		$terms = get_terms( array(
			'taxonomy'   => CHUBES_CODEBASE_TAXONOMY,
			'parent'     => $parent,
			'hide_empty' => $hide_empty,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array();
		foreach ( $terms as $term ) {
			$items[] = self::prepare_term( $term );
		}

		return rest_ensure_response( $items );
	}

	public static function get_tree( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$tree = self::build_tree( 0 );
		return rest_ensure_response( $tree );
	}

	public static function resolve_path( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$path           = $request->get_param( 'path' );
		$create_missing = (bool) $request->get_param( 'create_missing' );
		$project_meta   = $request->get_param( 'project_meta' ) ?? array();

		if ( ! is_array( $path ) || empty( $path ) ) {
			return new WP_Error( 'invalid_path', 'Path must be a non-empty array', array( 'status' => 400 ) );
		}

		$result = chubes_resolve_codebase_path( $path, $create_missing, $project_meta );

		if ( ! $result['success'] ) {
			return new WP_Error( 'resolve_failed', $result['error'] ?? 'Failed to resolve path', array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	public static function get_term( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$term_id = absint( $request->get_param( 'id' ) );
		$term    = get_term( $term_id, CHUBES_CODEBASE_TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'not_found', 'Term not found', array( 'status' => 404 ) );
		}

		return rest_ensure_response( self::prepare_term( $term, true ) );
	}

	public static function update_term( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$term_id = absint( $request->get_param( 'id' ) );
		$term    = get_term( $term_id, CHUBES_CODEBASE_TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'not_found', 'Term not found', array( 'status' => 404 ) );
		}

		$args = array();

		$name = $request->get_param( 'name' );
		if ( $name !== null ) {
			$args['name'] = sanitize_text_field( wp_unslash( $name ) );
		}

		$description = $request->get_param( 'description' );
		if ( $description !== null ) {
			$args['description'] = sanitize_textarea_field( wp_unslash( $description ) );
		}

		if ( ! empty( $args ) ) {
			$result = wp_update_term( $term_id, CHUBES_CODEBASE_TAXONOMY, $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$meta = $request->get_param( 'meta' );
		if ( ! empty( $meta ) && is_array( $meta ) ) {
			if ( isset( $meta['github_url'] ) ) {
				update_term_meta( $term_id, 'codebase_github_url', esc_url_raw( $meta['github_url'] ) );
			}
			if ( isset( $meta['wp_url'] ) ) {
				update_term_meta( $term_id, 'codebase_wp_url', esc_url_raw( $meta['wp_url'] ) );
			}
		}

		$term = get_term( $term_id, CHUBES_CODEBASE_TAXONOMY );
		return rest_ensure_response( self::prepare_term( $term, true ) );
	}

	private static function prepare_term( \WP_Term $term, bool $include_repo_info = false ): array {
		$top_level    = chubes_get_codebase_top_level_term( $term );
		$project      = chubes_get_codebase_project_term( $term );
		$project_type = chubes_get_codebase_project_type( $term );

		$item = array(
			'id'           => $term->term_id,
			'name'         => $term->name,
			'slug'         => $term->slug,
			'description'  => $term->description,
			'parent'       => $term->parent,
			'count'        => $term->count,
			'project_type' => $project_type,
			'is_top_level' => chubes_is_codebase_top_level_term( $term ),
			'is_project'   => $project && $project->term_id === $term->term_id,
		);

		if ( $include_repo_info ) {
			$item['meta'] = array(
				'github_url' => chubes_get_codebase_github_url( $term->term_id ),
				'wp_url'     => chubes_get_codebase_wp_url( $term->term_id ),
				'installs'   => chubes_get_codebase_installs( $term->term_id ),
			);
			$item['repository_info'] = chubes_get_repository_info( $term );
		}

		return $item;
	}

	private static function build_tree( int $parent_id ): array {
		$terms = get_terms( array(
			'taxonomy'   => CHUBES_CODEBASE_TAXONOMY,
			'parent'     => $parent_id,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$tree = array();
		foreach ( $terms as $term ) {
			$node     = self::prepare_term( $term );
			$children = self::build_tree( $term->term_id );
			if ( ! empty( $children ) ) {
				$node['children'] = $children;
			}
			$tree[] = $node;
		}

		return $tree;
	}
}
