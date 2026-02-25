<?php
/**
 * Project Card Component
 *
 * Renders a project card for project taxonomy archives.
 * Shows project info, content type buttons, and external links.
 */

namespace DocSync\Templates;

use DocSync\Core\Project;

class ProjectCard {

	/**
	 * Render a project card
	 *
	 * @param \WP_Term $term      The project taxonomy term
	 * @param array    $repo_info Repository info from Project::get_repository_info()
	 */
	public static function render( $term, $repo_info ) {
		$parent_term = get_term( $term->parent, 'project' );
		$category_name = $parent_term && ! is_wp_error( $parent_term ) ? $parent_term->name : 'Project';
		$singular_type = rtrim( $category_name, 's' );

		$content_buttons = self::get_content_buttons( $term );
		$total_content = array_sum( array_column( $content_buttons, 'count' ) );

		$has_download = ! empty( $repo_info['wp_url'] );
		$download_text = $has_download ? 'Download ' . $singular_type : 'View ' . $singular_type;

		// Get actual project type from posts
		$project_type = '';
		$posts = get_posts( [
			'post_type'      => 'documentation',
			'tax_query'      => [
				[
					'taxonomy' => 'project',
					'terms'    => $term->term_id,
				],
			],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );
		if ( ! empty( $posts ) ) {
			$project_type = Project::get_project_type( $posts[0] );
		}
		?>

		<div class="doc-card" data-project-type="<?php echo esc_attr( $project_type ); ?>">
			<div class="card-header">
				<h3 class="project-title">
					<a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				</h3>

				<?php if ( $term->description ) : ?>
					<p class="project-description"><?php echo esc_html( wp_trim_words( $term->description, 20 ) ); ?></p>
				<?php endif; ?>

				<div class="project-stats">
					<?php if ( $repo_info['installs'] > 0 ) : ?>
						<span class="stat-item">
							<span class="stat-number"><?php echo number_format( $repo_info['installs'] ); ?></span>
							<span class="stat-label">downloads</span>
						</span>
					<?php endif; ?>

					<?php if ( $total_content > 0 ) : ?>
						<span class="stat-item">
							<span class="stat-number"><?php echo $total_content; ?></span>
							<span class="stat-label">items</span>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $content_buttons ) ) : ?>
				<div class="content-buttons">
					<?php foreach ( $content_buttons as $button ) : ?>
						<a href="<?php echo esc_url( $button['url'] ); ?>" class="content-btn" data-type="<?php echo esc_attr( $button['type'] ); ?>">
							<span class="btn-label">View <?php echo esc_html( $button['label'] ); ?></span>
							<span class="btn-count"><?php echo $button['count']; ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="external-buttons">
				<?php if ( $repo_info['wp_url'] ) : ?>
					<a href="<?php echo esc_url( $repo_info['wp_url'] ); ?>" class="external-btn primary" target="_blank">
						<svg class="btn-icon"><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/fonts/social-icons.svg#icon-wordpress"></use></svg>
						<?php echo esc_html( $download_text ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $repo_info['github_url'] ) : ?>
					<a href="<?php echo esc_url( $repo_info['github_url'] ); ?>" class="external-btn secondary" target="_blank">
						<svg class="btn-icon"><use href="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/fonts/social-icons.svg#icon-github"></use></svg>
						GitHub
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get content type buttons for a project term
	 *
	 * @param \WP_Term $term The project taxonomy term
	 * @return array Array of button data
	 */
	private static function get_content_buttons( $term ) {
		$all_post_types = get_post_types( [ 'public' => true ], 'objects' );
		$content_buttons = [];

		foreach ( $all_post_types as $post_type_obj ) {
			$post_type = $post_type_obj->name;

			if ( in_array( $post_type, [ 'attachment', 'page' ], true ) ) {
				continue;
			}

			if ( ! is_object_in_taxonomy( $post_type, 'project' ) ) {
				continue;
			}

			$query = new \WP_Query( [
				'post_type'      => $post_type,
				'tax_query'      => [
					[
						'taxonomy' => 'project',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					],
				],
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			] );

			$count = $query->found_posts;
			wp_reset_postdata();

			if ( $count > 0 ) {
				$content_buttons[] = [
					'type'     => $post_type,
					'label'    => $post_type_obj->labels->name,
					'singular' => $post_type_obj->labels->singular_name,
					'count'    => $count,
					'url'      => self::generate_content_type_url( $post_type, $term ),
				];
			}
		}

		return $content_buttons;
	}

	/**
	 * Generate URL for viewing content of specific type for a project
	 *
	 * @param string   $post_type The post type
	 * @param \WP_Term $term      The project term
	 * @return string The URL
	 */
	public static function generate_content_type_url( $post_type, $term ) {
		if ( $post_type === 'documentation' ) {
			return get_term_link( $term );
		}

		$archive_url = get_post_type_archive_link( $post_type );
		if ( $archive_url ) {
			return add_query_arg( 'project', $term->slug, $archive_url );
		}

		return get_term_link( $term );
	}
}
