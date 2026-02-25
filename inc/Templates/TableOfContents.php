<?php
/**
 * Table of Contents Sidebar
 *
 * Generates a sticky TOC from post content headings for single
 * documentation pages. Includes scroll-spy via docsync-toc.js.
 *
 * Ported from extrachill-docs with enhancements for nested heading support.
 *
 * @package DocSync\Templates
 * @since 1.0.0
 */

namespace DocSync\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TableOfContents {

	/**
	 * Initialize TOC hooks.
	 *
	 * Hooks into docsync_single_sidebar filter to provide sidebar content.
	 * Also adds heading IDs to post content if not present.
	 */
	public static function init(): void {
		add_filter( 'docsync_single_sidebar', [ __CLASS__, 'render' ], 10, 2 );
		add_filter( 'the_content', [ __CLASS__, 'ensure_heading_ids' ], 5 );
	}

	/**
	 * Render TOC sidebar HTML for a documentation post.
	 *
	 * @param string $sidebar_content Existing sidebar content.
	 * @param int    $post_id         Current post ID.
	 * @return string TOC HTML or existing content if no headings found.
	 */
	public static function render( string $sidebar_content, int $post_id ): string {
		$content = get_post_field( 'post_content', $post_id );

		if ( empty( $content ) ) {
			return $sidebar_content;
		}

		// Apply content filters to get rendered HTML (blocks → HTML).
		$rendered = apply_filters( 'docsync_toc_content', $content );

		// Extract h2 and h3 headings with IDs.
		preg_match_all(
			'/<(h[23])[^>]*id=["\']([^"\']+)["\'][^>]*>(.*?)<\/\1>/i',
			$rendered,
			$matches,
			PREG_SET_ORDER
		);

		if ( empty( $matches ) ) {
			return $sidebar_content;
		}

		$headers = [];
		foreach ( $matches as $match ) {
			$headers[] = [
				'level' => $match[1],
				'id'    => $match[2],
				'text'  => wp_strip_all_tags( $match[3] ),
			];
		}

		ob_start();
		?>
		<nav class="docsync-toc" aria-label="<?php esc_attr_e( 'Table of Contents', 'docsync' ); ?>">
			<h3 class="docsync-toc-title"><span><?php esc_html_e( 'On This Page', 'docsync' ); ?></span></h3>
			<?php echo self::build_list( $headers ); ?>
		</nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build the TOC list HTML from headers array.
	 *
	 * Supports nested h2 → h3 hierarchy.
	 *
	 * @param array $headers Array of header data with level, id, text.
	 * @return string HTML list.
	 */
	private static function build_list( array $headers ): string {
		$html = '<ul class="docsync-toc-list">';

		foreach ( $headers as $header ) {
			$indent_class = $header['level'] === 'h3' ? ' docsync-toc-nested' : '';
			$html .= sprintf(
				'<li class="docsync-toc-item%s"><a href="#%s" class="docsync-toc-link">%s</a></li>',
				$indent_class,
				esc_attr( $header['id'] ),
				esc_html( $header['text'] )
			);
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Ensure all h2 and h3 elements in documentation content have IDs.
	 *
	 * Adds slug-based IDs to headings that don't already have one,
	 * so the TOC can link to them.
	 *
	 * @param string $content Post content.
	 * @return string Content with heading IDs ensured.
	 */
	public static function ensure_heading_ids( string $content ): string {
		if ( ! is_singular( 'documentation' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<(h[23])([^>]*)>(.*?)<\/\1>/i',
			function( $matches ) {
				$tag   = $matches[1];
				$attrs = $matches[2];
				$text  = $matches[3];

				// Already has an ID — leave it alone.
				if ( preg_match( '/id=["\']/', $attrs ) ) {
					return $matches[0];
				}

				$id = sanitize_title( wp_strip_all_tags( $text ) );
				return sprintf( '<%s%s id="%s">%s</%s>', $tag, $attrs, esc_attr( $id ), $text, $tag );
			},
			$content
		);
	}
}
