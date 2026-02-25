<?php

namespace DocSync\WPCLI\Commands;

use DocSync\Core\Project;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProjectTreeCommand {
	public static function run( array $args, array $assoc_args ): void {
		$project_type_terms = get_terms( [
			'taxonomy'   => 'project_type',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $project_type_terms ) ) {
			WP_CLI::error( $project_type_terms->get_error_message() );
		}

		if ( empty( $project_type_terms ) ) {
			WP_CLI::warning( 'No project type terms found.' );
			return;
		}

		foreach ( $project_type_terms as $type_term ) {
			WP_CLI::line( self::format_type_line( $type_term ) );

			$projects = get_terms( [
				'taxonomy'   => Project::TAXONOMY,
				'parent'     => 0,
				'hide_empty' => false,
				'meta_query' => [
					[
						'key'   => 'project_type',
						'value' => $type_term->slug,
					],
				],
				'orderby'    => 'name',
				'order'      => 'ASC',
			] );

			if ( is_wp_error( $projects ) || empty( $projects ) ) {
				WP_CLI::line( '' );
				continue;
			}

			$count = count( $projects );
			foreach ( $projects as $index => $project ) {
				$is_last = ( $index === $count - 1 );
				$prefix = $is_last ? '└── ' : '├── ';
				WP_CLI::line( $prefix . self::format_project_line( $project ) );
			}

			WP_CLI::line( '' );
		}
	}

	private static function format_type_line( \WP_Term $term ): string {
		return "{$term->name} ({$term->slug})";
	}

	private static function format_project_line( \WP_Term $term ): string {
		$parts = [ "{$term->slug} ({$term->term_id})" ];

		$doc_count = self::get_doc_count( $term->term_id );
		if ( $doc_count > 0 ) {
			$parts[] = "[{$doc_count} docs]";
		}

		$github_url = Project::get_github_url( $term->term_id );
		if ( $github_url ) {
			$parts[] = $github_url;
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
