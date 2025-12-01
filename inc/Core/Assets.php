<?php
/**
 * Assets Management - God File
 *
 * Single source of truth for all plugin asset enqueues.
 * Centralizes all frontend, admin, and conditional asset loading logic.
 */

namespace ChubesDocs\Core;

class Assets {

	/**
	 * Initialize asset management hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
	}

	/**
	 * Handle all frontend asset enqueues.
	 */
	public static function enqueue_frontend_assets() {
		self::enqueue_archive_assets();
		self::enqueue_single_assets();
	}

	/**
	 * Handle all admin asset enqueues.
	 */
	public static function enqueue_admin_assets() {
		// Admin assets can be added here in the future
	}

	/**
	 * Enqueue assets for codebase/documentation archives.
	 */
	private static function enqueue_archive_assets() {
		$is_docs_archive = is_post_type_archive( 'documentation' ) ||
			is_tax( 'codebase' ) ||
			get_query_var( 'docs_category_archive' ) ||
			get_query_var( 'docs_project_archive' ) ||
			get_query_var( 'codebase_archive' ) ||
			get_query_var( 'codebase_project' );

		if ( ! $is_docs_archive ) {
			return;
		}

		wp_enqueue_style(
			'chubes-docs-archives',
			CHUBES_DOCS_URL . 'assets/css/archives.css',
			[],
			filemtime( CHUBES_DOCS_PATH . 'assets/css/archives.css' )
		);
	}

	/**
	 * Enqueue assets for single documentation pages.
	 */
	private static function enqueue_single_assets() {
		if ( ! is_singular( 'documentation' ) ) {
			return;
		}

		wp_enqueue_style(
			'chubes-docs-related',
			CHUBES_DOCS_URL . 'assets/css/related-posts.css',
			[],
			filemtime( CHUBES_DOCS_PATH . 'assets/css/related-posts.css' )
		);
	}
}