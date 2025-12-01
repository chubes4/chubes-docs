<?php
/**
 * Documentation Breadcrumbs Handler
 * 
 * Hooks into theme's breadcrumb filters to provide documentation-specific
 * breadcrumb rendering with codebase taxonomy hierarchy.
 */

namespace ChubesDocs\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Breadcrumbs {

	public static function init() {
		add_filter( 'chubes_breadcrumb_custom_archive', [ __CLASS__, 'handle_custom_archive' ], 10, 2 );
		add_filter( 'chubes_breadcrumb_single_documentation', [ __CLASS__, 'handle_single_documentation' ], 10, 3 );
	}

	/**
	 * Handle custom archive breadcrumbs for codebase taxonomy pages
	 *
	 * @param string|null $output Current breadcrumb output
	 * @param array $args Breadcrumb arguments
	 * @return string|null
	 */
	public static function handle_custom_archive( $output, $args ) {
		$term = self::get_active_codebase_archive_term();
		if ( ! $term ) {
			return $output;
		}

		ob_start();
		echo $args['separator'];
		echo '<a href="' . esc_url( get_post_type_archive_link( 'documentation' ) ) . '">Docs</a>';
		self::render_term_breadcrumbs( $term, $args );
		return ob_get_clean();
	}

	/**
	 * Handle single documentation post breadcrumbs
	 *
	 * @param string|null $output Current breadcrumb output
	 * @param array $args Breadcrumb arguments
	 * @param int $post_id Post ID
	 * @return string
	 */
	public static function handle_single_documentation( $output, $args, $post_id ) {
		ob_start();

		echo $args['separator'];
		echo '<a href="' . esc_url( get_post_type_archive_link( 'documentation' ) ) . '">Docs</a>';

		$terms = get_the_terms( $post_id, Codebase::TAXONOMY );

		if ( $terms && ! is_wp_error( $terms ) ) {
			$primary_term = Codebase::get_primary_term( $terms );
			if ( $primary_term ) {
				self::render_term_breadcrumbs( $primary_term, $args, true );
			}
		}

		if ( $args['show_current'] ) {
			echo $args['separator'];
			echo $args['before_current'] . get_the_title( $post_id ) . $args['after_current'];
		}

		return ob_get_clean();
	}

	/**
	 * Render breadcrumb chain for a codebase term hierarchy
	 *
	 * @param \WP_Term $term Codebase taxonomy term
	 * @param array $args Breadcrumb arguments
	 * @param bool $link_current_term Whether to link the deepest term
	 */
	public static function render_term_breadcrumbs( $term, $args, $link_current_term = false ) {
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$ancestors = Codebase::get_term_ancestors( $term );
		$ancestors[] = $term;

		$slug_path = [];
		$last_index = count( $ancestors ) - 1;

		foreach ( $ancestors as $index => $chain_term ) {
			$is_last = ( $index === $last_index );

			echo $args['separator'];

			if ( $is_last && ! $link_current_term ) {
				echo $args['before_current'] . esc_html( $chain_term->name ) . $args['after_current'];
			} else {
				$url = get_term_link( $chain_term, Codebase::TAXONOMY );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $chain_term->name ) . '</a>';
			}
		}
	}

	/**
	 * Resolve the active codebase term for documentation archives
	 *
	 * @return \WP_Term|null
	 */
	public static function get_active_codebase_archive_term() {
		if ( is_tax( Codebase::TAXONOMY ) ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}

		return null;
	}
}
