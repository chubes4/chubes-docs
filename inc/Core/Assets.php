<?php
/**
 * Assets Management - God File
 *
 * Single source of truth for all plugin asset enqueues.
 * Centralizes all frontend, admin, and conditional asset loading logic.
 */

namespace DocSync\Core;

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
			'documentation_page_docsync-settings',
			'edit-project',
		];

		if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}

		wp_enqueue_script(
			'docsync-sync',
			DOCSYNC_URL . 'assets/js/admin-sync.js',
			[],
			filemtime( DOCSYNC_PATH . 'assets/js/admin-sync.js' ),
			true
		);

		wp_localize_script( 'docsync-sync', 'docSyncAdmin', [
			'restUrl' => rest_url( 'docsync/v1' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'strings' => [
				'syncing'     => __( 'Syncing...', 'docsync' ),
				'success'     => __( 'Sync complete!', 'docsync' ),
				'error'       => __( 'Sync failed:', 'docsync' ),
				'noRepos'     => __( 'No repositories configured for sync.', 'docsync' ),
				'testing'     => __( 'Testing connection...', 'docsync' ),
				'testingRepo' => __( 'Testing repository...', 'docsync' ),
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
			'docsync-archives',
			DOCSYNC_URL . 'assets/css/archives.css',
			[],
			filemtime( DOCSYNC_PATH . 'assets/css/archives.css' )
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
			'docsync-search',
			DOCSYNC_URL . 'assets/js/docs-search.js',
			[],
			filemtime( DOCSYNC_PATH . 'assets/js/docs-search.js' ),
			true
		);

		wp_localize_script( 'docsync-search', 'docSyncSearch', [
			'restUrl' => rest_url( 'wp/v2/documentation' ),
			'strings' => [
				'loading'   => __( 'Searching...', 'docsync' ),
				'error'     => __( 'Search failed. Please try again.', 'docsync' ),
				'noResults' => __( 'No results for "%s"', 'docsync' ),
				'viewAll'   => __( 'View all %d results', 'docsync' ),
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
			'docsync-related',
			DOCSYNC_URL . 'assets/css/related-posts.css',
			[],
			filemtime( DOCSYNC_PATH . 'assets/css/related-posts.css' )
		);
	}
}