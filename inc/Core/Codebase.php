<?php
/**
 * Codebase Taxonomy Registration and Helpers
 * 
 * Registers the codebase taxonomy for organizing documentation by project.
 * Provides static helper methods for term hierarchy resolution.
 */

namespace ChubesDocs\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Codebase {

	const TAXONOMY = 'codebase';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		$labels = array(
			'name'              => _x( 'Codebase', 'taxonomy general name', 'chubes-docs' ),
			'singular_name'     => _x( 'Codebase', 'taxonomy singular name', 'chubes-docs' ),
			'search_items'      => __( 'Search Codebases', 'chubes-docs' ),
			'all_items'         => __( 'All Codebases', 'chubes-docs' ),
			'parent_item'       => __( 'Parent Codebase', 'chubes-docs' ),
			'parent_item_colon' => __( 'Parent Codebase:', 'chubes-docs' ),
			'edit_item'         => __( 'Edit Codebase', 'chubes-docs' ),
			'update_item'       => __( 'Update Codebase', 'chubes-docs' ),
			'add_new_item'      => __( 'Add New Codebase', 'chubes-docs' ),
			'new_item_name'     => __( 'New Codebase Name', 'chubes-docs' ),
			'menu_name'         => __( 'Codebase', 'chubes-docs' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'codebase', 'with_front' => false ),
			'show_in_rest'      => true,
		);

		$args = apply_filters( 'chubes_codebase_args', $args );

		register_taxonomy( self::TAXONOMY, array( Documentation::POST_TYPE ), $args );

