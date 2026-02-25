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
						<svg class="btn-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zM3.443 12c0-1.178.25-2.296.69-3.313l3.8 10.411A8.564 8.564 0 013.443 12zm8.557 8.56c-.8 0-1.58-.104-2.316-.3l2.46-7.14 2.52 6.907c.016.042.038.08.058.117a8.546 8.546 0 01-2.722.417zm1.126-12.576c.494-.026.938-.075.938-.075.442-.05.39-.702-.053-.677 0 0-1.33.104-2.186.104-.804 0-2.16-.104-2.16-.104-.442-.025-.494.652-.052.677 0 0 .42.05.864.075l1.283 3.517-1.803 5.406-3-8.923c.494-.026.94-.075.94-.075.44-.05.388-.702-.054-.677 0 0-1.33.104-2.186.104-.154 0-.335-.004-.525-.01A8.542 8.542 0 0112 3.44c2.34 0 4.47.94 6.02 2.46-.038-.003-.076-.008-.116-.008-.804 0-1.374.7-1.374 1.452 0 .675.39 1.246.804 1.922.312.546.676 1.246.676 2.257 0 .7-.27 1.512-.624 2.644l-.818 2.732-2.96-8.803zm3.96 12.058l2.508-7.244a7.624 7.624 0 00.596-2.882c0-.296-.02-.572-.054-.84A8.555 8.555 0 0120.557 12a8.558 8.558 0 01-3.47 6.042z"/></svg>
						<?php echo esc_html( $download_text ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $repo_info['github_url'] ) : ?>
					<a href="<?php echo esc_url( $repo_info['github_url'] ); ?>" class="external-btn secondary" target="_blank">
						<svg class="btn-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.009-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.268 2.75 1.026A9.578 9.578 0 0112 6.836a9.59 9.59 0 012.504.337c1.909-1.294 2.747-1.026 2.747-1.026.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/></svg>
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
