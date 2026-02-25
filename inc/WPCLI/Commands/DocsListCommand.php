<?php
/**
 * WP-CLI command for listing documentation posts.
 *
 * Delegates to the docsync/search-docs ability for filtered listing.
 * Uses direct WP_Query only for unfiltered listing (no search ability needed).
 *
 * @package DocSync\WPCLI\Commands
 */

namespace DocSync\WPCLI\Commands;

use DocSync\Core\Documentation;
use DocSync\Core\Project;
use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocsListCommand {

	/**
	 * List documentation posts.
	 *
	 * ## OPTIONS
	 *
	 * [--project=<slug>]
	 * : Filter by project slug or term ID
	 *
	 * [--status=<status>]
	 * : Post status to filter by
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--per-page=<number>]
	 * : Number of results per page
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--page=<number>]
	 * : Page number
	 * ---
	 * default: 1
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
	 *     # List all docs
	 *     wp docsync docs list
	 *
	 *     # List docs for a specific project
	 *     wp docsync docs list --project=data-machine
	 *
	 *     # List docs as JSON
	 *     wp docsync docs list --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$project  = $assoc_args['project'] ?? null;
		$status   = sanitize_text_field( $assoc_args['status'] ?? 'publish' );
		$per_page = absint( $assoc_args['per-page'] ?? 20 );
		$page     = absint( $assoc_args['page'] ?? 1 );
		$format   = sanitize_key( $assoc_args['format'] ?? 'table' );

		$query_args = [
			'post_type'      => Documentation::POST_TYPE,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
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

		$query = new \WP_Query( $query_args );

		if ( empty( $query->posts ) ) {
			WP_CLI::warning( 'No documentation found.' );
			return;
		}

		$rows = [];
		foreach ( $query->posts as $post ) {
			$terms        = get_the_terms( $post->ID, Project::TAXONOMY );
			$project_term = $terms ? Project::get_project_term( $terms ) : null;
			$primary_term = $terms ? Project::get_primary_term( $terms ) : null;

			$rows[] = [
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'slug'    => $post->post_name,
				'project' => $project_term ? $project_term->slug : '',
				'path'    => $primary_term ? Project::build_term_hierarchy_path( $primary_term ) : '',
				'status'  => $post->post_status,
			];
		}

		WP_CLI::log( sprintf( 'Showing %d of %d docs (page %d)', count( $rows ), $query->found_posts, $page ) );
		Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
	}
}
