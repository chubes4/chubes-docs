<?php
/**
 * Archive Template Hooks
 * 
 * Hooks into theme's archive.php to render documentation and codebase content.
 * Provides all archive rendering for the documentation system.
 */

namespace ChubesDocs\Templates;

use ChubesDocs\Core\Codebase;

class Archive {

	public static function init() {
		add_action( 'chubes_archive_header_after', [ self::class, 'render_header_extras' ] );
		add_action( 'chubes_archive_content', [ self::class, 'render_content' ] );
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
	 * Render archive content based on context
	 * 
	 * Handles codebase taxonomy pages and documentation archive.
	 */
	public static function render_content() {
		if ( is_tax( 'codebase' ) ) {
			self::render_codebase_content();
			return;
		}

		if ( is_post_type_archive( 'documentation' ) ) {
			self::render_documentation_archive();
			return;
		}
	}

	/**
	 * Render codebase taxonomy content
	 * 
	 * Parent terms: Grid of project cards
	 * Child terms: Documentation list for the project
	 */
	private static function render_codebase_content() {
		$term = get_queried_object();
		$is_category = ( $term->parent === 0 );

		if ( $is_category ) {
			self::render_category_content( $term );
		} else {
			self::render_project_content( $term );
		}
	}

	/**
	 * Render category page content (parent term)
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
	 * Render project page content (child term)
	 * 
	 * Shows documentation list for the project.
	 */
	private static function render_project_content( $term ) {
		$docs_query = new \WP_Query( [
			'post_type'      => 'documentation',
			'tax_query'      => [
				[
					'taxonomy' => 'codebase',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				],
			],
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		] );

		?>
		<h2>Documentation</h2>

		<?php if ( $docs_query->have_posts() ) : ?>
			<div class="documentation-cards">
				<?php
				while ( $docs_query->have_posts() ) :
					$docs_query->the_post();
					?>
					<a href="<?php the_permalink(); ?>" class="documentation-card">
						<div class="card-header">
							<h3><?php the_title(); ?></h3>
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
		else :
			$parent_term = get_term( $term->parent, 'codebase' );
			$singular_type = $parent_term && ! is_wp_error( $parent_term ) ? rtrim( $parent_term->name, 's' ) : 'project';
			$repo_info = Codebase::get_repository_info( $term );
			?>
			<div class="no-docs">
				<p>Documentation for this <?php echo esc_html( strtolower( $singular_type ) ); ?> is coming soon.</p>
				<?php if ( ! empty( $repo_info['github_url'] ) ) : ?>
					<p>In the meantime, check out the <a href="<?php echo esc_url( $repo_info['github_url'] ); ?>" target="_blank">GitHub repository</a> for technical details.</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php
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
