<?php

namespace ChubesDocs\Sync;

use ChubesDocs\Core\Codebase;

class SyncManager {

	public static function sync_post(
		string $source_file,
		string $title,
		string $content,
		int $project_term_id,
		int $filesize,
		string $timestamp,
		array $subpath = [],
		string $excerpt = '',
		bool $force = false
	): array {
		$project_term = get_term( $project_term_id, Codebase::TAXONOMY );
		if ( ! $project_term || is_wp_error( $project_term ) ) {
			return [
				'success' => false,
				'error'   => 'Invalid project term ID',
			];
		}
		$project_slug = $project_term->slug;

		if ( MarkdownProcessor::isMarkdown( $content ) ) {
			$processor = new MarkdownProcessor( $project_slug, $source_file, $project_term_id );
			$content   = $processor->process( $content );
		}

		$leaf_term_id = self::resolve_subpath( $project_term_id, $subpath );
		if ( is_wp_error( $leaf_term_id ) ) {
			return [
				'success' => false,
				'error'   => $leaf_term_id->get_error_message(),
			];
		}

		$existing_post_id = self::find_post_by_source( $source_file, $project_term_id );

		if ( $existing_post_id ) {
			$stored_filesize = get_post_meta( $existing_post_id, '_sync_filesize', true );
			$stored_timestamp = get_post_meta( $existing_post_id, '_sync_timestamp', true );

			if ( ! $force && (string)$stored_filesize === (string)$filesize && $stored_timestamp === $timestamp ) {
				return [
					'success'          => true,
					'action'           => 'unchanged',
					'post_id'          => $existing_post_id,
					'title'            => $title,
					'source_file'      => $source_file,
					'changes_detected' => false,
				];
			}

			$result = wp_update_post( [
				'ID'           => $existing_post_id,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			], true );

			if ( is_wp_error( $result ) ) {
				return [
					'success'     => false,
					'error'       => $result->get_error_message(),
					'source_file' => $source_file,
				];
			}

			self::update_sync_meta( $existing_post_id, $source_file, $filesize, $timestamp );
			wp_set_object_terms( $existing_post_id, $leaf_term_id, Codebase::TAXONOMY );

			return [
				'success'          => true,
				'action'           => 'updated',
				'post_id'          => $existing_post_id,
				'title'            => $title,
				'source_file'      => $source_file,
				'changes_detected' => true,
			];
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'documentation',
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return [
				'success'     => false,
				'error'       => $post_id->get_error_message(),
				'source_file' => $source_file,
			];
		}

		self::update_sync_meta( $post_id, $source_file, $filesize, $timestamp );
		wp_set_object_terms( $post_id, $leaf_term_id, Codebase::TAXONOMY );

		return [
			'success'          => true,
			'action'           => 'created',
			'post_id'          => $post_id,
			'title'            => $title,
			'source_file'      => $source_file,
			'changes_detected' => true,
		];
	}

	private static function resolve_subpath( int $parent_term_id, array $subpath ): int|\WP_Error {
		if ( empty( $subpath ) ) {
			return $parent_term_id;
		}

		$current_parent = $parent_term_id;

		foreach ( $subpath as $part_name ) {
			$slug = sanitize_title( $part_name );

			$existing = get_terms( [
				'taxonomy'   => Codebase::TAXONOMY,
				'parent'     => $current_parent,
				'slug'       => $slug,
				'hide_empty' => false,
				'number'     => 1,
			] );

			if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
				$current_parent = $existing[0]->term_id;
				continue;
			}

			$result = wp_insert_term( $part_name, Codebase::TAXONOMY, [
				'parent' => $current_parent,
				'slug'   => $slug,
			] );

			if ( is_wp_error( $result ) ) {
				if ( isset( $result->error_data['term_exists'] ) ) {
					$term = get_term( $result->error_data['term_exists'], Codebase::TAXONOMY );
					if ( $term && $term->parent === $current_parent ) {
						$current_parent = $term->term_id;
						continue;
					}
					return new \WP_Error( 'term_parent_mismatch', "Term '{$part_name}' exists but under wrong parent" );
				}
				return $result;
			}

			$current_parent = $result['term_id'];
		}

		return $current_parent;
	}

	public static function find_post_by_source( string $source_file, int $project_term_id ): ?int {
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
			'tax_query'      => array(
				array(
					'taxonomy'         => Codebase::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $project_term_id,
					'include_children' => true,
				),
			),
		) );

		return ! empty( $posts ) ? $posts[0]->ID : null;
	}

	public static function update_sync_meta( int $post_id, string $source_file, int $filesize, string $timestamp ): void {
		update_post_meta( $post_id, '_sync_source_file', $source_file );
		update_post_meta( $post_id, '_sync_filesize', $filesize );
		update_post_meta( $post_id, '_sync_timestamp', $timestamp );
	}
}
