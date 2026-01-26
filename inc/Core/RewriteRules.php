<?php
/**
 * Rewrite Rules for Documentation Permalinks
 *
 * Provides hierarchical URL routing:
 * - /docs/ → Documentation post type archive (all projects grouped by category)
 * - /docs/{category}/ → Category archive (projects in that category)
 * - /docs/{category}/{project}/ → Project documentation listing
 * - /docs/{category}/{project}/{sub-hierarchy}/ → Nested term archives
 * - /docs/{category}/{project}/{sub-hierarchy}/{post-slug}/ → Documentation post
 */

namespace ChubesDocs\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RewriteRules {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_action( 'parse_request', [ __CLASS__, 'resolve_docs_path' ] );
		add_filter( 'post_type_link', [ __CLASS__, 'filter_doc_permalink' ], 10, 2 );
		add_filter( 'term_link', [ __CLASS__, 'filter_term_permalink' ], 10, 3 );
		add_filter( 'post_type_archive_link', [ __CLASS__, 'filter_archive_permalink' ], 10, 2 );
	}

	/**
	 * Register custom query vars
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'chubes_docs_path';
		return $vars;
	}

	/**
	 * Register rewrite rules for documentation URLs
	 */
	public static function register_rules() {
		// /docs/ → documentation post type archive
		add_rewrite_rule(
			'^docs/?$',
			'index.php?post_type=documentation',
			'top'
		);


		// /docs/{path}/ → catch-all for projects and deeper hierarchy
		add_rewrite_rule(
			'^docs/(.+)/?$',
			'index.php?chubes_docs_path=$matches[1]',
			'top'
		);
	}

	/**
	 * Resolve the catch-all docs path to either a term archive or single post
	 *
	 * Walks the hierarchy linearly: category → project → sub-terms → post
	 *
	 * @param \WP $wp The WordPress environment instance
	 */
	public static function resolve_docs_path( $wp ) {
		if ( empty( $wp->query_vars['chubes_docs_path'] ) ) {
			return;
		}

		$path = trim( $wp->query_vars['chubes_docs_path'], '/' );
		$segments = array_filter( explode( '/', $path ) );

		if ( empty( $segments ) ) {
			return;
		}

		$segments = array_values( $segments );
		$first_slug = $segments[0];

		// First segment must be a top-level category
		$current_term = get_term_by( 'slug', $first_slug, Project::TAXONOMY );
		if ( ! $current_term || is_wp_error( $current_term ) || $current_term->parent !== 0 ) {
			return;
		}

		// Single segment - category archive
		if ( count( $segments ) === 1 ) {
			unset( $wp->query_vars['chubes_docs_path'] );
			$wp->query_vars['project'] = $current_term->slug;
			return;
		}

		// Walk remaining segments down the hierarchy
		$remaining_segments = array_slice( $segments, 1 );

		foreach ( $remaining_segments as $index => $slug ) {
			$child_term = self::find_child_term( $slug, $current_term->term_id );

			if ( $child_term ) {
				$current_term = $child_term;
			} else {
				// Not a term - check if it's a post (must be the last segment)
				$is_last_segment = ( $index === count( $remaining_segments ) - 1 );

				if ( $is_last_segment ) {
					$post = self::find_documentation_post( $slug, $current_term );
					if ( $post ) {
						unset( $wp->query_vars['chubes_docs_path'] );
						$wp->query_vars['post_type'] = Documentation::POST_TYPE;
						$wp->query_vars['name'] = $post->post_name;
						return;
					}
				}

				// No match found - let WordPress handle 404
				return;
			}
		}

		// All segments matched terms - show deepest term archive
		unset( $wp->query_vars['chubes_docs_path'] );
		$wp->query_vars['project'] = $current_term->slug;
	}

	/**
	 * Find a child term by slug under a parent term
	 *
	 * @param string $slug      The term slug
	 * @param int    $parent_id The parent term ID
	 * @return \WP_Term|null
	 */
	private static function find_child_term( $slug, $parent_id ) {
		$terms = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'slug'       => $slug,
			'parent'     => $parent_id,
			'hide_empty' => false,
			'number'     => 1,
		] );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return $terms[0];
		}

		return null;
	}

	/**
	 * Find a documentation post by slug within a project term
	 *
	 * @param string   $slug The post slug
	 * @param \WP_Term $term The project term
	 * @return \WP_Post|null
	 */
	private static function find_documentation_post( $slug, $term ) {
		$posts = get_posts( [
			'post_type'   => Documentation::POST_TYPE,
			'name'        => $slug,
			'post_status' => 'publish',
			'numberposts' => 1,
			'tax_query'   => [
				[
					'taxonomy' => Project::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				],
			],
		] );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Filter documentation post permalinks to include full term hierarchy
	 *
	 * @param string   $permalink The post permalink
	 * @param \WP_Post $post      The post object
	 * @return string
	 */
	public static function filter_doc_permalink( $permalink, $post ) {
		if ( $post->post_type !== Documentation::POST_TYPE ) {
			return $permalink;
		}

		$terms = get_the_terms( $post->ID, Project::TAXONOMY );
		$primary_term = Project::get_primary_term( $terms );

		if ( ! $primary_term ) {
			return $permalink;
		}

		$path = self::build_docs_term_path( $primary_term );
		return home_url( '/docs/' . $path . '/' . $post->post_name . '/' );
	}

	/**
	 * Filter project term permalinks to use hierarchical /docs/ URLs
	 *
	 * @param string   $termlink The term permalink
	 * @param \WP_Term $term     The term object
	 * @param string   $taxonomy The taxonomy slug
	 * @return string
	 */
	public static function filter_term_permalink( $termlink, $term, $taxonomy ) {
		if ( $taxonomy !== Project::TAXONOMY ) {
			return $termlink;
		}

		// Top-level category (depth 0): /docs/{category}/
		if ( $term->parent === 0 ) {
			return home_url( '/docs/' . $term->slug . '/' );
		}

		// Project and deeper terms: /docs/{project}/{hierarchy}/
		$path = self::build_docs_term_path( $term );
		return home_url( '/docs/' . $path . '/' );
	}

	/**
	 * Build the docs URL path for a term (full hierarchy including category)
	 *
	 * @param \WP_Term $term The project term
	 * @return string Path like 'category/project/sub/subsub'
	 */
	public static function build_docs_term_path( $term ) {
		$ancestors = Project::get_term_ancestors( $term );
		$ancestors[] = $term;

		$slugs = array_map( function( $t ) {
			return $t->slug;
		}, $ancestors );

		return implode( '/', $slugs );
	}

	/**
	 * Filter documentation archive permalink to use /docs/
	 *
	 * @param string $link      The archive permalink
	 * @param string $post_type The post type slug
	 * @return string
	 */
	public static function filter_archive_permalink( $link, $post_type ) {
		if ( $post_type === Documentation::POST_TYPE ) {
			return home_url( '/docs/' );
		}

		return $link;
	}
}
