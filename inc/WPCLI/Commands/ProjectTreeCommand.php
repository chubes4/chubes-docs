<?php

namespace ChubesDocs\WPCLI\Commands;

use ChubesDocs\Core\Project;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProjectTreeCommand {
	public static function run( array $args, array $assoc_args ): void {
		$top_level_terms = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $top_level_terms ) ) {
			WP_CLI::error( $top_level_terms->get_error_message() );
		}

		if ( empty( $top_level_terms ) ) {
			WP_CLI::warning( 'No project terms found.' );
			return;
		}

		foreach ( $top_level_terms as $type_term ) {
			WP_CLI::line( self::format_term_line( $type_term, false ) );

			$children = get_terms( [
				'taxonomy'   => Project::TAXONOMY,
				'parent'     => $type_term->term_id,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			] );

			if ( is_wp_error( $children ) || empty( $children ) ) {
				WP_CLI::line( '' );
				continue;
			}

			$count = count( $children );
			foreach ( $children as $index => $child ) {
				$is_last = ( $index === $count - 1 );
				$prefix = $is_last ? '└── ' : '├── ';
				WP_CLI::line( $prefix . self::format_term_line( $child, true ) );
			}

			WP_CLI::line( '' );
		}
	}

	private static function format_term_line( \WP_Term $term, bool $is_project ): string {
		$parts = [ "{$term->slug} ({$term->term_id})" ];

		if ( $is_project ) {
			$doc_count = self::get_doc_count( $term->term_id );
			if ( $doc_count > 0 ) {
				$parts[] = "[{$doc_count} docs]";
			}

			$github_url = Project::get_github_url( $term->term_id );
			if ( $github_url ) {
				$parts[] = $github_url;
			}
		}

		return implode( ' ', $parts );
	}

	private static function get_doc_count( int $term_id ): int {
		$query = new \WP_Query( [
			'post_type'      => 'documentation',
			'tax_query'      => [
				[
					'taxonomy' => Project::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		] );

		$count = $query->found_posts;
		wp_reset_postdata();

		return $count;
	}
}
