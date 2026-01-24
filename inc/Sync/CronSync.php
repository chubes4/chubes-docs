<?php
/**
 * Cron Sync Runner
 *
 * Handles scheduled sync of all GitHub repositories with documentation.
 * Loops through codebase terms with GitHub URLs and syncs each one.
 */

namespace ChubesDocs\Sync;

use ChubesDocs\Core\Project;

class CronSync {

	const CRON_HOOK = 'chubes_docs_github_sync';
	const OPTION_INTERVAL = 'chubes_docs_sync_interval';
	const OPTION_PAT = 'chubes_docs_github_pat';

	public static function init(): void {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
		add_action( 'admin_init', [ __CLASS__, 'schedule' ] );
		register_deactivation_hook( CHUBES_DOCS_PATH . 'chubes-docs.php', [ __CLASS__, 'unschedule' ] );
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		$interval = get_option( self::OPTION_INTERVAL, 'twicedaily' );
		wp_schedule_event( time(), $interval, self::CRON_HOOK );
	}

	/**
	 * Unschedule the cron event.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Reschedule with new interval.
	 *
	 * @param string $interval New interval (hourly, twicedaily, daily).
	 */
	public static function reschedule( string $interval ): void {
		self::unschedule();
		wp_schedule_event( time(), $interval, self::CRON_HOOK );
	}

	/**
	 * Run the sync for all codebases with GitHub URLs.
	 *
	 * @return array Results for each synced codebase.
	 */
	public static function run(): array {
		$pat = get_option( self::OPTION_PAT );
		if ( empty( $pat ) ) {
			return [
				'success' => false,
				'error'   => 'GitHub PAT not configured',
				'results' => [],
			];
		}

		$terms = self::get_syncable_terms();
		if ( empty( $terms ) ) {
			return [
				'success' => true,
				'error'   => null,
				'results' => [],
			];
		}

		$github = new GitHubClient( $pat );
		$repo_sync = new RepoSync( $github );
		$notifier = new SyncNotifier();

		$results = [];

		foreach ( $terms as $term ) {
			$result = $repo_sync->sync( $term->term_id );
			$results[ $term->term_id ] = $result;

			if ( $result['success'] ) {
				$notifier->send( $term->term_id, $result );
			}
		}

		return [
			'success' => true,
			'error'   => null,
			'results' => $results,
		];
	}

	/**
	 * Sync a single term by ID.
	 *
	 * @param int $term_id Term ID.
	 * @return array Sync result.
	 */
	public static function sync_term( int $term_id ): array {
		$pat = get_option( self::OPTION_PAT );
		if ( empty( $pat ) ) {
			return [
				'success' => false,
				'error'   => 'GitHub PAT not configured',
			];
		}

		$github = new GitHubClient( $pat );
		$repo_sync = new RepoSync( $github );
		$notifier = new SyncNotifier();

		$result = $repo_sync->sync( $term_id );

		if ( $result['success'] ) {
			$notifier->send( $term_id, $result );
		}

		return $result;
	}

	/**
	 * Get all codebase terms that have a GitHub URL configured.
	 * Only returns depth-1 terms (project level).
	 *
	 * @return array Array of WP_Term objects.
	 */
	private static function get_syncable_terms(): array {
		$all_terms = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $all_terms ) ) {
			return [];
		}

		$syncable = [];

		foreach ( $all_terms as $term ) {
			if ( Project::get_term_depth( $term ) !== 1 ) {
				continue;
			}

			$github_url = get_term_meta( $term->term_id, 'project_github_url', true );
			if ( empty( $github_url ) ) {
				continue;
			}

			$syncable[] = $term;
		}

		return $syncable;
	}

	/**
	 * Get next scheduled sync time.
	 *
	 * @return int|false Timestamp or false if not scheduled.
	 */
	public static function get_next_scheduled(): int|false {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get current sync interval.
	 *
	 * @return string Interval name.
	 */
	public static function get_interval(): string {
		return get_option( self::OPTION_INTERVAL, 'twicedaily' );
	}
}
