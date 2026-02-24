<?php
/**
 * WP-CLI command for searching documentation.
 *
 * @package ChubesDocs\WPCLI\Commands
 */

namespace ChubesDocs\WPCLI\Commands;

use ChubesDocs\Core\Documentation;
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
	 * Performs a WordPress search across doc titles and content.
	 *
	 * ## OPTIONS
	 *
	 * <query>
	 * : Search query string
	 *
	 * [--project=<slug>]
	 * : Limit search to a specific project
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

		$query_args = [
			'post_type'      => Documentation::POST_TYPE,
			'post_status'    => 'publish',
			's'              => sanitize_text_field( $query ),
			'posts_per_page' => $per_page,
			'orderby'        => 'relevance',
		];

		if ( $project ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => Project::TAXONOMY,
					'field'    => is_numeric( $project ) ? 'term_id' : 'slug',
					'terms'    => $project,
				],
			];
		}

		$wp_query = new \WP_Query( $query_args );

		if ( empty( $wp_query->posts ) ) {
			WP_CLI::warning( "No docs found for: {$query}" );
			return;
		}

		$rows = [];
		foreach ( $wp_query->posts as $post ) {
			$terms        = get_the_terms( $post->ID, Project::TAXONOMY );
			$project_term = $terms ? Project::get_project_term( $terms ) : null;
			$primary_term = $terms ? Project::get_primary_term( $terms ) : null;

			$rows[] = [
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'project' => $project_term ? $project_term->slug : '',
				'path'    => $primary_term ? Project::build_term_hierarchy_path( $primary_term ) : '',
				'excerpt' => mb_strimwidth( $post->post_excerpt, 0, 80, '...' ),
			];
		}

		WP_CLI::log( sprintf( 'Found %d docs matching "%s"', $wp_query->found_posts, $query ) );
		Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
	}
}
