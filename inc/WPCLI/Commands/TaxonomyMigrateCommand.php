<?php

namespace ChubesDocs\WPCLI\Commands;

use ChubesDocs\Core\Project;
use WP_CLI;
use WP_CLI\Utils;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxonomyMigrateCommand {
	public static function run( array $args, array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );
		$format = sanitize_key( $assoc_args['format'] ?? 'table' );

		if ( ! in_array( $format, [ 'table', 'json', 'yaml' ], true ) ) {
			WP_CLI::error( '--format must be one of: table, json, yaml' );
		}

		WP_CLI::log( $dry_run ? 'DRY RUN MODE - No changes will be made' : 'EXECUTION MODE - Changes will be made' );

		// Step 1: Create project_type terms
		WP_CLI::log( 'Step 1: Creating project_type terms...' );
		$project_types_created = self::create_project_types( $dry_run );
		WP_CLI::log( "Created {$project_types_created} project type terms" );

		// Step 2: Migrate taxonomy terms from 'codebase' to 'project'
		WP_CLI::log( 'Step 2: Migrating taxonomy terms...' );
		$terms_migrated = self::migrate_taxonomy_terms( $dry_run );
		WP_CLI::log( "Migrated {$terms_migrated} taxonomy terms" );

		// Step 3: Migrate term metadata
		WP_CLI::log( 'Step 3: Migrating term metadata...' );
		$meta_migrated = self::migrate_term_metadata( $dry_run );
		WP_CLI::log( "Migrated {$meta_migrated} metadata keys" );

		// Step 4: Extract project types from hierarchy
		WP_CLI::log( 'Step 4: Extracting project types from hierarchy...' );
		$types_assigned = self::assign_project_types_from_hierarchy( $dry_run );
		WP_CLI::log( "Assigned project types to {$types_assigned} posts" );

		WP_CLI::success( $dry_run ? 'Dry run completed successfully' : 'Migration completed successfully' );

		$summary = [
			[
				'step' => 'Create project types',
				'count' => $project_types_created,
				'status' => 'completed',
			],
			[
				'step' => 'Migrate taxonomy terms',
				'count' => $terms_migrated,
				'status' => 'completed',
			],
			[
				'step' => 'Migrate term metadata',
				'count' => $meta_migrated,
				'status' => 'completed',
			],
			[
				'step' => 'Assign project types',
				'count' => $types_assigned,
				'status' => 'completed',
			],
		];

		Utils\format_items( $format, $summary, [ 'step', 'count', 'status' ] );
	}

	private static function create_project_types( bool $dry_run ): int {
		$project_types = [
			'wordpress-plugins' => 'WordPress Plugins',
			'wordpress-themes' => 'WordPress Themes',
			'cli' => 'CLI Tools',
		];

		$count = 0;

		foreach ( $project_types as $slug => $name ) {
			$existing = get_term_by( 'slug', $slug, 'project_type' );
			if ( $existing ) {
				WP_CLI::log( "Project type already exists: {$slug}" );
				continue;
			}

			if ( ! $dry_run ) {
				$result = wp_insert_term( $name, 'project_type', [ 'slug' => $slug ] );
				if ( is_wp_error( $result ) ) {
					WP_CLI::warning( "Failed to create project type {$slug}: " . $result->get_error_message() );
					continue;
				}
			}

			WP_CLI::log( "Created project type: {$slug}" );
			$count++;
		}

		return $count;
	}

	private static function migrate_taxonomy_terms( bool $dry_run ): int {
		global $wpdb;

		// Get all terms from the old 'codebase' taxonomy
		$old_terms = get_terms( [
			'taxonomy' => 'codebase', // Old taxonomy name
			'hide_empty' => false,
		] );

		if ( is_wp_error( $old_terms ) || empty( $old_terms ) ) {
			WP_CLI::log( 'No terms found in old codebase taxonomy' );
			return 0;
		}

		$count = 0;

		foreach ( $old_terms as $term ) {
			WP_CLI::log( "Migrating term: {$term->name} ({$term->slug})" );

			if ( ! $dry_run ) {
				// Update the term's taxonomy in the database
				$updated = $wpdb->update(
					$wpdb->term_taxonomy,
					[ 'taxonomy' => 'project' ],
					[ 'term_id' => $term->term_id, 'taxonomy' => 'codebase' ]
				);

				if ( $updated === false ) {
					WP_CLI::warning( "Failed to migrate term {$term->term_id}" );
					continue;
				}
			}

			$count++;
		}

		return $count;
	}

	private static function migrate_term_metadata( bool $dry_run ): int {
		global $wpdb;

		// Get all termmeta with codebase_ prefix
		$meta_keys = $wpdb->get_results(
			"SELECT meta_id, meta_key FROM {$wpdb->termmeta} WHERE meta_key LIKE 'codebase_%'"
		);

		if ( empty( $meta_keys ) ) {
			WP_CLI::log( 'No codebase_ metadata found' );
			return 0;
		}

		$count = 0;

		foreach ( $meta_keys as $meta ) {
			$new_key = str_replace( 'codebase_', 'project_', $meta->meta_key );
			WP_CLI::log( "Migrating metadata: {$meta->meta_key} → {$new_key}" );

			if ( ! $dry_run ) {
				$updated = $wpdb->update(
					$wpdb->termmeta,
					[ 'meta_key' => $new_key ],
					[ 'meta_id' => $meta->meta_id ]
				);

				if ( $updated === false ) {
					WP_CLI::warning( "Failed to migrate metadata {$meta->meta_id}" );
					continue;
				}
			}

			$count++;
		}

		return $count;
	}

	private static function assign_project_types_from_hierarchy( bool $dry_run ): int {
		// Get all top-level terms (categories) from the project taxonomy
		$top_level_terms = get_terms( [
			'taxonomy' => 'project',
			'parent' => 0,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $top_level_terms ) || empty( $top_level_terms ) ) {
			WP_CLI::log( 'No top-level project terms found' );
			return 0;
		}

		$project_type_mapping = [
			'wordpress-plugins' => 'wordpress-plugins',
			'wordpress-themes' => 'wordpress-themes',
			'cli' => 'cli',
		];

		$count = 0;

		foreach ( $top_level_terms as $category_term ) {
			if ( ! isset( $project_type_mapping[ $category_term->slug ] ) ) {
				WP_CLI::log( "Skipping unknown category: {$category_term->slug}" );
				continue;
			}

			$project_type_slug = $project_type_mapping[ $category_term->slug ];
			$project_type_term = get_term_by( 'slug', $project_type_slug, 'project_type' );

			if ( ! $project_type_term ) {
				WP_CLI::warning( "Project type not found: {$project_type_slug}" );
				continue;
			}

			WP_CLI::log( "Assigning project type '{$project_type_slug}' to posts under '{$category_term->slug}'" );

			// Get all posts under this category (recursive)
			$posts = get_posts( [
				'post_type' => 'documentation',
				'tax_query' => [
					[
						'taxonomy' => 'project',
						'field' => 'term_id',
						'terms' => $category_term->term_id,
					],
				],
				'posts_per_page' => -1,
				'fields' => 'ids',
			] );

			foreach ( $posts as $post_id ) {
				if ( ! $dry_run ) {
					$result = wp_set_object_terms( $post_id, $project_type_slug, 'project_type', false );
					if ( is_wp_error( $result ) ) {
						WP_CLI::warning( "Failed to assign project type to post {$post_id}" );
						continue;
					}
				}

				$count++;
			}
		}

		return $count;
	}
}