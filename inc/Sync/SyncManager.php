<?php

namespace ChubesDocs\Sync;

class SyncManager {

	public static function sync_post(
		string $source_file,
		string $title,
		string $content,
		array $codebase_path,
		string $excerpt = '',
		bool $force = false
	): array {
		// Auto-detect and convert markdown to HTML
		if ( MarkdownProcessor::isMarkdown( $content ) ) {
			$project_slug = isset( $codebase_path[1] ) ? sanitize_title( $codebase_path[1] ) : '';
			$processor    = new MarkdownProcessor( $project_slug, $source_file );
			$content      = $processor->process( $content );
		}

		$new_hash         = self::compute_hash( $content );
		$existing_post_id = self::find_post_by_source( $source_file );

		if ( $existing_post_id ) {
			$stored_hash = get_post_meta( $existing_post_id, '_sync_hash', true );

			if ( ! $force && $stored_hash === $new_hash ) {
				return array(
					'success'          => true,
					'action'           => 'unchanged',
					'post_id'          => $existing_post_id,
					'title'            => $title,
					'source_file'      => $source_file,
					'previous_hash'    => $stored_hash,
					'new_hash'         => $new_hash,
					'changes_detected' => false,
					'codebase_path'    => $codebase_path,
				);
			}

			$result = wp_update_post( array(
				'ID'           => $existing_post_id,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			), true );

			if ( is_wp_error( $result ) ) {
				return array(
					'success'     => false,
					'error'       => $result->get_error_message(),
					'source_file' => $source_file,
				);
			}

			self::update_sync_meta( $existing_post_id, $source_file, $new_hash );

			$resolved = chubes_resolve_codebase_path( $codebase_path, true );
			if ( $resolved['success'] && $resolved['leaf_term_id'] ) {
				wp_set_object_terms( $existing_post_id, $resolved['leaf_term_id'], CHUBES_CODEBASE_TAXONOMY );
			}

			return array(
				'success'          => true,
				'action'           => 'updated',
				'post_id'          => $existing_post_id,
				'title'            => $title,
				'source_file'      => $source_file,
				'previous_hash'    => $stored_hash,
				'new_hash'         => $new_hash,
				'changes_detected' => true,
				'codebase_path'    => $codebase_path,
			);
		}

		$post_id = wp_insert_post( array(
			'post_type'    => 'documentation',
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'success'     => false,
				'error'       => $post_id->get_error_message(),
				'source_file' => $source_file,
			);
		}

		self::update_sync_meta( $post_id, $source_file, $new_hash );

		$resolved = chubes_resolve_codebase_path( $codebase_path, true );
		if ( $resolved['success'] && $resolved['leaf_term_id'] ) {
			wp_set_object_terms( $post_id, $resolved['leaf_term_id'], CHUBES_CODEBASE_TAXONOMY );
		}

		return array(
			'success'          => true,
			'action'           => 'created',
			'post_id'          => $post_id,
			'title'            => $title,
			'source_file'      => $source_file,
			'previous_hash'    => null,
			'new_hash'         => $new_hash,
			'changes_detected' => true,
			'codebase_path'    => $codebase_path,
		);
	}

	public static function find_post_by_source( string $source_file ): ?int {
		$posts = get_posts( array(
			'post_type'      => 'documentation',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_sync_source_file',
					'value' => $source_file,
				),
			),
		) );

		return ! empty( $posts ) ? $posts[0]->ID : null;
	}

	public static function compute_hash( string $content ): string {
		$normalized = trim( $content );
		return hash( 'sha256', $normalized );
	}

	public static function needs_sync( int $post_id, string $new_hash ): bool {
		$stored_hash = get_post_meta( $post_id, '_sync_hash', true );
		return $stored_hash !== $new_hash;
	}

	public static function get_sync_meta( int $post_id ): array {
		return array(
			'source_file' => get_post_meta( $post_id, '_sync_source_file', true ),
			'hash'        => get_post_meta( $post_id, '_sync_hash', true ),
			'timestamp'   => get_post_meta( $post_id, '_sync_timestamp', true ),
		);
	}

	public static function update_sync_meta( int $post_id, string $source_file, string $hash ): void {
		update_post_meta( $post_id, '_sync_source_file', $source_file );
		update_post_meta( $post_id, '_sync_hash', $hash );
		update_post_meta( $post_id, '_sync_timestamp', gmdate( 'c' ) );
	}
}
