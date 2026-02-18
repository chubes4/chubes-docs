<?php
/**
 * Archive Template Hooks
 * 
 * Hooks into theme's archive.php to render documentation and project content.
 * Provides hierarchical archive rendering for the documentation system.
 */

namespace ChubesDocs\Templates;

use ChubesDocs\Core\Project;
use ChubesDocs\Core\Documentation;

class Archive {

	public static function init() {
		add_action( 'chubes_archive_header_after', [ self::class, 'render_header_extras' ] );
		add_filter( 'chubes_archive_content', [ self::class, 'filter_content' ], 10, 2 );
		add_filter( 'get_the_archive_title', [ self::class, 'filter_archive_title' ], 15 );
		add_filter( 'chubes_show_archive_description', [ self::class, 'filter_show_description' ] );
		add_action( 'chubes_single_after_content', [ self::class, 'render_github_link' ], 10, 2 );
	}

	/**
	 * Render header extras for project pages and docs archive
	 *
	 * Renders a project info card with description, stats, and action buttons
	 * on project pages. On the main docs archive, renders an API help block.
	 */
	public static function render_header_extras() {
		if ( is_post_type_archive( 'documentation' ) ) {
			self::render_api_help_block();
			return;
		}

		if ( ! is_tax( 'project' ) ) {
			return;
		}

		$term = get_queried_object();

		if ( $term->parent !== 0 ) {
			return;
		}

		$repo_info = Project::get_repository_info( $term );
		$project_type = Project::get_project_type( $term );
		$type_term = get_term_by( 'slug', $project_type, 'project_type' );
		$category_name = $type_term && ! is_wp_error( $type_term ) ? $type_term->name : 'Project';
		$singular_type = rtrim( $category_name, 's' );
		$has_download = ! empty( $repo_info['wp_url'] );
		$download_text = $has_download ? 'Download ' . $singular_type : 'View ' . $singular_type;
		$doc_count = $repo_info['content_counts']['documentation'] ?? 0;
		$has_stats = ( ! empty( $repo_info['installs'] ) && $repo_info['installs'] > 0 ) || $doc_count > 0;
		$has_actions = ! empty( $repo_info['wp_url'] ) || ! empty( $repo_info['github_url'] );
		?>

		<div class="project-info-card">
			<?php if ( $term->description ) : ?>
				<p class="project-description"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>

			<?php if ( $has_stats ) : ?>
				<div class="project-stats">
					<?php if ( ! empty( $repo_info['installs'] ) && $repo_info['installs'] > 0 ) : ?>
						<div class="stat-item">
							<span class="stat-number"><?php echo number_format( $repo_info['installs'] ); ?></span>
							<span class="stat-label">Downloads</span>
						</div>
					<?php endif; ?>

					<?php if ( $doc_count > 0 ) : ?>
						<div class="stat-item">
							<span class="stat-number"><?php echo $doc_count; ?></span>
							<span class="stat-label"><?php echo $doc_count === 1 ? 'Doc' : 'Docs'; ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_actions ) : ?>
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
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Filter archive content for documentation and project contexts
	 * 
	 * Returns custom HTML for project taxonomy and documentation archives.
	 * Returns unmodified content for other archive types.
	 *
	 * @param string $content        The current archive content
	 * @param mixed  $queried_object The queried object
	 * @return string HTML content or unmodified content
	 */
	public static function filter_content( $content, $queried_object ) {
		if ( is_tax( 'project' ) ) {
			ob_start();
			self::render_project_content();
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
		 * Render project taxonomy content
		 * 
		 * Project terms (depth 0): Project info and hierarchical documentation
		 * All other terms (depth 1+): Hierarchical documentation listing
		 */
		private static function render_project_content() {
		$term = get_queried_object();

		if ( $term->parent === 0 ) {
			self::render_top_level_project_content( $term );
		} else {
			self::render_term_content( $term );
		}
	}

	/**
	 * Render project page content (depth 0 term)
	 * 
	 * Shows hierarchical documentation for the project.
	 */
	private static function render_top_level_project_content( $term ) {
		// Render the hierarchical documentation
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
	 * Render term content with hierarchical grouping
	 * 
	 * Shows posts directly under the term at top (no header),
	 * then child terms as sections with headers, recursively.
	 *
	 * @param \WP_Term $term The project term
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
	 * @param \WP_Term $term  The project term
	 * @param int      $depth The current depth level (1 = h3, 2 = h4, 3+ = h5)
	 */
	private static function render_term_hierarchy_section( $term, $depth ) {
		$direct_posts = self::get_direct_posts_for_term( $term );
		$child_terms = self::get_sorted_child_terms( $term );

		$has_direct_posts = $direct_posts->have_posts();
		$has_children_with_content = self::term_has_descendant_content( $child_terms );

		if ( ! $has_direct_posts && ! $has_children_with_content ) {
			return;
		}

		$header_tag = self::get_header_tag( $depth );
		?>

		<section class="term-section depth-<?php echo esc_attr( $depth ); ?>">
			<div class="term-section-header">
				<<?php echo $header_tag; ?>><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a></<?php echo $header_tag; ?>>
			</div>

			<?php if ( $term->description ) : ?>
				<p class="term-description"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>

			<?php if ( $has_direct_posts ) : ?>
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
	 * Check if any child terms have content (posts or their own descendants with posts)
	 *
	 * @param array $child_terms Array of WP_Term objects
	 * @return bool True if any descendant has posts
	 */
	private static function term_has_descendant_content( $child_terms ) {
		foreach ( $child_terms as $child ) {
			$posts = self::get_direct_posts_for_term( $child );
			if ( $posts->have_posts() ) {
				return true;
			}
			$grandchildren = self::get_sorted_child_terms( $child );
			if ( self::term_has_descendant_content( $grandchildren ) ) {
				return true;
			}
		}
		return false;
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
		<div class="cards-grid">
			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<div class="doc-card doc-card--compact">
					<div class="card-header">
						<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
						<?php if ( has_excerpt() ) : ?>
							<p class="card-description"><?php echo wp_trim_words( get_the_excerpt(), 20, '...' ); ?></p>
						<?php endif; ?>
					</div>

					<div class="card-stats">
						<span class="stat-item">Updated <?php echo get_the_modified_date(); ?></span>
					</div>

					<div class="card-actions">
						<a href="<?php the_permalink(); ?>" class="btn primary">
							View Docs →
						</a>
					</div>
				</div>
			<?php endwhile; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render no content message for a term
	 *
	 * @param \WP_Term $term The project term
	 */
	private static function render_no_content_message( $term ) {
		$project_term = Project::get_project_term( [ $term ] );
		$repo_info = $project_term ? Project::get_repository_info( $project_term ) : [];
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
	 * @param \WP_Term $term The project term
	 * @return \WP_Query
	 */
	private static function get_direct_posts_for_term( $term ) {
		return new \WP_Query( [
			'post_type'      => Documentation::POST_TYPE,
			'tax_query'      => [
				[
					'taxonomy'         => Project::TAXONOMY,
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
			'taxonomy'   => Project::TAXONOMY,
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
	 * @param \WP_Term $term The project term
	 * @return string|null The modified date or null if no posts
	 */
	private static function get_term_latest_modified_date( $term ) {
		$query = new \WP_Query( [
			'post_type'      => Documentation::POST_TYPE,
			'tax_query'      => [
				[
					'taxonomy'         => Project::TAXONOMY,
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
	 * Render API help block on the main docs archive
	 */
	private static function render_api_help_block() {
		?>
		<aside class="docs-api-help" style="margin: var(--chubes-space-xl) 0; padding: var(--chubes-space-lg) var(--chubes-space-xl); border: 1px solid var(--chubes-border-default); border-radius: 8px; font-size: var(--chubes-font-size-sm); line-height: 1.6; background: var(--chubes-background-card); text-align: left;">
			<p style="margin: 0 0 var(--chubes-space-md); color: var(--chubes-text-secondary);"><strong style="color: var(--chubes-text-primary);">For developers &amp; AI agents</strong> — All documentation is available programmatically in markdown.</p>
			<pre style="margin: 0 0 var(--chubes-space-md); padding: var(--chubes-space-md) var(--chubes-space-base); background: var(--chubes-background-primary); border-radius: 6px; overflow-x: auto; font-size: var(--chubes-font-size-xs); line-height: 1.8; border: 1px solid var(--chubes-border-default); color: var(--chubes-body-text-color);"><code><span style="color: var(--chubes-accent-color-2);">GET</span>  /wp-json/chubes/v1/docs?search=<span style="color: var(--chubes-muted-text-color);">{query}</span>
<span style="color: var(--chubes-accent-color-2);">GET</span>  /wp-json/chubes/v1/docs/<span style="color: var(--chubes-muted-text-color);">{id}</span></code></pre>
			<p style="margin: 0;"><a href="/docs/chubes-docs/api-reference/" style="color: var(--chubes-link-color); text-decoration: none;">View API Reference →</a></p>
		</aside>
		<?php
	}

	/**
	 * Render documentation archive page
	 * 
	 * Shows all documentation grouped by project type.
	 */
	private static function render_documentation_archive() {
		// Get all depth-0 project terms with GitHub URLs (synced projects)
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
			return;
		}

		// Group projects by their project_type term meta
		$projects_by_type = [];
		$untyped_projects = [];

		foreach ( $project_terms as $project_term ) {
			$repo_info = Project::get_repository_info( $project_term );
			$doc_count = $repo_info['content_counts']['documentation'] ?? 0;

			if ( $doc_count > 0 ) {
				$project_data = [
					'term'      => $project_term,
					'repo_info' => $repo_info,
					'doc_count' => $doc_count,
				];

				$project_type = Project::get_project_type( $project_term );
				if ( $project_type ) {
					if ( ! isset( $projects_by_type[ $project_type ] ) ) {
						$projects_by_type[ $project_type ] = [];
					}
					$projects_by_type[ $project_type ][] = $project_data;
				} else {
					$untyped_projects[] = $project_data;
				}
			}
		}

		if ( empty( $projects_by_type ) && empty( $untyped_projects ) ) {
			return;
		}

		foreach ( $projects_by_type as $project_type => $projects ) :
			// Get display name from project_type taxonomy term
			$type_term = get_term_by( 'slug', $project_type, 'project_type' );
			$display_name = $type_term && ! is_wp_error( $type_term ) ? $type_term->name : ucfirst( str_replace( '-', ' ', $project_type ) );
			?>

			<section class="documentation-category-section">
				<div class="category-header">
					<h2><?php echo esc_html( $display_name ); ?></h2>
				</div>

				<div class="cards-grid">
					<?php foreach ( $projects as $project ) : ?>
						<div class="doc-card">
							<div class="card-header">
								<h3><a href="<?php echo esc_url( get_term_link( $project['term'] ) ); ?>"><?php echo esc_html( $project['term']->name ); ?></a></h3>
								<?php if ( $project['term']->description ) : ?>
									<p class="card-description"><?php echo esc_html( wp_trim_words( $project['term']->description, 20 ) ); ?></p>
								<?php endif; ?>
							</div>

							<div class="card-stats">
								<span class="stat-item"><?php echo $project['doc_count']; ?> guide<?php echo $project['doc_count'] !== 1 ? 's' : ''; ?></span>
								<?php if ( $project['repo_info']['installs'] > 0 ) : ?>
									<span class="stat-item"><?php echo number_format( $project['repo_info']['installs'] ); ?> downloads</span>
								<?php endif; ?>

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

							<div class="card-actions">
								<a href="<?php echo esc_url( get_term_link( $project['term'] ) ); ?>" class="btn primary">
									View Documentation →
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach;

		// Render untyped projects at the bottom
		if ( ! empty( $untyped_projects ) ) :
			?>
			<section class="documentation-category-section">
				<div class="category-header">
					<h2>Projects</h2>
				</div>

				<div class="cards-grid">
					<?php foreach ( $untyped_projects as $project ) : ?>
						<div class="doc-card">
							<div class="card-header">
								<h3><a href="<?php echo esc_url( get_term_link( $project['term'] ) ); ?>"><?php echo esc_html( $project['term']->name ); ?></a></h3>
								<?php if ( $project['term']->description ) : ?>
									<p class="card-description"><?php echo esc_html( wp_trim_words( $project['term']->description, 20 ) ); ?></p>
								<?php endif; ?>
							</div>

							<div class="card-stats">
								<span class="stat-item"><?php echo $project['doc_count']; ?> guide<?php echo $project['doc_count'] !== 1 ? 's' : ''; ?></span>
								<?php if ( $project['repo_info']['installs'] > 0 ) : ?>
									<span class="stat-item"><?php echo number_format( $project['repo_info']['installs'] ); ?> downloads</span>
								<?php endif; ?>

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

							<div class="card-actions">
								<a href="<?php echo esc_url( get_term_link( $project['term'] ) ); ?>" class="btn primary">
									View Documentation →
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif;
	}

	/**
	 * Filter archive title for documentation and project contexts
	 */
	public static function filter_archive_title( $title ) {
		if ( is_post_type_archive( 'documentation' ) ) {
			return 'Documentation';
		}

		if ( is_tax( 'project' ) ) {
			$term = get_queried_object();
			return $term->name;
		}

		return $title;
	}

	/**
	 * Filter whether to show archive description
	 * 
	 * Hide description for archives with custom content (project tax, documentation CPT)
	 * to avoid duplication with rendered content.
	 *
	 * @param bool $show Whether to show the description
	 * @return bool
	 */
	public static function filter_show_description( $show ) {
		if ( is_tax( 'project' ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * Get display name for project type slug
	 *
	 * @param string $slug Project type slug
	 * @return string Display name
	 */
	private static function get_project_type_display_name( $slug ) {
		return ucfirst( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * Render "View on GitHub" link for single documentation posts.
	 *
	 * Hooks into chubes_single_after_content.
	 */
	public static function render_github_link( $post_id, $post_type ) {
		if ( $post_type !== 'documentation' ) {
			return;
		}

		$source_file = get_post_meta( $post_id, '_sync_source_file', true );
		if ( empty( $source_file ) ) {
			return;
		}

		$terms = get_the_terms( $post_id, 'project' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}

		// Find the top-level project term
		$project_term = null;
		foreach ( $terms as $term ) {
			if ( $term->parent === 0 ) {
				$project_term = $term;
				break;
			}
		}
		if ( ! $project_term ) {
			// Try parent of first term
			foreach ( $terms as $term ) {
				$parent = get_term( $term->parent, 'project' );
				if ( $parent && ! is_wp_error( $parent ) && $parent->parent === 0 ) {
					$project_term = $parent;
					break;
				}
			}
		}

		if ( ! $project_term ) {
			return;
		}

		$github_url = get_term_meta( $project_term->term_id, 'project_github_url', true );
		if ( empty( $github_url ) ) {
			return;
		}

		$full_url = rtrim( $github_url, '/' ) . '/blob/main/docs/' . $source_file;
		?>
		<div class="docs-github-link" style="margin-top: var(--chubes-space-xl); padding-top: var(--chubes-space-xl); border-top: 1px solid var(--chubes-border-default);">
			<a href="<?php echo esc_url( $full_url ); ?>" target="_blank" rel="noopener" class="button-3" style="display: inline-flex; align-items: center; gap: 8px;">
				<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
				View on GitHub
			</a>
		</div>
		<?php
	}

}
