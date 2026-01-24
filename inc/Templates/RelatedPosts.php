<?php
/**
 * Related Posts Template Functionality
 * 
 * Hooks into theme's single post template to render related documentation
 * using hierarchical codebase taxonomy relationships.
 */

namespace ChubesDocs\Templates;

use ChubesDocs\Core\Project;

class RelatedPosts {

	public static function init() {
		add_filter( 'chubes_single_meta_label', [ self::class, 'filter_meta_label' ], 10, 2 );
		add_filter( 'chubes_single_meta_date', [ self::class, 'filter_meta_date' ], 10, 3 );
		add_action( 'chubes_single_after_content', [ self::class, 'render_related_posts' ], 10, 2 );
	}

	public static function filter_meta_label( $label, $post_type ) {
		if ( $post_type === 'documentation' ) {
			return 'Last updated:';
		}
		return $label;
	}

	public static function filter_meta_date( $date, $post_id, $post_type ) {
		if ( $post_type === 'documentation' ) {
			return get_the_modified_date( '', $post_id );
		}
		return $date;
	}



	public static function render_related_posts( $post_id, $post_type ) {
		if ( $post_type !== 'documentation' ) {
			return;
		}

		$terms = get_the_terms( $post_id, 'codebase' );
		$project_term = null;
		$current_level_term = null;
		$top_level_term = null;

		if ( $terms && ! is_wp_error( $terms ) ) {
			$project_term = Project::get_project_term( $terms );
			$top_level_term = Project::get_top_level_term( $terms );
			$current_level_term = Project::get_primary_term( $terms );
		}

		$related_posts = self::get_related_documentation( $post_id, 3 );

		$section_title = 'Related Documentation';
		if ( $current_level_term ) {
			$section_title = 'More in ' . $current_level_term->name;
		} elseif ( $project_term ) {
			$section_title = 'More in ' . $project_term->name;
		}
		?>
		<div class="related-posts">
			<h3><?php echo esc_html( $section_title ); ?></h3>

			<?php if ( ! empty( $related_posts ) ) : ?>
				<div class="related-posts-list">
					<?php foreach ( $related_posts as $related_post ) : ?>
						<article class="related-post-item">
							<h4><a href="<?php echo esc_url( get_permalink( $related_post->ID ) ); ?>"><?php echo esc_html( $related_post->post_title ); ?></a></h4>
							<?php if ( $related_post->post_excerpt ) : ?>
								<p class="related-excerpt">
									<?php echo wp_trim_words( $related_post->post_excerpt, 15 ); ?>
								</p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="no-related-posts">
					<p>No related documentation found.</p>
				</div>
			<?php endif; ?>

			<div class="related-actions">
				<?php if ( $project_term && $top_level_term ) : 
					$project_url = home_url( '/docs/' . $top_level_term->slug . '/' . $project_term->slug . '/' );
					?>
					<a href="<?php echo esc_url( $project_url ); ?>" class="button-2">
						All <?php echo esc_html( $project_term->name ); ?> Docs
					</a>
				<?php endif; ?>

				<?php if ( $current_level_term && $project_term && $current_level_term->term_id !== $project_term->term_id ) :
					$current_level_url = '';
					if ( $top_level_term ) {
						if ( $current_level_term->parent === $project_term->term_id ) {
							$current_level_url = home_url( '/docs/' . $top_level_term->slug . '/' . $project_term->slug . '/' . $current_level_term->slug . '/' );
						} else {
							$current_level_url = get_term_link( $current_level_term );
						}
					} else {
						$current_level_url = get_term_link( $current_level_term );
					}
					?>
					<a href="<?php echo esc_url( $current_level_url ); ?>" class="button-1">
						View all <?php echo esc_html( $current_level_term->name ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function get_related_documentation( $post_id, $limit = 3 ) {
		$current_post = get_post( $post_id );

		if ( ! $current_post || $current_post->post_type !== 'documentation' ) {
			return [];
		}

		$doc_taxonomies = get_object_taxonomies( 'documentation', 'names' );
		$taxonomy_terms = null;
		$taxonomy_name = null;

		foreach ( $doc_taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$taxonomy_terms = $terms;
				$taxonomy_name = $taxonomy;
				break;
			}
		}

		if ( ! $taxonomy_terms || ! $taxonomy_name ) {
			return self::get_fallback_related( $post_id, $limit );
		}

		$hierarchy_levels = self::build_hierarchy_levels( $taxonomy_terms, $taxonomy_name );
		$related_posts = [];

		foreach ( $hierarchy_levels as $level_data ) {
			if ( count( $related_posts ) >= $limit ) {
				break;
			}

			$needed = $limit - count( $related_posts );
			$level_posts = self::get_posts_by_level(
				$level_data['terms'],
				$level_data['taxonomy'],
				$post_id,
				$current_post->post_parent,
				$needed,
				$level_data['same_parent_only']
			);

			$related_posts = array_merge( $related_posts, $level_posts );
		}

		return array_slice( $related_posts, 0, $limit );
	}

	private static function build_hierarchy_levels( $terms, $taxonomy_name ) {
		$levels = [];
		$deepest_term = null;
		$max_depth = -1;

		foreach ( $terms as $term ) {
			$depth = self::get_term_depth( $term, $taxonomy_name );
			if ( $depth > $max_depth ) {
				$max_depth = $depth;
				$deepest_term = $term;
			}
		}

		if ( ! $deepest_term ) {
			$deepest_term = $terms[0];
		}

		$levels[] = [
			'terms' => [ $deepest_term ],
			'taxonomy' => $taxonomy_name,
			'same_parent_only' => true,
		];

		$levels[] = [
			'terms' => [ $deepest_term ],
			'taxonomy' => $taxonomy_name,
			'same_parent_only' => false,
		];

		$current_term = $deepest_term;
		while ( $current_term->parent != 0 ) {
			$parent_term = get_term( $current_term->parent, $taxonomy_name );
			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				$levels[] = [
					'terms' => [ $parent_term ],
					'taxonomy' => $taxonomy_name,
					'same_parent_only' => false,
				];
				$current_term = $parent_term;
			} else {
				break;
			}
		}

		return $levels;
	}

