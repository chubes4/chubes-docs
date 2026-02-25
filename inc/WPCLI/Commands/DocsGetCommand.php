<?php
/**
 * WP-CLI command for retrieving a single documentation post.
 *
 * Delegates to the docsync/get-doc ability.
 *
 * @package DocSync\WPCLI\Commands
 */

namespace DocSync\WPCLI\Commands;

use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocsGetCommand {

	/**
	 * Get a single documentation post by ID or slug.
	 *
	 * Returns markdown content by default, matching the REST API's
	 * agent-friendly format. Delegates to the docsync/get-doc ability.
	 *
	 * ## OPTIONS
	 *
	 * <id_or_slug>
	 * : Post ID or slug to retrieve
	 *
	 * [--field=<field>]
	 * : Return a specific field only (title, content, excerpt, slug, link, project)
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: markdown
	 * options:
	 *   - markdown
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get doc by ID (returns markdown)
	 *     wp docsync docs get 5288
	 *
	 *     # Get doc by slug
	 *     wp docsync docs get basetool-class
	 *
	 *     # Get just the content as markdown
	 *     wp docsync docs get 5288 --field=content
	 *
	 *     # Get doc metadata as JSON
	 *     wp docsync docs get 5288 --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function run( array $args, array $assoc_args ): void {
		$id_or_slug = $args[0] ?? '';
		$field      = $assoc_args['field'] ?? null;
		$format     = sanitize_key( $assoc_args['format'] ?? 'markdown' );

		if ( empty( $id_or_slug ) ) {
			WP_CLI::error( 'Provide a post ID or slug.' );
		}

		$ability = wp_get_ability( 'docsync/get-doc' );
		if ( ! $ability ) {
			WP_CLI::error( 'docsync/get-doc ability not registered.' );
		}

		// Build ability input.
		$input = [];
		if ( is_numeric( $id_or_slug ) ) {
			$input['id'] = absint( $id_or_slug );
		} else {
			$input['slug'] = sanitize_title( $id_or_slug );
		}

		$result = $ability->execute( $input );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( isset( $result['error'] ) ) {
			WP_CLI::error( $result['error'] );
		}

		// Single field output.
		if ( $field ) {
			$value = self::extract_field( $result, $field );
			if ( null === $value ) {
				WP_CLI::error( "Unknown field: {$field}" );
			}
			WP_CLI::line( $value );
			return;
		}

		// Markdown format — print title + content for agent consumption.
		if ( 'markdown' === $format ) {
			WP_CLI::line( "# {$result['title']}" );
			WP_CLI::line( '' );
			if ( ! empty( $result['excerpt'] ) ) {
				WP_CLI::line( "> {$result['excerpt']}" );
				WP_CLI::line( '' );
			}
			if ( ! empty( $result['content'] ) ) {
				WP_CLI::line( $result['content'] );
			}
			return;
		}

		// Structured output (JSON/YAML).
		$item = [
			'id'             => $result['id'],
			'title'          => $result['title'],
			'slug'           => $result['meta']['sync_source_file'] ?? '',
			'excerpt'        => $result['excerpt'],
			'link'           => $result['link'],
			'project'        => $result['project']['slug'] ?? '',
			'source_file'    => $result['meta']['sync_source_file'] ?? '',
			'sync_timestamp' => $result['meta']['sync_timestamp'] ?? '',
			'content_format' => $result['content_format'] ?? 'markdown',
		];

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			Utils\format_items( $format, [ $item ], array_keys( $item ) );
		}
	}

	/**
	 * Extract a specific field from the ability result.
	 *
	 * @param array  $result Ability result.
	 * @param string $field  Field name.
	 * @return string|null
	 */
	private static function extract_field( array $result, string $field ): ?string {
		return match ( $field ) {
			'title'   => $result['title'] ?? '',
			'content' => $result['content'] ?? '',
			'excerpt' => $result['excerpt'] ?? '',
			'link'    => $result['link'] ?? '',
			'project' => $result['project']['slug'] ?? '',
			default   => null,
		};
	}
}
