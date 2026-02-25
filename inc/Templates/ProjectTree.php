<?php
/**
 * Project Tree Sidebar Navigation
 *
 * Renders a hierarchical tree of all documentation in the current project
 * on single documentation pages. Shows sections (taxonomy terms) and docs,
 * highlights the current page, and supports collapse/expand.
 *
 * Hooks into docsync_single_sidebar at priority 5 (before TOC at 10).
 * Uses ProjectController::build_doc_tree() for the data structure.
 *
 * @package DocSync\Templates
 * @since 1.0.0
 */

namespace DocSync\Templates;

use DocSync\Api\Controllers\ProjectController;
use DocSync\Core\Project;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProjectTree {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_filter( 'docsync_single_sidebar', [ __CLASS__, 'render' ], 5, 2 );
	}

	/**
	 * Render the project tree sidebar for a documentation post.
	 *
	 * @param string $sidebar_content Existing sidebar content.
	 * @param int    $post_id         Current post ID.
	 * @return string Updated sidebar content with tree prepended.
	 */
	public static function render( string $sidebar_content, int $post_id ): string {
		$terms = get_the_terms( $post_id, Project::TAXONOMY );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return $sidebar_content;
		}

		// Get the top-level project term.
		$project_term = Project::get_top_level_term( $terms );
		if ( ! $project_term ) {
			return $sidebar_content;
		}

		$tree = ProjectController::build_doc_tree( $project_term->term_id );
		if ( empty( $tree ) ) {
			return $sidebar_content;
		}

		$project_url = get_term_link( $project_term );

		ob_start();
		?>
		<nav class="docsync-project-tree" aria-label="<?php echo esc_attr( sprintf( __( '%s documentation', 'docsync' ), $project_term->name ) ); ?>">
			<h3 class="docsync-tree-title">
				<a href="<?php echo esc_url( $project_url ); ?>"><?php echo esc_html( $project_term->name ); ?></a>
			</h3>
			<?php echo self::render_sections( $tree, $post_id ); ?>
		</nav>
		<?php
		$tree_html = ob_get_clean();

		return $tree_html . $sidebar_content;
	}

	/**
	 * Render tree sections recursively.
	 *
	 * @param array $sections Tree sections from ProjectController::build_doc_tree().
	 * @param int   $post_id  Current post ID for highlighting.
	 * @return string HTML.
	 */
	private static function render_sections( array $sections, int $post_id ): string {
		$html = '<ul class="docsync-tree-list">';

		foreach ( $sections as $section ) {
			$term = $section['term'] ?? null;
			$docs = $section['docs'] ?? [];
			$children = $section['children'] ?? [];

			// Section with a term heading (named section).
			if ( $term ) {
				$has_children = ! empty( $docs ) || ! empty( $children );
				$is_expanded  = $has_children && self::section_contains_post( $section, $post_id );
				$state_class  = $is_expanded ? 'docsync-tree-expanded' : 'docsync-tree-collapsed';

				$html .= '<li class="docsync-tree-section ' . $state_class . '">';
				$html .= '<button class="docsync-tree-toggle" aria-expanded="' . ( $is_expanded ? 'true' : 'false' ) . '">';
				$html .= '<span class="docsync-tree-icon"></span>';
				$html .= esc_html( $term['name'] );
				$html .= '</button>';

				if ( $has_children ) {
					$html .= '<ul class="docsync-tree-children">';

					foreach ( $docs as $doc ) {
						$html .= self::render_doc_item( $doc, $post_id );
					}

					// Recurse into child sections.
					if ( ! empty( $children ) ) {
						foreach ( $children as $child_section ) {
							$child_term = $child_section['term'] ?? null;
							$child_docs = $child_section['docs'] ?? [];
							$child_children = $child_section['children'] ?? [];

							if ( $child_term ) {
								$child_expanded = self::section_contains_post( $child_section, $post_id );
								$child_state = $child_expanded ? 'docsync-tree-expanded' : 'docsync-tree-collapsed';

								$html .= '<li class="docsync-tree-section ' . $child_state . '">';
								$html .= '<button class="docsync-tree-toggle" aria-expanded="' . ( $child_expanded ? 'true' : 'false' ) . '">';
								$html .= '<span class="docsync-tree-icon"></span>';
								$html .= esc_html( $child_term['name'] );
								$html .= '</button>';

								$html .= '<ul class="docsync-tree-children">';
								foreach ( $child_docs as $doc ) {
									$html .= self::render_doc_item( $doc, $post_id );
								}
								$html .= '</ul></li>';
							} else {
								foreach ( $child_docs as $doc ) {
									$html .= self::render_doc_item( $doc, $post_id );
								}
							}
						}
					}

					$html .= '</ul>';
				}

				$html .= '</li>';
			} else {
				// Root docs (no section heading).
				foreach ( $docs as $doc ) {
					$html .= self::render_doc_item( $doc, $post_id );
				}
			}
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Render a single doc item.
	 *
	 * @param array $doc     Doc data with id, title, slug, url.
	 * @param int   $post_id Current post ID.
	 * @return string HTML list item.
	 */
	private static function render_doc_item( array $doc, int $post_id ): string {
		$is_current = ( (int) $doc['id'] === $post_id );
		$classes    = 'docsync-tree-doc';

		if ( $is_current ) {
			$classes .= ' docsync-tree-current';
		}

		return sprintf(
			'<li class="%s"><a href="%s"%s>%s</a></li>',
			esc_attr( $classes ),
			esc_url( $doc['url'] ),
			$is_current ? ' aria-current="page"' : '',
			esc_html( $doc['title'] )
		);
	}

	/**
	 * Check if a section contains the given post ID.
	 *
	 * Used to auto-expand sections containing the current page.
	 *
	 * @param array $section Section data.
	 * @param int   $post_id Post ID to find.
	 * @return bool True if found.
	 */
	private static function section_contains_post( array $section, int $post_id ): bool {
		foreach ( $section['docs'] ?? [] as $doc ) {
			if ( (int) $doc['id'] === $post_id ) {
				return true;
			}
		}

		foreach ( $section['children'] ?? [] as $child ) {
			if ( self::section_contains_post( $child, $post_id ) ) {
				return true;
			}
		}

		return false;
	}
}
