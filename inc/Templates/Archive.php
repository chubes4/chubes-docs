<?php
/**
 * Archive Template Hooks
 * 
 * Hooks into theme's archive.php to render documentation and codebase content.
 * Provides hierarchical archive rendering for the documentation system.
 */

namespace ChubesDocs\Templates;

use ChubesDocs\Core\Codebase;
use ChubesDocs\Core\Documentation;

class Archive {

	public static function init() {
		add_action( 'chubes_archive_header_after', [ self::class, 'render_header_extras' ] );
		add_filter( 'chubes_archive_content', [ self::class, 'filter_content' ], 10, 2 );
		add_filter( 'get_the_archive_title', [ self::class, 'filter_archive_title' ], 15 );
	}

	/**
	 * Render header extras for codebase project pages
	 * 
	 * Adds download stats and action buttons for child codebase terms.
	 */
	public static function render_header_extras() {
		if ( ! is_tax( 'codebase' ) ) {
			return;
		}

		$term = get_queried_object();

		if ( $term->parent === 0 ) {
			return;
		}

		$repo_info = Codebase::get_repository_info( $term );
		$parent_term = get_term( $term->parent, 'codebase' );
		$category_name = $parent_term && ! is_wp_error( $parent_term ) ? $parent_term->name : 'Project';
		$singular_type = rtrim( $category_name, 's' );
		$has_download = ! empty( $repo_info['wp_url'] );
		$download_text = $has_download ? 'Download ' . $singular_type : 'View ' . $singular_type;
		?>

		<?php if ( ! empty( $repo_info['installs'] ) && $repo_info['installs'] > 0 ) : ?>
			<div class="project-stats">
				<div class="stat-item">
					<span class="stat-number"><?php echo number_format( $repo_info['installs'] ); ?></span>
					<span class="stat-label">Downloads</span>
				</div>
			</div>
		<?php endif; ?>

		<div class="project-actions">
			<?php if ( ! empty( $repo_info['wp_url'] ) ) : ?>
				<a href="<?php echo esc_url( $repo_info['wp_url'] ); ?>" class="btn primary" target="_blank">
					<svg class="btn-icon"><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/fonts/social-icons.svg#icon-wordpress"></use></svg>
					<?php echo esc_html( $download_text ); ?>
				</a>
			<?php endif; ?>

			<?php if ( ! empty( $repo_info['github_url'] ) ) : ?>
				<a href="<?php echo esc_url( $repo_info['github_url'] ); ?>" class="btn secondary" target="_blank">
					<svg class="btn-icon"><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/fonts/social-icons.svg#icon-github"></use></svg>
					View on GitHub
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Filter archive content for documentation and codebase contexts
	 * 
	 * Returns custom HTML for codebase taxonomy and documentation archives.
	 * Returns unmodified content for other archive types.
	 *
	 * @param string $content        The current archive content
	 * @param mixed  $queried_object The queried object
	 * @return string HTML content or unmodified content
	 */
	public static function filter_content( $content, $queried_object ) {
		if ( is_tax( 'codebase' ) ) {
			ob_start();
			self::render_codebase_content();
			return ob_get_clean();
		}

		if ( is_post_type_archive( 'documentation' ) ) {
			ob_start();
			self::render_documentation_archive();
			return ob_get_clean();
		}

		return $content;
	}

	/**
	 * Render codebase taxonomy content
	 * 
	 * Category terms (depth 0): Grid of project cards
	 * All other terms (depth 1+): Hierarchical documentation listing
	 */
	private static function render_codebase_content() {
		$term = get_queried_object();

		if ( $term->parent === 0 ) {
			self::render_category_content( $term );
		} else {
			self::render_term_content( $term );
		}
	}

	/**
	 * Render category page content (depth 0 term)
	 * 
	 * Shows grid of project cards for all child terms.
	 */
	private static function render_category_content( $term ) {
		$project_terms = get_terms( [
			'taxonomy'   => 'codebase',
			'hide_empty' => false,
			'parent'     => $term->term_id,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( empty( $project_terms ) || is_wp_error( $project_terms ) ) : ?>
			<div class="no-content">
				<p><?php echo esc_html( $term->name ); ?> will be listed here soon.</p>
			</div>
			<?php
			return;
		endif;
		?>

		<div class="codebase-cards-grid">
			<?php
			foreach ( $project_terms as $project ) :
				$repo_info = Codebase::get_repository_info( $project );

				if ( $repo_info['has_content'] ) :
					CodebaseCard::render( $project, $repo_info );
				endif;
			endforeach;
			?>
		</div>
		<?php
	}

	/**
	 * Render term content with hierarchical grouping
	 * 
	 * Shows posts directly under the term at top (no header),
	 * then child terms as sections with headers, recursively.
	 *
	 * @param \WP_Term $term The codebase term
	 */
	private static function render_term_content( $term ) {
		$direct_posts = self::get_direct_posts_for_term( $term );
		$child_terms = self::get_sorted_child_terms( $term );
		$has_content = $direct_posts->have_posts() || ! empty( $child_terms );

		if ( ! $has_content ) {
			self::render_no_content_message( $term );
			return;
		}

		// Render posts directly under this term (no header)
		if ( $direct_posts->have_posts() ) {
			self::render_documentation_cards( $direct_posts );
		}

		// Render child term sections
		foreach ( $child_terms as $child_term ) {
			self::render_term_hierarchy_section( $child_term, 1 );
		}
	}

	/**
	 * Render a term section with header and recursive children
	 *
	 * @param \WP_Term $term  The codebase term
	 * @param int      $depth The current depth level (1 = h3, 2 = h4, 3+ = h5)
	 */
	private static function render_term_hierarchy_section( $term, $depth ) {
		$direct_posts = self::get_direct_posts_for_term( $term );
		$child_terms = self::get_sorted_child_terms( $term );
		$header_tag = self::get_header_tag( $depth );
		?>

		<section class="term-section depth-<?php echo esc_attr( $depth ); ?>">
			<div class="term-section-header">
				<<?php echo $header_tag; ?>><?php echo esc_html( $term->name ); ?></<?php echo $header_tag; ?>>
			</div>

			<?php if ( $term->description ) : ?>
				<p class="term-description"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>

			<?php if ( $direct_posts->have_posts() ) : ?>
				<?php self::render_documentation_cards( $direct_posts ); ?>
				<div class="view-all-wrapper">
					<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="btn secondary">View all →</a>
				</div>
			<?php endif; ?>

			<?php foreach ( $child_terms as $child_term ) : ?>
				<?php self::render_term_hierarchy_section( $child_term, $depth + 1 ); ?>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * Get the appropriate header tag for a given depth
	 *
	 * @param int $depth The depth level
	 * @return string The header tag (h3, h4, or h5)
	 */
	private static function get_header_tag( $depth ) {
		if ( $depth <= 1 ) {
			return 'h3';
		}
		if ( $depth === 2 ) {
			return 'h4';
		}
		return 'h5';
	}

	/**
	 * Render documentation cards from a WP_Query
	 *
	 * @param \WP_Query $query The query with documentation posts
	 */
	private static function render_documentation_cards( $query ) {
		?>
		<div class="documentation-cards">
			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<a href="<?php the_permalink(); ?>" class="documentation-card">
					<div class="card-header">
						<h4><?php the_title(); ?></h4>
						<?php if ( has_excerpt() ) : ?>
							<p class="card-description"><?php the_excerpt(); ?></p>
						<?php endif; ?>
					</div>
					<div class="card-stats">
						<span class="stat-item">Last updated: <?php echo get_the_modified_date(); ?></span>
					</div>
				</a>
			<?php endwhile; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render no content message for a term
	 *
	 * @param \WP_Term $term The codebase term
	 */
	private static function render_no_content_message( $term ) {
		$project_term = Codebase::get_project_term( [ $term ] );
		$repo_info = $project_term ? Codebase::get_repository_info( $project_term ) : [];
		?>
		<div class="no-docs">
			<p>Documentation for this section is coming soon.</p>
			<?php if ( ! empty( $repo_info['github_url'] ) ) : ?>
				<p>In the meantime, check out the <a href="<?php echo esc_url( $repo_info['github_url'] ); ?>" target="_blank">GitHub repository</a> for technical details.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get posts directly assigned to a term (not inherited from children)
	 *
	 * @param \WP_Term $term The codebase term
	 * @return \WP_Query
	 */
	private static function get_direct_posts_for_term( $term ) {
		return new \WP_Query( [
			'post_type'      => Documentation::POST_TYPE,
			'tax_query'      => [
				[
					'taxonomy'         => Codebase::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $term->term_id,
					'include_children' => false,
				],
			],
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'modified',
			'order'          => 'DESC',
		] );
	}

	/**
	 * Get child terms sorted by most recently modified post
	 *
	 * @param \WP_Term $term The parent term
	 * @return array Sorted array of WP_Term objects
	 */
	private static function get_sorted_child_terms( $term ) {
		$children = get_terms( [
			'taxonomy'   => Codebase::TAXONOMY,
			'parent'     => $term->term_id,
			'hide_empty' => false,
		] );

		if ( empty( $children ) || is_wp_error( $children ) ) {
			return [];
		}

		// Get latest modified date for each child term
		$terms_with_dates = [];
		foreach ( $children as $child ) {
			$latest_date = self::get_term_latest_modified_date( $child );
			$terms_with_dates[] = [
				'term' => $child,
				'date' => $latest_date,
			];
		}

		// Sort by date DESC (most recent first), terms without posts go last
		usort( $terms_with_dates, function( $a, $b ) {
			if ( $a['date'] === null && $b['date'] === null ) {
				return strcmp( $a['term']->name, $b['term']->name );
			}
			if ( $a['date'] === null ) {
				return 1;
			}
			if ( $b['date'] === null ) {
				return -1;
			}
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		} );

		return array_column( $terms_with_dates, 'term' );
	}

	/**
	 * Get the most recent post modified date under a term (including children)
	 *
	 * @param \WP_Term $term The codebase term
	 * @return string|null The modified date or null if no posts
	 */
	private static function get_term_latest_modified_date( $term ) {
		$query = new \WP_Query( [
			'post_type'      => Documentation::POST_TYPE,
			'tax_query'      => [
				[
					'taxonomy'         => Codebase::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $term->term_id,
					'include_children' => true,
				],
			],
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );

		if ( $query->have_posts() ) {
			$post_id = $query->posts[0];
			return get_post_field( 'post_modified', $post_id );
		}

		return null;
	}

	/**
	 * Render documentation archive page
	 * 
	 * Shows all documentation grouped by parent codebase category.
	 */
	private static function render_documentation_archive() {
		$parent_categories = get_terms( [
			'taxonomy'   => 'codebase',
			'hide_empty' => true,
			'parent'     => 0,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( ! $parent_categories || is_wp_error( $parent_categories ) ) {
			return;
		}

		foreach ( $parent_categories as $parent_category ) :
			$project_terms = get_terms( [
				'taxonomy'   => 'codebase',
				'hide_empty' => true,
				'parent'     => $parent_category->term_id,
				'orderby'    => 'name',
				'order'      => 'ASC',
			] );

			$has_documentation = false;
			$projects_with_docs = [];

			if ( $project_terms && ! is_wp_error( $project_terms ) ) {
				foreach ( $project_terms as $project_term ) {
					$repo_info = Codebase::get_repository_info( $project_term );
					$doc_count = $repo_info['content_counts']['documentation'] ?? 0;

					if ( $doc_count > 0 ) {
						$has_documentation = true;
						$projects_with_docs[] = [
							'term'      => $project_term,
							'repo_info' => $repo_info,
							'doc_count' => $doc_count,
						];
					}
				}
			}

			if ( ! $has_documentation ) {
				continue;
			}
			?>

			<section class="documentation-category-section">
				<div class="category-header">
					<h2><?php echo esc_html( ucfirst( $parent_category->name ) ); ?></h2>
					<?php if ( $parent_category->description ) : ?>
						<p><?php echo esc_html( $parent_category->description ); ?></p>
					<?php endif; ?>
				</div>

				<div class="documentation-cards">
					<?php foreach ( $projects_with_docs as $project ) : ?>
						<div class="documentation-card">
							<div class="card-header">
								<h3><?php echo esc_html( $project['term']->name ); ?></h3>
								<?php if ( $project['term']->description ) : ?>
									<p class="card-description"><?php echo esc_html( wp_trim_words( $project['term']->description, 20 ) ); ?></p>
								<?php endif; ?>
							</div>

							<div class="card-stats">
								<span class="stat-item"><?php echo $project['doc_count']; ?> guide<?php echo $project['doc_count'] !== 1 ? 's' : ''; ?></span>
								<?php if ( $project['repo_info']['installs'] > 0 ) : ?>
									<span class="stat-item"><?php echo number_format( $project['repo_info']['installs'] ); ?> downloads</span>
								<?php endif; ?>
							</div>

							<div class="card-actions">
								<a href="<?php echo esc_url( get_term_link( $project['term'] ) ); ?>" class="btn primary">
									View Documentation →
								</a>

								<div class="external-links">
									<?php if ( $project['repo_info']['wp_url'] ) : ?>
										<a href="<?php echo esc_url( $project['repo_info']['wp_url'] ); ?>" class="external-link" target="_blank" title="Download from WordPress.org">
											<svg><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/fonts/social-icons.svg#icon-wordpress"></use></svg>
										</a>
									<?php endif; ?>

									<?php if ( $project['repo_info']['github_url'] ) : ?>
										<a href="<?php echo esc_url( $project['repo_info']['github_url'] ); ?>" class="external-link" target="_blank" title="View on GitHub">
											<svg><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/fonts/social-icons.svg#icon-github"></use></svg>
										</a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach;
	}

	/**
	 * Filter archive title for documentation and codebase contexts
	 */
	public static function filter_archive_title( $title ) {
		if ( is_post_type_archive( 'documentation' ) ) {
			return 'Documentation';
		}

		if ( is_tax( 'codebase' ) ) {
			$term = get_queried_object();
			return $term->name;
		}

		return $title;
	}
}
