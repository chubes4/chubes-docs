<?php
/**
 * Homepage Column Component
 * 
 * Provides Documentation column for the homepage grid.
 * Hooks into chubes_homepage_columns action.
 */

namespace DocSync\Templates;

use DocSync\Core\Project;

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
			<p class="docs-api-hint">
				Also available via API: <code>GET /wp-json/docsync/v1/docs</code>
			</p>
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

		// Get depth 0 project terms with GitHub URLs (synced projects)
		$project_terms = get_terms( [
			'taxonomy'   => 'project',
			'parent'     => 0,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'     => 'project_github_url',
					'compare' => 'EXISTS',
				],
			],
		] );

		if ( ! $project_terms || is_wp_error( $project_terms ) ) {
			return [];
		}

		foreach ( $project_terms as $project_term ) {
			$repo_info = Project::get_repository_info( $project_term );
			$doc_count = $repo_info['content_counts']['documentation'] ?? 0;

			if ( $doc_count > 0 ) {
				$project_type = Project::get_project_type( $project_term );
				$type_term = $project_type ? get_term_by( 'slug', $project_type, 'project_type' ) : null;
				$type_display = $type_term ? $type_term->name : 'Project';

				$doc_items[] = [
					'name'  => $project_term->name,
					'type'  => $type_display,
					'count' => $doc_count,
					'url'   => get_term_link( $project_term ),
				];
			}
		}

		// Sort by count descending
		usort( $doc_items, function( $a, $b ) {
			return $b['count'] <=> $a['count'];
		} );

		return array_slice( $doc_items, 0, 3 );
	}
}
