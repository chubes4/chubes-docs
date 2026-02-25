<?php
/**
 * WP-CLI command for documentation synchronization.
 * Supports single term sync and batch sync via --all flag.
 */

namespace DocSync\WPCLI\Commands;

use DocSync\Abilities\SyncAbilities;
use DocSync\Sync\CronSync;
use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocsSyncCommand {

	/**
	 * Sync documentation from GitHub.
	 *
	 * ## OPTIONS
	 *
	 * [<term_id>]
	 * : Project term ID to sync (required unless --all is used)
	 *
	 * [--all]
	 * : Sync all projects with GitHub URLs configured
	 *
	 * [--force]
	 * : Force sync even if SHA unchanged
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml)
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Sync single project
	 *     wp docsync docs sync 42
	 *
	 *     # Sync all projects
	 *     wp docsync docs sync --all
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$sync_all = Utils\get_flag_value( $assoc_args, 'all', false );
		$format   = sanitize_key( $assoc_args['format'] ?? 'table' );

		if ( ! in_array( $format, [ 'table', 'json', 'yaml' ], true ) ) {
			WP_CLI::error( '--format must be one of: table, json, yaml' );
		}

		if ( $sync_all ) {
			self::sync_all( $format );
			return;
		}

		$term_id = absint( $args[0] ?? 0 );
		if ( empty( $term_id ) ) {
			WP_CLI::error( 'Project term ID required (e.g. wp docsync docs sync 123) or use --all' );
		}

		$force = Utils\get_flag_value( $assoc_args, 'force', false );
		self::sync_single( $term_id, $force, $format );
	}

	private static function sync_single( int $term_id, bool $force, string $format ): void {
		$result = CronSync::sync_term( $term_id, $force );

		if ( empty( $result['success'] ) ) {
			WP_CLI::error( $result['error'] ?? 'Sync failed' );
		}

		$rows = [
			[
				'term_id'   => (string) $term_id,
				'added'     => (string) count( $result['added'] ?? [] ),
				'updated'   => (string) count( $result['updated'] ?? [] ),
				'removed'   => (string) count( $result['removed'] ?? [] ),
				'unchanged' => (string) count( $result['unchanged'] ?? [] ),
				'error'     => (string) ( $result['error'] ?? '' ),
			],
		];

		WP_CLI::success( 'Docs sync complete.' );
		Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
	}

	private static function sync_all( string $format ): void {
		WP_CLI::log( 'Syncing all projects with GitHub URLs...' );

		$result = SyncAbilities::sync_callback( [] );

		if ( empty( $result['success'] ) ) {
			WP_CLI::error( $result['error'] ?? 'Sync failed' );
		}

		$rows = [];
		foreach ( $result['results'] as $term_id => $term_result ) {
			$term   = get_term( $term_id );
			$status = $term_result['success'] ? 'OK' : 'FAIL';
			$error  = $term_result['error'] ?? '';

			// Show inline warning for failed projects
			if ( ! $term_result['success'] && ! empty( $error ) ) {
				$project_name = $term ? $term->name : "term {$term_id}";
				WP_CLI::warning( "{$project_name}: {$error}" );
			}

			$rows[] = [
				'term_id'   => (string) $term_id,
				'project'   => $term ? $term->name : '',
				'added'     => (string) count( $term_result['added'] ?? [] ),
				'updated'   => (string) count( $term_result['updated'] ?? [] ),
				'removed'   => (string) count( $term_result['removed'] ?? [] ),
				'unchanged' => (string) count( $term_result['unchanged'] ?? [] ),
				'status'    => $status,
			];
		}

		if ( ! empty( $result['errors'] ) ) {
			foreach ( $result['errors'] as $error ) {
				WP_CLI::warning( $error );
			}
		}

		WP_CLI::success( sprintf(
			'Synced %d repos: %d added, %d updated, %d removed',
			$result['repos_synced'],
			$result['total_added'],
			$result['total_updated'],
			$result['total_removed']
		) );

		if ( ! empty( $rows ) ) {
			Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
		}
	}
}
