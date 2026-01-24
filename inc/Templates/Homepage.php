<?php
/**
 * Homepage Column Component
 * 
 * Provides Documentation column for the homepage grid.
 * Hooks into chubes_homepage_columns action.
 */

namespace ChubesDocs\Templates;

use ChubesDocs\Core\Project;

class Homepage {

	public static function init() {
		add_action( 'chubes_homepage_columns', [ self::class, 'render_docs_column' ], 5 );
	}

	/**
	 * Render Documentation column on homepage
	 */
	public static function render_docs_column() {
		$doc_items = self::get_documentation_items();
		?>
		<div class="homepage-column">
			<h3>Documentation</h3>
			<ul class="item-list">
				<?php if ( ! empty( $doc_items ) ) : ?>
					<?php foreach ( $doc_items as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
							<div class="meta">
								<small><?php echo esc_html( $item['type'] ); ?> &bull; <?php echo (int) $item['count']; ?> doc<?php echo ( (int) $item['count'] > 1 ) ? 's' : ''; ?></small>
							</div>
						</li>
					<?php endforeach; ?>
				<?php else : ?>
					<li>Documentation will be available soon.</li>
				<?php endif; ?>
			</ul>
			<div class="list-cta">
				<a class="btn secondary" href="<?php echo esc_url( get_post_type_archive_link( 'documentation' ) ); ?>">View all Docs</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get documentation items for display
	 *
	 * @return array Array of documentation items with name, type, count, and URL
	 */
	private static function get_documentation_items() {
		$doc_items = [];

		$parent_categories = get_terms( [
			'taxonomy'   => 'project',
			'hide_empty' => false,
			'parent'     => 0,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( ! $parent_categories || is_wp_error( $parent_categories ) ) {
			return [];
		}

		foreach ( $parent_categories as $parent_category ) {
			$child_projects = get_terms( [
				'taxonomy'   => 'project',
				'hide_empty' => false,
				'parent'     => $parent_category->term_id,
				'orderby'    => 'name',
				'order'      => 'ASC',
			] );

			if ( ! $child_projects || is_wp_error( $child_projects ) ) {
				continue;
			}

			foreach ( $child_projects as $project ) {
				$repo_info = Project::get_repository_info( $project );
				$doc_count = $repo_info['content_counts']['documentation'] ?? 0;

				if ( $doc_count > 0 ) {
					$doc_items[] = [
						'name'  => $project->name,
						'type'  => rtrim( $parent_category->name, 's' ),
						'count' => $doc_count,
						'url'   => get_term_link( $project ),
					];
				}
			}
		}

		usort( $doc_items, function( $a, $b ) {
			return strcmp( $a['name'], $b['name'] );
		} );

		return array_slice( $doc_items, 0, 3 );
	}
}
