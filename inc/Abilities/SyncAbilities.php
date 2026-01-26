<?php
/**
 * Sync Abilities
 *
 * Provides WP Abilities API integration for documentation synchronization.
 * Supports both single-term and batch sync operations via GitHub.
 */

namespace ChubesDocs\Abilities;

use ChubesDocs\Sync\RepoSync;
use ChubesDocs\Sync\CronSync;
use ChubesDocs\Sync\GitHubClient;
use ChubesDocs\Sync\SyncNotifier;
use ChubesDocs\Core\Project;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SyncAbilities {

	public static function register(): void {
		$pat = get_option( CronSync::OPTION_PAT );
		if ( empty( $pat ) ) {
			return;
		}

		wp_register_ability( 'chubes/sync-docs', [
			'label'               => __( 'Sync Documentation', 'chubes-docs' ),
			'description'         => __( 'Sync documentation from GitHub. Pass term_id for single sync, term_ids for batch, or neither for all syncable terms.', 'chubes-docs' ),
			'category'            => 'chubes',
			'input_schema'        => [
				'type'       => 'object',
				'properties' => [
					'term_id'  => [ 'type' => 'integer' ],
					'term_ids' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
			],
			'output_schema'       => [
				'type'       => 'object',
				'properties' => [
					'success'       => [ 'type' => 'boolean' ],
					'term_id'       => [ 'type' => 'integer' ],
					'repos_synced'  => [ 'type' => 'integer' ],
					'total_added'   => [ 'type' => 'integer' ],
					'total_updated' => [ 'type' => 'integer' ],
					'total_removed' => [ 'type' => 'integer' ],
					'added'         => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'updated'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'removed'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'renamed'       => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'unchanged'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'errors'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'results'       => [ 'type' => 'object' ],
					'error'         => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => [ self::class, 'sync_callback' ],
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'meta'                => [ 'show_in_rest' => true ],
		] );
	}

	public static function sync_callback( array $input ): array {
		$pat = get_option( CronSync::OPTION_PAT );
		if ( empty( $pat ) ) {
			return [
				'success' => false,
				'error'   => 'GitHub PAT not configured',
			];
		}

		$github    = new GitHubClient( $pat );
		$repo_sync = new RepoSync( $github );

		if ( isset( $input['term_id'] ) ) {
			return $repo_sync->sync( $input['term_id'] );
		}

		$term_ids = $input['term_ids'] ?? [];
		if ( empty( $term_ids ) ) {
			$terms    = self::get_syncable_terms();
			$term_ids = wp_list_pluck( $terms, 'term_id' );
		}

		$notifier = new SyncNotifier();
		$results  = [];

		foreach ( $term_ids as $term_id ) {
			$result              = $repo_sync->sync( $term_id );
			$results[ $term_id ] = $result;

			if ( $result['success'] ) {
				$notifier->send( $term_id, $result );
			}
		}

		$total_added   = 0;
		$total_updated = 0;
		$total_removed = 0;
		$repos_synced  = 0;
		$errors        = [];

		foreach ( $results as $term_id => $result ) {
			if ( $result['success'] ) {
				$repos_synced++;
				$total_added   += count( $result['added'] ?? [] );
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
			'results'       => $results,
		];
	}

	private static function get_syncable_terms(): array {
		$all_terms = get_terms( [
			'taxonomy'   => Project::TAXONOMY,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $all_terms ) ) {
			return [];
		}

		$syncable = [];

		foreach ( $all_terms as $term ) {
			if ( Project::get_term_depth( $term ) !== 0 ) {
				continue;
			}

			$github_url = get_term_meta( $term->term_id, 'project_github_url', true );
			if ( empty( $github_url ) ) {
				continue;
			}

			$syncable[] = $term;
		}

		return $syncable;
	}
}
