<?php
/**
 * Documentation Layout
 *
 * Wraps single documentation content in a two-column layout with a sidebar.
 * The sidebar collects its content from the docsync_single_sidebar filter,
 * which is where ProjectTree and TableOfContents hook in.
 *
 * This component solves the rendering gap: previously, sidebar components
 * hooked into the filter but nothing applied it. Now the layout wrapper
 * applies the filter and renders the sidebar alongside the content.
 *
 * @package DocSync\Templates
 * @since 1.0.0
 */

namespace DocSync\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocumentationLayout {

	/**
	 * Initialize hooks.
	 *
	 * Uses a late priority on the_content to wrap after all content filters
	 * have run (blocks, shortcodes, embeds, etc).
	 */
	public static function init(): void {
		add_filter( 'the_content', [ __CLASS__, 'wrap_content' ], 999 );
	}

	/**
	 * Wrap single documentation content with sidebar layout.
	 *
	 * Only acts on singular documentation pages. Collects sidebar content
	 * from the docsync_single_sidebar filter and wraps everything in a
	 * CSS Grid layout.
	 *
	 * @param string $content The post content.
	 * @return string Wrapped content with sidebar, or original content.
	 */
	public static function wrap_content( string $content ): string {
		if ( ! is_singular( 'documentation' ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		/**
		 * Filter the sidebar content for single documentation pages.
		 *
		 * Components hook into this to add sidebar widgets:
		 * - ProjectTree at priority 5 (project navigation)
		 * - TableOfContents at priority 10 (page headings)
		 *
		 * @param string $sidebar_content Accumulated sidebar HTML.
		 * @param int    $post_id         Current documentation post ID.
		 */
		$sidebar = apply_filters( 'docsync_single_sidebar', '', $post_id );

		if ( empty( trim( $sidebar ) ) ) {
			return $content;
		}

		return '<div class="docsync-doc-layout">'
			. '<div class="docsync-doc-content">' . $content . '</div>'
			. '<aside class="docsync-doc-sidebar">' . $sidebar . '</aside>'
			. '</div>';
	}
}
