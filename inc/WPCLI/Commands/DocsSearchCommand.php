<?php
/**
 * WP-CLI command for searching documentation.
 *
 * Delegates to the chubes/search-docs ability.
 *
 * @package ChubesDocs\WPCLI\Commands
 */

namespace ChubesDocs\WPCLI\Commands;

use ChubesDocs\Core\Project;
use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocsSearchCommand {

	/**
	 * Search documentation by keyword.
	 *
	 * Delegates to the chubes/search-docs ability for full-text search
	 * across doc titles and content.
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : Search query string
	 *
	 * [--project=<slug>]
	 * : Limit search to a specific project (slug or term ID)
	 *
	 * [--per-page=<number>]
	 * : Number of results
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Search all docs
	 *     wp chubes docs search "abilities API"
	 *
	 *     # Search within a project
	 *     wp chubes docs search "webhook" --project=data-machine
	 *
	 *     # Search with JSON output
	 *     wp chubes docs search "handler" --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$query    = $args[0] ?? '';
		$project  = $assoc_args['project'] ?? null;
		$per_page = absint( $assoc_args['per-page'] ?? 20 );
		$format   = sanitize_key( $assoc_args['format'] ?? 'table' );

		if ( empty( $query ) ) {
			WP_CLI::error( 'Provide a search query.' );
		}

		$ability = wp_get_ability( 'chubes/search-docs' );
		if ( ! $ability ) {
			WP_CLI::error( 'chubes/search-docs ability not registered.' );
		}

		// Build ability input.
		$input = [
			'query'    => $query,
			'per_page' => min( $per_page, 50 ),
		];

		// Resolve project slug to term ID if needed.
		if ( $project ) {
			if ( is_numeric( $project ) ) {
				$input['project'] = absint( $project );
			} else {
				$term = get_term_by( 'slug', sanitize_title( $project ), Project::TAXONOMY );
				if ( ! $term || is_wp_error( $term ) ) {
					WP_CLI::error( "Project not found: {$project}" );
				}
				$input['project'] = $term->term_id;
			}
		}

		$result = $ability->execute( $input );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( empty( $result['items'] ) ) {
			WP_CLI::warning( "No docs found for: {$query}" );
			return;
		}

		$rows = [];
		foreach ( $result['items'] as $item ) {
			$rows[] = [
				'id'      => $item['id'],
				'title'   => $item['title'],
				'project' => $item['project']['slug'] ?? '',
				'excerpt' => mb_strimwidth( $item['excerpt'] ?? '', 0, 80, '...' ),
			];
		}

		WP_CLI::log( sprintf( 'Found %d docs matching "%s"', $result['total'], $query ) );
		Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
	}
}
