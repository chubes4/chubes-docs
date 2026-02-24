<?php
/**
 * WP-CLI command for retrieving a single documentation post.
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

class DocsGetCommand {

	/**
	 * Get a single documentation post by ID or slug.
	 *
	 * Returns markdown content by default, matching the REST API's
	 * agent-friendly format.
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
	 *     wp chubes docs get 5288
	 *
	 *     # Get doc by slug
	 *     wp chubes docs get basetool-class
	 *
	 *     # Get just the content as markdown
	 *     wp chubes docs get 5288 --field=content
	 *
	 *     # Get doc metadata as JSON
	 *     wp chubes docs get 5288 --format=json
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

		$post = self::find_doc( $id_or_slug );

		if ( ! $post ) {
			WP_CLI::error( "Documentation not found: {$id_or_slug}" );
		}

		$markdown = get_post_meta( $post->ID, '_sync_markdown', true );

		// Single field output.
		if ( $field ) {
			$value = self::get_field_value( $post, $field, $markdown );
			if ( null === $value ) {
				WP_CLI::error( "Unknown field: {$field}" );
			}
			WP_CLI::line( $value );
			return;
		}

		// Markdown format — print title + content for agent consumption.
		if ( 'markdown' === $format ) {
			WP_CLI::line( "# {$post->post_title}" );
			WP_CLI::line( '' );
			if ( ! empty( $post->post_excerpt ) ) {
				WP_CLI::line( "> {$post->post_excerpt}" );
				WP_CLI::line( '' );
			}
			if ( ! empty( $markdown ) ) {
				WP_CLI::line( $markdown );
			} else {
				WP_CLI::line( wp_strip_all_tags( $post->post_content ) );
			}
			return;
		}

		// Structured output (JSON/YAML).
		$terms        = get_the_terms( $post->ID, Project::TAXONOMY );
		$project_term = $terms ? Project::get_project_term( $terms ) : null;
		$primary_term = $terms ? Project::get_primary_term( $terms ) : null;

		$item = [
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'excerpt'        => $post->post_excerpt,
			'status'         => $post->post_status,
			'link'           => get_permalink( $post->ID ),
			'project'        => $project_term ? $project_term->slug : '',
			'path'           => $primary_term ? Project::build_term_hierarchy_path( $primary_term ) : '',
			'source_file'    => get_post_meta( $post->ID, '_sync_source_file', true ),
			'sync_timestamp' => get_post_meta( $post->ID, '_sync_timestamp', true ),
			'content_format' => ! empty( $markdown ) ? 'markdown' : 'html',
		];

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		} else {
			Utils\format_items( $format, [ $item ], array_keys( $item ) );
		}
	}

	/**
	 * Find a documentation post by ID or slug.
	 *
	 * @param string $id_or_slug Post ID or slug.
	 * @return \WP_Post|null
	 */
	private static function find_doc( string $id_or_slug ): ?\WP_Post {
		// Try as numeric ID first.
		if ( is_numeric( $id_or_slug ) ) {
			$post = get_post( absint( $id_or_slug ) );
			if ( $post && Documentation::POST_TYPE === $post->post_type ) {
				return $post;
			}
		}

		// Try as slug.
		$posts = get_posts( [
			'post_type'   => Documentation::POST_TYPE,
			'name'        => sanitize_title( $id_or_slug ),
			'post_status' => 'any',
			'numberposts' => 1,
		] );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get a specific field value from a doc.
	 *
	 * @param \WP_Post $post     The documentation post.
	 * @param string   $field    Field name.
	 * @param string   $markdown Stored markdown content.
	 * @return string|null
	 */
	private static function get_field_value( \WP_Post $post, string $field, string $markdown ): ?string {
		$terms        = get_the_terms( $post->ID, Project::TAXONOMY );
		$project_term = $terms ? Project::get_project_term( $terms ) : null;

		return match ( $field ) {
			'title'   => $post->post_title,
			'content' => ! empty( $markdown ) ? $markdown : wp_strip_all_tags( $post->post_content ),
			'excerpt' => $post->post_excerpt,
			'slug'    => $post->post_name,
			'link'    => get_permalink( $post->ID ),
			'project' => $project_term ? $project_term->slug : '',
			default   => null,
		};
	}
}