		do_action( 'chubes_codebase_registered' );
	}

	/**
	 * Get the primary (deepest) codebase term from a set of terms
	 *
	 * @param array $terms Array of WP_Term objects
	 * @return WP_Term|null
	 */
	public static function get_primary_term( $terms ) {
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		$deepest      = null;
		$deepest_depth = -1;

		foreach ( $terms as $term ) {
			$depth = self::get_term_depth( $term );
			if ( $depth > $deepest_depth ) {
				$deepest       = $term;
				$deepest_depth = $depth;
			}
		}

		return $deepest;
	}

	/**
	 * Get the project-level term (depth 1) from terms
	 *
	 * @param array $terms Array of WP_Term objects
	 * @return WP_Term|null
	 */
	public static function get_project_term( $terms ) {
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( self::get_term_depth( $term ) === 1 ) {
				return $term;
			}
		}

		$primary = self::get_primary_term( $terms );
		if ( $primary ) {
			return self::get_ancestor_at_depth( $primary, 1 );
		}

		return null;
	}

	/**
	 * Get the top-level term (depth 0) from terms
	 *
	 * @param array $terms Array of WP_Term objects
	 * @return WP_Term|null
	 */
	public static function get_top_level_term( $terms ) {
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( $term->parent === 0 ) {
				return $term;
			}
		}

		$primary = self::get_primary_term( $terms );
		if ( $primary ) {
			return self::get_ancestor_at_depth( $primary, 0 );
		}

		return null;
	}

	/**
	 * Get the depth of a term in the hierarchy
	 *
	 * @param WP_Term $term
	 * @return int
	 */
	public static function get_term_depth( $term ) {
		$depth = 0;
		$current = $term;

		while ( $current->parent !== 0 ) {
			$depth++;
			$current = get_term( $current->parent, self::TAXONOMY );
			if ( ! $current || is_wp_error( $current ) ) {
				break;
			}
		}

		return $depth;
	}

	/**
	 * Get ancestor term at a specific depth
	 *
	 * @param WP_Term $term
	 * @param int $target_depth
	 * @return WP_Term|null
	 */
	public static function get_ancestor_at_depth( $term, $target_depth ) {
		$ancestors = self::get_term_ancestors( $term );
		$ancestors[] = $term;

		foreach ( $ancestors as $ancestor ) {
			if ( self::get_term_depth( $ancestor ) === $target_depth ) {
				return $ancestor;
			}
		}

		return null;
	}

	/**
	 * Get all ancestors of a term (from root to parent)
	 *
	 * @param WP_Term $term
	 * @return array Array of WP_Term objects
	 */
	public static function get_term_ancestors( $term ) {
		$ancestors = [];
		$current = $term;

		while ( $current->parent !== 0 ) {
			$parent = get_term( $current->parent, self::TAXONOMY );
			if ( ! $parent || is_wp_error( $parent ) ) {
				break;
			}
			array_unshift( $ancestors, $parent );
			$current = $parent;
		}

		return $ancestors;
	}

	/**
	 * Build hierarchical URL path from term to root
	 *
	 * @param WP_Term $term
	 * @return string Path like 'category/project/subproject'
	 */
	public static function build_term_hierarchy_path( $term ) {
		$ancestors = self::get_term_ancestors( $term );
		$ancestors[] = $term;

		$slugs = array_map( function( $t ) {
			return $t->slug;
		}, $ancestors );

		return implode( '/', $slugs );
	}

	/**
	 * Resolve a hierarchical path to its deepest matching term
	 *
	 * @param string $path Path like 'wordpress-plugins/my-plugin'
	 * @return WP_Term|null
	 */
	public static function resolve_path( $path ) {
		$parts = array_filter( explode( '/', trim( $path, '/' ) ) );
		if ( empty( $parts ) ) {
			return null;
		}

		$target_slug = end( $parts );
		$term = get_term_by( 'slug', $target_slug, self::TAXONOMY );

		return $term && ! is_wp_error( $term ) ? $term : null;
	}

	/**
	 * Get GitHub URL for a codebase term
	 *
	 * @param WP_Term|int $term Term object or ID
	 * @return string|null
	 */
	public static function get_github_url( $term ) {
		$term_id = is_object( $term ) ? $term->term_id : $term;
		return get_term_meta( $term_id, 'github_url', true ) ?: null;
	}

	/**
	 * Get WordPress.org URL for a codebase term
	 *
	 * @param WP_Term|int $term Term object or ID
	 * @return string|null
	 */
	public static function get_wp_url( $term ) {
		$term_id = is_object( $term ) ? $term->term_id : $term;
		return get_term_meta( $term_id, 'wp_url', true ) ?: null;
	}

	/**
	 * Get install count for a codebase term
	 *
	 * @param WP_Term|int $term Term object or ID
	 * @return int
	 */
	public static function get_installs( $term ) {
		$term_id = is_object( $term ) ? $term->term_id : $term;
		return (int) get_term_meta( $term_id, 'codebase_installs', true );
	}

	/**
	 * Get top-level category slugs
	 *
	 * @return array
	 */
	public static function get_top_level_slugs() {
		return array( 'wordpress-plugins', 'wordpress-themes', 'discord-bots', 'php-libraries' );
	}

	/**
	 * Check if a term is a top-level category
	 *
	 * @param WP_Term $term
	 * @return bool
	 */
	public static function is_top_level_term( $term ) {
		return $term->parent === 0 && in_array( $term->slug, self::get_top_level_slugs(), true );
	}

	/**
	 * Get the project type (top-level category slug) for a term
	 *
	 * @param WP_Term|array $term_or_terms Single term or array of terms
	 * @return string|null
	 */
	public static function get_project_type( $term_or_terms ) {
		$terms = is_array( $term_or_terms ) ? $term_or_terms : array( $term_or_terms );
		$top_level = self::get_top_level_term( $terms );

		return $top_level ? $top_level->slug : null;
	}

	/**
	 * Get repository information for a term
	 *
	 * @param WP_Term|array $term_or_terms Single term or array of terms
	 * @return array
	 */
	public static function get_repository_info( $term_or_terms ) {
		$terms = is_array( $term_or_terms ) ? $term_or_terms : array( $term_or_terms );
		$project = self::get_project_term( $terms );

		if ( ! $project ) {
			return array(
				'github_url'     => null,
				'wp_url'         => null,
				'installs'       => 0,
				'project_type'   => null,
				'content_counts' => array(),
				'has_content'    => false,
			);
		}

		$content_counts = self::get_content_counts( $project );
		$has_content = array_sum( $content_counts ) > 0;

		return array(
			'github_url'     => self::get_github_url( $project->term_id ),
			'wp_url'         => self::get_wp_url( $project->term_id ),
			'installs'       => self::get_installs( $project->term_id ),
			'project_type'   => self::get_project_type( $project ),
			'content_counts' => $content_counts,
			'has_content'    => $has_content,
		);
	}

	/**
	 * Get content counts by post type for a term
	 *
	 * @param WP_Term $term
	 * @return array Associative array of post_type => count
	 */
	public static function get_content_counts( $term ) {
		$counts = array();
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type, array( 'attachment', 'page' ), true ) ) {
				continue;
			}

			if ( ! is_object_in_taxonomy( $post_type, self::TAXONOMY ) ) {
				continue;
			}

			$query = new \WP_Query( array(
				'post_type'      => $post_type,
				'tax_query'      => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			) );

			if ( $query->found_posts > 0 ) {
				$counts[ $post_type ] = $query->found_posts;
			}

			wp_reset_postdata();
		}

		return $counts;
	}
}
