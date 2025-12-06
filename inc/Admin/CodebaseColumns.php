<?php
/**
 * Codebase Taxonomy Columns
 *
 * Adds custom columns to the codebase taxonomy list table.
 * Shows GitHub URL, WP.org installs, and sync status.
 */

namespace ChubesDocs\Admin;

use ChubesDocs\Core\Codebase;

class CodebaseColumns {

	public static function init(): void {
		add_filter( 'manage_edit-codebase_columns', [ __CLASS__, 'add_columns' ] );
		add_filter( 'manage_codebase_custom_column', [ __CLASS__, 'render_column' ], 10, 3 );
		add_filter( 'manage_edit-codebase_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
	}

	/**
	 * Add custom columns to the taxonomy list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_columns( array $columns ): array {
		$new_columns = [];

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( $key === 'name' ) {
				$new_columns['github']      = __( 'GitHub', 'chubes-docs' );
				$new_columns['installs']    = __( 'Installs', 'chubes-docs' );
				$new_columns['sync_status'] = __( 'Sync', 'chubes-docs' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 * @return string Column content.
	 */
	public static function render_column( string $content, string $column_name, int $term_id ): string {
		$term = get_term( $term_id, Codebase::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return $content;
		}

		if ( Codebase::get_term_depth( $term ) !== 1 ) {
			return '&mdash;';
		}

		switch ( $column_name ) {
			case 'github':
				$content = self::render_github_column( $term_id );
				break;

			case 'installs':
				$content = self::render_installs_column( $term_id );
				break;

			case 'sync_status':
				$content = self::render_sync_column( $term_id );
				break;
		}

		return $content;
	}

	/**
	 * Define sortable columns.
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public static function sortable_columns( array $columns ): array {
		$columns['installs'] = 'installs';
		return $columns;
	}

	/**
	 * Render GitHub column.
	 *
	 * @param int $term_id Term ID.
	 * @return string HTML content.
	 */
	private static function render_github_column( int $term_id ): string {
		$github_url = get_term_meta( $term_id, 'codebase_github_url', true );

		if ( empty( $github_url ) ) {
			return '<span class="dashicons dashicons-minus" style="color:#999;"></span>';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener" title="%s"><span class="dashicons dashicons-github" style="color:#333;"></span></a>',
			esc_url( $github_url ),
			esc_attr( $github_url )
		);
	}

	/**
	 * Render installs column.
	 *
	 * @param int $term_id Term ID.
	 * @return string HTML content.
	 */
	private static function render_installs_column( int $term_id ): string {
		$wp_url = get_term_meta( $term_id, 'codebase_wp_url', true );

		if ( empty( $wp_url ) ) {
			return '&mdash;';
		}

		$installs = (int) get_term_meta( $term_id, 'codebase_installs', true );

		if ( $installs >= 1000000 ) {
			$formatted = number_format( floor( $installs / 1000000 ) ) . 'M+';
		} elseif ( $installs >= 1000 ) {
			$formatted = number_format( floor( $installs / 1000 ) ) . 'K+';
		} else {
			$formatted = number_format( $installs ) . '+';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $wp_url ),
			esc_html( $formatted )
		);
	}

	/**
	 * Render sync status column.
	 *
	 * @param int $term_id Term ID.
	 * @return string HTML content.
	 */
	private static function render_sync_column( int $term_id ): string {
		$github_url = get_term_meta( $term_id, 'codebase_github_url', true );

		if ( empty( $github_url ) ) {
			return '&mdash;';
		}

		$status = get_term_meta( $term_id, 'codebase_sync_status', true );
		$last_sync = get_term_meta( $term_id, 'codebase_last_sync_time', true );
		$files_synced = get_term_meta( $term_id, 'codebase_files_synced', true );
		$error = get_term_meta( $term_id, 'codebase_sync_error', true );

		$icon = '';
		$title = '';

		switch ( $status ) {
			case 'success':
				$icon = '<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>';
				$title = sprintf(
					/* translators: 1: file count, 2: relative time */
					__( '%1$d files synced %2$s', 'chubes-docs' ),
					(int) $files_synced,
					human_time_diff( $last_sync ) . ' ' . __( 'ago', 'chubes-docs' )
				);
				break;

			case 'syncing':
				$icon = '<span class="dashicons dashicons-update" style="color:#0073aa;"></span>';
				$title = __( 'Syncing...', 'chubes-docs' );
				break;

			case 'failed':
				$icon = '<span class="dashicons dashicons-warning" style="color:#dc3232;"></span>';
				$title = $error ?: __( 'Sync failed', 'chubes-docs' );
				break;

			default:
				$icon = '<span class="dashicons dashicons-clock" style="color:#999;"></span>';
				$title = __( 'Never synced', 'chubes-docs' );
				break;
		}

		return sprintf( '<span title="%s">%s</span>', esc_attr( $title ), $icon );
	}
}
