<?php

namespace ChubesDocs\WPCLI\Commands;

use ChubesDocs\Sync\CronSync;
use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocsSyncCommand {
	public static function run( array $args, array $assoc_args ): void {
		$term_id = absint( $args[0] ?? 0 );
		$force = isset( $assoc_args['force'] ) ? (bool) $assoc_args['force'] : false;
		$format = sanitize_key( $assoc_args['format'] ?? 'table' );

		if ( empty( $term_id ) ) {
			WP_CLI::error( 'Project term ID is required (e.g. wp chubes docs sync 123)' );
		}

		if ( ! in_array( $format, [ 'table', 'json', 'yaml' ], true ) ) {
			WP_CLI::error( '--format must be one of: table, json, yaml' );
		}

		$result = CronSync::sync_term( $term_id, $force );

		if ( empty( $result['success'] ) ) {
			WP_CLI::error( $result['error'] ?? 'Sync failed' );
		}

		$rows = [
			[
				'term_id'  => (string) $term_id,
				'added'    => (string) count( $result['added'] ?? [] ),
				'updated'  => (string) count( $result['updated'] ?? [] ),
				'removed'  => (string) count( $result['removed'] ?? [] ),
				'unchanged'=> (string) count( $result['unchanged'] ?? [] ),
				'error'    => (string) ( $result['error'] ?? '' ),
			],
		];

		WP_CLI::success( 'Docs sync complete.' );
		Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
	}
}