	private static function get_posts_by_level( $terms, $taxonomy, $exclude_post_id, $current_parent_id, $limit, $same_parent_only = false ) {
		$term_ids = array_map( function( $term ) {
			return $term->term_id;
		}, $terms );

		$args = [
			'post_type' => 'documentation',
			'posts_per_page' => $limit * 2,
			'post_status' => 'publish',
			'post__not_in' => [ $exclude_post_id ],
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'tax_query' => [
				[
					'taxonomy' => $taxonomy,
					'field' => 'term_id',
					'terms' => $term_ids,
					'operator' => 'IN',
				],
			],
		];

		if ( $same_parent_only && $current_parent_id ) {
			$args['post_parent'] = $current_parent_id;
		}

		$posts = get_posts( $args );

		if ( count( $posts ) < $limit && $same_parent_only && $current_parent_id ) {
			$args['post_parent'] = $current_parent_id;
			$args['posts_per_page'] = $limit;
			$sibling_posts = get_posts( $args );
			$posts = array_merge( $posts, $sibling_posts );
		}

		return array_slice( $posts, 0, $limit );
	}

	private static function get_term_depth( $term, $taxonomy ) {
		$depth = 0;
		$current_term = $term;

		while ( $current_term->parent != 0 ) {
			$depth++;
			$parent_term = get_term( $current_term->parent, $taxonomy );
			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				$current_term = $parent_term;
			} else {
				break;
			}

			if ( $depth > 10 ) {
				break;
			}
		}

		return $depth;
	}

	private static function get_fallback_related( $post_id, $limit = 3 ) {
		return get_posts( [
			'post_type' => 'documentation',
			'posts_per_page' => $limit,
			'post_status' => 'publish',
			'post__not_in' => [ $post_id ],
			'orderby' => 'date',
			'order' => 'DESC',
		] );
	}
}
