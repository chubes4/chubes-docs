<?php
/**
 * Project Taxonomy Registration and Helpers
 *
 * Registers the project taxonomy for organizing documentation by project.
 * Provides static helper methods for term hierarchy resolution.
 */

namespace DocSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Project {

	const TAXONOMY = 'project';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		$labels = array(
			'name'              => _x( 'Projects', 'taxonomy general name', 'docsync' ),
			'singular_name'     => _x( 'Project', 'taxonomy singular name', 'docsync' ),
			'search_items'      => __( 'Search Projects', 'docsync' ),
			'all_items'         => __( 'All Projects', 'docsync' ),
			'parent_item'       => __( 'Parent Project', 'docsync' ),
			'parent_item_colon' => __( 'Parent Project:', 'docsync' ),
			'edit_item'         => __( 'Edit Project', 'docsync' ),
			'update_item'       => __( 'Update Project', 'docsync' ),
			'add_new_item'      => __( 'Add New Project', 'docsync' ),
			'new_item_name'     => __( 'New Project Name', 'docsync' ),
			'menu_name'         => __( 'Projects', 'docsync' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'project' ),
			'show_in_rest'      => true,
		);

		$args = apply_filters( 'docsync_project_args', $args );

		register_taxonomy( self::TAXONOMY, array( Documentation::POST_TYPE ), $args );

		// Register project_type taxonomy
		$project_type_labels = array(
			'name'              => _x( 'Project Types', 'taxonomy general name', 'docsync' ),
			'singular_name'     => _x( 'Project Type', 'taxonomy singular name', 'docsync' ),
			'search_items'      => __( 'Search Project Types', 'docsync' ),
			'all_items'         => __( 'All Project Types', 'docsync' ),
			'parent_item'       => __( 'Parent Project Type', 'docsync' ),
			'parent_item_colon' => __( 'Parent Project Type:', 'docsync' ),
			'edit_item'         => __( 'Edit Project Type', 'docsync' ),
			'update_item'       => __( 'Update Project Type', 'docsync' ),
			'add_new_item'      => __( 'Add New Project Type', 'docsync' ),
			'new_item_name'     => __( 'New Project Type Name', 'docsync' ),
			'menu_name'         => __( 'Project Types', 'docsync' ),
		);

		$project_type_args = array(
			'hierarchical'      => true,
			'labels'            => $project_type_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_menu'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'project-type' ),
			'show_in_rest'      => true,
		);

		$project_type_args = apply_filters( 'docsync_project_type_args', $project_type_args );

		register_taxonomy( 'project_type', array( Documentation::POST_TYPE ), $project_type_args );

