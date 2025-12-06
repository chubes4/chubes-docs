<?php
/**
 * Sync AJAX Handler
 *
 * Handles AJAX requests for manual sync operations.
 */

namespace ChubesDocs\Admin;

use ChubesDocs\Sync\CronSync;

class SyncAjax {

	const ACTION_SYNC_ALL = 'chubes_docs_sync_all';
	const ACTION_SYNC_TERM = 'chubes_docs_sync_term';

	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::ACTION_SYNC_ALL, [ __CLASS__, 'handle_sync_all' ] );
		add_action( 'wp_ajax_' . self::ACTION_SYNC_TERM, [ __CLASS__, 'handle_sync_term' ] );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 */
	public static function enqueue_scripts( string $hook_suffix ): void {
		if ( $hook_suffix !== 'documentation_page_chubes-docs-settings' ) {
			return;
		}

		wp_enqueue_script(
			'chubes-docs-sync',
			CHUBES_DOCS_URL . 'assets/js/admin-sync.js',
			[],
			CHUBES_DOCS_VERSION,
			true
		);

		wp_localize_script( 'chubes-docs-sync', 'chubesDocsSync', [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'syncAllAction' => self::ACTION_SYNC_ALL,
			'nonce'       => wp_create_nonce( self::ACTION_SYNC_ALL ),
			'strings'     => [
				'syncing'  => __( 'Syncing...', 'chubes-docs' ),
				'success'  => __( 'Sync complete!', 'chubes-docs' ),
				'error'    => __( 'Sync failed:', 'chubes-docs' ),
				'noRepos'  => __( 'No repositories configured for sync.', 'chubes-docs' ),
			],
		] );
	}

	/**
	 * Handle sync all request.
	 */
	public static function handle_sync_all(): void {
		check_ajax_referer( self::ACTION_SYNC_ALL, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'chubes-docs' ) ] );
		}

		$results = CronSync::run();

		if ( ! $results['success'] && $results['error'] ) {
			wp_send_json_error( [ 'message' => $results['error'] ] );
		}

		$summary = self::build_summary( $results['results'] );
		wp_send_json_success( $summary );
	}

	/**
	 * Handle sync single term request.
	 */
	public static function handle_sync_term(): void {
		check_ajax_referer( self::ACTION_SYNC_TERM, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'chubes-docs' ) ] );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid term ID.', 'chubes-docs' ) ] );
		}

		$force = isset( $_POST['force'] ) && $_POST['force'] === 'true';
		$result = CronSync::sync_term( $term_id, $force );

		if ( ! $result['success'] && $result['error'] ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		wp_send_json_success( [
			'added'   => count( $result['added'] ?? [] ),
			'updated' => count( $result['updated'] ?? [] ),
			'removed' => count( $result['removed'] ?? [] ),
			'message' => sprintf(
				/* translators: 1: added count, 2: updated count, 3: removed count */
				__( 'Added: %1$d, Updated: %2$d, Removed: %3$d', 'chubes-docs' ),
				count( $result['added'] ?? [] ),
				count( $result['updated'] ?? [] ),
				count( $result['removed'] ?? [] )
			),
		] );
	}

	/**
	 * Build summary from sync results.
	 *
	 * @param array $results Results from CronSync::run().
	 * @return array Summary data.
	 */
	private static function build_summary( array $results ): array {
		$total_added = 0;
		$total_updated = 0;
		$total_removed = 0;
		$repos_synced = 0;
		$errors = [];

		foreach ( $results as $term_id => $result ) {
			if ( $result['success'] ) {
				$repos_synced++;
				$total_added += count( $result['added'] ?? [] );
				$total_updated += count( $result['updated'] ?? [] );
				$total_removed += count( $result['removed'] ?? [] );
			} elseif ( ! empty( $result['error'] ) ) {
				$term = get_term( $term_id );
				$name = $term ? $term->name : "Term {$term_id}";
				$errors[] = "{$name}: {$result['error']}";
			}
		}

		return [
			'repos_synced'  => $repos_synced,
			'total_added'   => $total_added,
			'total_updated' => $total_updated,
			'total_removed' => $total_removed,
			'errors'        => $errors,
			'message'       => sprintf(
				/* translators: 1: repos count, 2: added count, 3: updated count, 4: removed count */
				__( 'Synced %1$d repos. Added: %2$d, Updated: %3$d, Removed: %4$d', 'chubes-docs' ),
				$repos_synced,
				$total_added,
				$total_updated,
				$total_removed
			),
		];
	}
}
