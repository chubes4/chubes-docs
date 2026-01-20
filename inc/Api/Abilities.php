<?php

namespace ChubesDocs\Api;

use ChubesDocs\Sync\RepoSync;
use ChubesDocs\Sync\CronSync;
use ChubesDocs\Core\Codebase;

class Abilities {

	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	public static function register_categories(): void {
		wp_register_ability_category( 'chubes', [
			'label'       => __( 'Chubes', 'chubes-docs' ),
			'description' => __( 'Core abilities for chubes.net monorepo', 'chubes-docs' ),
		] );
	}

	public static function register_abilities(): void {
		$pat = get_option( CronSync::OPTION_PAT );
		if ( empty( $pat ) ) {
			return;
		}

		$github = new \ChubesDocs\Sync\GitHubClient( $pat );
		$repo_sync = new RepoSync( $github );

		wp_register_ability( 'chubes/sync-docs', [
			'label'               => __( 'Sync Documentation', 'chubes-docs' ),
			'description'         => __( 'Sync a single codebase term from GitHub', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'term_id' => [
						'type' => 'integer',
					],
				],
				'required' => [ 'term_id' ],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success'   => [ 'type' => 'boolean' ],
					'term_id'   => [ 'type' => 'integer' ],
					'added'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'updated'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'removed'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'renamed'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'unchanged' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'error'     => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ $repo_sync, 'sync' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'meta'                => [ 'show_in_rest' => true ],
		] );

		wp_register_ability( 'chubes/sync-docs-batch', [
			'label'               => __( 'Sync All Documentation', 'chubes-docs' ),
			'description'         => __( 'Sync multiple codebase terms from GitHub', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'term_ids' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				'required' => [],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success'       => [ 'type' => 'boolean' ],
					'repos_synced'  => [ 'type' => 'integer' ],
					'total_added'   => [ 'type' => 'integer' ],
					'total_updated' => [ 'type' => 'integer' ],
					'total_removed' => [ 'type' => 'integer' ],
					'errors'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'results'      => [ 'type' => 'object' ],
				],
			],
			'execute_callback'    => [ self::class, 'sync_batch_callback' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function sync_batch_callback( array $input ): array {
		$term_ids = $input['term_ids'] ?? [];

		if ( empty( $term_ids ) ) {
			$terms = self::get_syncable_terms();
			$term_ids = wp_list_pluck( $terms, 'term_id' );
		}

		$pat = get_option( CronSync::OPTION_PAT );
		if ( empty( $pat ) ) {
			return [
				'success'       => false,
				'error'         => 'GitHub PAT not configured',
				'results'      => [],
			];
		}

		$github = new \ChubesDocs\Sync\GitHubClient( $pat );
		$repo_sync = new RepoSync( $github );
		$notifier = new \ChubesDocs\Sync\SyncNotifier();

		$results = [];

		foreach ( $term_ids as $term_id ) {
			$result = $repo_sync->sync( $term_id );
			$results[ $term_id ] = $result;

			if ( $result['success'] ) {
				$notifier->send( $term_id, $result );
			}
		}

		$total_added = 0;
		$total_updated = 0;
		$total_removed = 0;
		$repos_synced = 0;
		$errors = [];

		foreach ( $results as $term_id => $result ) {
			if ( $result['success'] ) {
				$repos_synced++;
				$total_added += count( $result['added'] ?? [] );
				$total_updated += count( $result['updated'] ?? [] );
				$total_removed += count( $result['removed'] ?? [] );
			} elseif ( ! empty( $result['error'] ) ) {
				$term = get_term( $term_id );
				$name = $term ? $term->name : "Term {$term_id}";
				$errors[] = "{$name}: {$result['error']}";
			}
		}

		return [
			'success'       => true,
			'repos_synced'  => $repos_synced,
			'total_added'   => $total_added,
			'total_updated' => $total_updated,
			'total_removed' => $total_removed,
			'errors'        => $errors,
			'results'      => $results,
		];
	}

	private static function get_syncable_terms(): array {
		$all_terms = get_terms( [
			'taxonomy'   => Codebase::TAXONOMY,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $all_terms ) ) {
			return [];
		}

		$syncable = [];

		foreach ( $all_terms as $term ) {
			if ( Codebase::get_term_depth( $term ) !== 1 ) {
				continue;
			}

			$github_url = get_term_meta( $term->term_id, 'codebase_github_url', true );
			if ( empty( $github_url ) ) {
				continue;
			}

			$syncable[] = $term;
		}

		return $syncable;
	}
}
