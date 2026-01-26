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
		self::enqueue_sync_assets();
	}

	/**
	 * Enqueue sync-related admin assets.
	 */
	private static function enqueue_sync_assets() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$allowed_screens = [
			'documentation_page_chubes-docs-settings',
			'edit-project',
		];

		if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}

		wp_enqueue_script(
			'chubes-docs-sync',
			CHUBES_DOCS_URL . 'assets/js/admin-sync.js',
			[],
			filemtime( CHUBES_DOCS_PATH . 'assets/js/admin-sync.js' ),
			true
		);

		wp_localize_script( 'chubes-docs-sync', 'chubesDocsSync', [
			'restUrl' => rest_url( 'chubes/v1' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'strings' => [
				'syncing'     => __( 'Syncing...', 'chubes-docs' ),
				'success'     => __( 'Sync complete!', 'chubes-docs' ),
				'error'       => __( 'Sync failed:', 'chubes-docs' ),
				'noRepos'     => __( 'No repositories configured for sync.', 'chubes-docs' ),
				'testing'     => __( 'Testing connection...', 'chubes-docs' ),
				'testingRepo' => __( 'Testing repository...', 'chubes-docs' ),
			],
		] );
	}

	/**
	 * Enqueue assets for project/documentation archives.
	 */
	private static function enqueue_archive_assets() {
		$is_docs_archive = is_post_type_archive( 'documentation' ) ||
			is_tax( 'project' ) ||
			get_query_var( 'docs_category_archive' ) ||
			get_query_var( 'docs_project_archive' ) ||
			get_query_var( 'project_archive' ) ||
			get_query_var( 'project_project' );

		if ( ! $is_docs_archive ) {
			return;
		}

		wp_enqueue_style(
			'chubes-docs-archives',
			CHUBES_DOCS_URL . 'assets/css/archives.css',
			[],
			filemtime( CHUBES_DOCS_PATH . 'assets/css/archives.css' )
		);

		if ( is_post_type_archive( 'documentation' ) ) {
			self::enqueue_search_assets();
		}
	}

	/**
	 * Enqueue search bar assets for documentation archive.
	 */
	private static function enqueue_search_assets() {
		wp_enqueue_script(
			'chubes-docs-search',
			CHUBES_DOCS_URL . 'assets/js/docs-search.js',
			[],
			filemtime( CHUBES_DOCS_PATH . 'assets/js/docs-search.js' ),
			true
		);

		wp_localize_script( 'chubes-docs-search', 'chubesDocsSearch', [
			'restUrl' => rest_url( 'wp/v2/documentation' ),
			'strings' => [
				'loading'   => __( 'Searching...', 'chubes-docs' ),
				'error'     => __( 'Search failed. Please try again.', 'chubes-docs' ),
				'noResults' => __( 'No results for "%s"', 'chubes-docs' ),
				'viewAll'   => __( 'View all %d results', 'chubes-docs' ),
			],
		] );
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