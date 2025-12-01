<?php
/**
 * Rewrite Rules for Codebase Vanity URLs and Documentation Permalinks
 *
 * Provides:
 * - /{category}/ → Codebase taxonomy archive (vanity URL)
 * - /{category}/{project}/ → Codebase project page (vanity URL)
 * - /docs/{project}/{post-slug}/ → Documentation post permalink
 */

namespace ChubesDocs\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RewriteRules {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_rules' ] );
		add_filter( 'post_type_link', [ __CLASS__, 'filter_doc_permalink' ], 10, 2 );
		add_filter( 'term_link', [ __CLASS__, 'filter_term_permalink' ], 10, 3 );
	}

	/**
	 * Register vanity URL rewrite rules for codebase taxonomy
	 */
	public static function register_rules() {
		$categories = Codebase::get_top_level_slugs();

		foreach ( $categories as $category ) {
			// /{category}/ → codebase taxonomy term
			add_rewrite_rule(
				'^' . $category . '/?$',
				'index.php?codebase=' . $category,
				'top'
			);

			// /{category}/{project}/ → codebase child term
			add_rewrite_rule(
				'^' . $category . '/([^/]+)/?$',
				'index.php?codebase=$matches[1]',
				'top'
			);
		}

		// /docs/{project}/{post-slug}/ → documentation post
		add_rewrite_rule(
			'^docs/([^/]+)/([^/]+)/?$',
			'index.php?post_type=documentation&name=$matches[2]',
			'top'
		);
	}

	/**
	 * Filter documentation post permalinks to include project slug
	 *
	 * @param string   $permalink The post permalink
	 * @param \WP_Post $post      The post object
	 * @return string
	 */
	public static function filter_doc_permalink( $permalink, $post ) {
		if ( $post->post_type !== Documentation::POST_TYPE ) {
			return $permalink;
		}

		$terms = get_the_terms( $post->ID, Codebase::TAXONOMY );
		$project = Codebase::get_project_term( $terms );

		if ( $project ) {
			return home_url( '/docs/' . $project->slug . '/' . $post->post_name . '/' );
		}

		return $permalink;
	}

	/**
	 * Filter codebase term permalinks to use vanity URLs
	 *
	 * @param string   $termlink The term permalink
	 * @param \WP_Term $term     The term object
	 * @param string   $taxonomy The taxonomy slug
	 * @return string
	 */
	public static function filter_term_permalink( $termlink, $term, $taxonomy ) {
		if ( $taxonomy !== Codebase::TAXONOMY ) {
			return $termlink;
		}

		// Top-level category: /wordpress-plugins/
		if ( $term->parent === 0 ) {
			return home_url( '/' . $term->slug . '/' );
		}

		// Project (child of top-level): /wordpress-plugins/my-plugin/
		$parent = get_term( $term->parent, Codebase::TAXONOMY );
		if ( $parent && ! is_wp_error( $parent ) && $parent->parent === 0 ) {
			return home_url( '/' . $parent->slug . '/' . $term->slug . '/' );
		}

		// Deeper nested terms - use parent's path
		$ancestors = get_ancestors( $term->term_id, Codebase::TAXONOMY, 'taxonomy' );
		if ( ! empty( $ancestors ) ) {
			$top_level = get_term( end( $ancestors ), Codebase::TAXONOMY );
			if ( $top_level && ! is_wp_error( $top_level ) ) {
				return home_url( '/' . $top_level->slug . '/' . $term->slug . '/' );
			}
		}

		return $termlink;
	}
}
