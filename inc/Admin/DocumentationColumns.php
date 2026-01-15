<?php
/**
 * Documentation Post Columns
 *
 * Replaces the published column with an updated column
 * on the documentation post list table.
 */

namespace ChubesDocs\Admin;

class DocumentationColumns {

	public static function init(): void {
		add_filter( 'manage_documentation_posts_columns', [ __CLASS__, 'add_columns' ], 20 );
		add_action( 'manage_documentation_posts_custom_column', [ __CLASS__, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-documentation_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
	}

	/**
	 * Replace the date column label.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_columns( array $columns ): array {
		if ( isset( $columns['date'] ) ) {
			$columns['date'] = __( 'Updated', 'chubes-docs' );
		}

		return $columns;
	}

	/**
	 * Render the updated column.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_column( string $column, int $post_id ): void {
		if ( $column !== 'date' ) {
			return;
		}

		echo esc_html( get_the_modified_date( get_option( 'date_format' ), $post_id ) );
	}

	/**
	 * Keep the updated column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public static function sortable_columns( array $columns ): array {
		if ( isset( $columns['date'] ) ) {
			$columns['date'] = 'modified';
		}

		return $columns;
	}
}