		do_action( 'docsync_project_registered' );
	}

	/**
	 * Get the primary (deepest) project term from a set of terms
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
	 * Get the project-level term (depth 0) from terms
	 *
	 * @param array $terms Array of WP_Term objects
	 * @return WP_Term|null
	 */
	public static function get_project_term( $terms ) {
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		foreach ( $terms as $term ) {
			if ( self::get_term_depth( $term ) === 0 ) {
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
	 * @param string|array $path Path like 'wordpress-plugins/my-plugin' or array
	 * @param bool $create_missing Whether to create missing terms
	 * @param array $project_meta Meta to add to the project term if created
	 * @param string|null $project_slug Explicit slug for the project term (depth 1)
	 * @return array
	 */
	public static function resolve_path( $path, $create_missing = false, $project_meta = array(), $project_slug = null ) {
		if ( is_string( $path ) ) {
			$parts = array_filter( explode( '/', trim( $path, '/' ) ) );
		} elseif ( is_array( $path ) ) {
			$parts = array_values( array_filter( $path ) );
		} else {
			return array(
				'success' => false,
				'error'   => 'Invalid path format',
			);
		}

		if ( empty( $parts ) ) {
			return array(
				'success' => false,
				'error'   => 'Empty path',
			);
		}

		$parent_id = 0;
		$terms     = array();

		foreach ( $parts as $index => $part_name ) {
			$is_project_level = ( $index === 0 ); // Depth 0 is project level
			
			// Determine slug
			if ( $is_project_level && ! empty( $project_slug ) ) {
				$slug = $project_slug;
			} else {
				$slug = sanitize_title( $part_name );
			}

			$found = false;
			$term  = null;

			// Try to find child of parent with matching name (case-insensitive)
			// Note: We search by name, not slug, because WordPress slugs are globally unique
			// and may have suffixes like "architecture-2" even under different parents
			$children = get_terms( array(
				'taxonomy'   => self::TAXONOMY,
				'parent'     => $parent_id,
				'hide_empty' => false,
			) );

			if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
				foreach ( $children as $child ) {
					if ( strtolower( $child->name ) === strtolower( $part_name ) ) {
						$term = $child;
						$found = true;
						break;
					}
				}
			}

			if ( ! $found ) {
				if ( ! $create_missing ) {
					return array(
						'success' => false,
						'error'   => "Term not found: {$part_name}",
					);
				}

				$args = array(
					'parent' => $parent_id,
					'slug'   => $slug,
				);

				$new_term = wp_insert_term( $part_name, self::TAXONOMY, $args );

				if ( is_wp_error( $new_term ) ) {
					if ( isset( $new_term->error_data['term_exists'] ) ) {
						$term_id = $new_term->error_data['term_exists'];
						$term = get_term( $term_id, self::TAXONOMY );
					} else {
						return array(
							'success' => false,
							'error'   => "Failed to create term {$part_name}: " . $new_term->get_error_message(),
						);
					}
				} else {
					$term = get_term( $new_term['term_id'], self::TAXONOMY );
					
					// Add meta if this is project level
					if ( $is_project_level && ! empty( $project_meta ) ) {
						foreach ( $project_meta as $key => $value ) {
							update_term_meta( $term->term_id, $key, $value );
						}
					}
				}
			}

			$terms[]   = $term;
			$parent_id = $term->term_id;
		}

		return array(
			'success'      => true,
			'leaf_term_id' => $parent_id,
			'terms'        => $terms,
		);
	}

	/**
	 * Get GitHub URL for a project term
	 *
	 * @param WP_Term|int $term Term object or ID
	 * @return string|null
	 */
	public static function get_github_url( $term ) {
		$term_id = is_object( $term ) ? $term->term_id : $term;
		return get_term_meta( $term_id, 'project_github_url', true ) ?: null;
	}

	/**
	 * Get WordPress.org URL for a project term
	 *
	 * @param WP_Term|int $term Term object or ID
	 * @return string|null
	 */
	public static function get_wp_url( $term ) {
		$term_id = is_object( $term ) ? $term->term_id : $term;
		return get_term_meta( $term_id, 'project_wp_url', true ) ?: null;
	}

	/**
	 * Get install count for a project term
	 */
	public static function get_installs( $term ) {
		$term_id = is_object( $term ) ? $term->term_id : $term;
		return (int) get_term_meta( $term_id, 'project_installs', true );
	}

	/**
	 * Get top-level category slugs
	 *
	 * @return array
	 */
	public static function get_top_level_slugs() {
		$slugs = get_terms( [
			'taxonomy'   => self::TAXONOMY,
			'parent'     => 0,
			'hide_empty' => false,
			'fields'     => 'slugs',
		] );

		if ( is_wp_error( $slugs ) ) {
			return [];
		}

		return is_array( $slugs ) ? $slugs : [];
	}

	/**
	 * Check if a term is a top-level category
	 *
	 * @param WP_Term $term
	 * @return bool
	 */
	public static function is_top_level_term( $term ) {
		return (int) $term->parent === 0;
	}

	/**
	 * Get the project type from term meta
	 *
	 * @param int|WP_Post|WP_Term $input Post ID/object or Term ID/object
	 * @return string|null
	 */
	public static function get_project_type( $input ) {
		// If it's a term, get meta directly
		if ( is_object( $input ) && isset( $input->term_id ) ) {
			return get_term_meta( $input->term_id, 'project_type', true ) ?: null;
		}

		// If it's a term ID
		if ( is_int( $input ) && get_term( $input, self::TAXONOMY ) ) {
			return get_term_meta( $input, 'project_type', true ) ?: null;
		}

		// Otherwise treat as post
		$post_id = is_object( $input ) ? $input->ID : $input;
		$terms = get_the_terms( $post_id, self::TAXONOMY );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}

		$project_term = self::get_project_term( $terms );
		return $project_term ? get_term_meta( $project_term->term_id, 'project_type', true ) ?: null : null;
	}

	/**
	 * Set the project type term meta for a post's project term
	 *
	 * @param int|WP_Post $post Post ID or object
	 * @param string $type Project type (wordpress-plugins, wordpress-themes, cli)
	 * @return bool
	 */
	public static function set_project_type( $post, $type ) {
		$post_id = is_object( $post ) ? $post->ID : $post;
		$terms = get_the_terms( $post_id, self::TAXONOMY );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return false;
		}

		$project_term = self::get_project_term( $terms );
		return $project_term ? update_term_meta( $project_term->term_id, 'project_type', $type ) : false;
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
