<?php
/**
 * Documentation Search Bar
 *
 * Renders an accessible search bar on the documentation archive page.
 * Uses the REST API for live search results.
 */

namespace ChubesDocs\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SearchBar {

	public static function init(): void {
		add_action( 'chubes_archive_header_after', [ self::class, 'render' ], 5 );
	}

	public static function render(): void {
		if ( ! is_post_type_archive( 'documentation' ) ) {
			return;
		}
		?>
		<div class="docs-search-wrapper">
			<form class="search-form-inline" role="search" aria-label="<?php esc_attr_e( 'Search documentation', 'chubes-docs' ); ?>">
				<label for="docs-search-input" class="screen-reader-text"><?php esc_html_e( 'Search documentation', 'chubes-docs' ); ?></label>
				<input
					type="search"
					id="docs-search-input"
					class="search-input-inline"
					placeholder="<?php esc_attr_e( 'Search documentation...', 'chubes-docs' ); ?>"
					autocomplete="off"
					aria-controls="docs-search-results"
					aria-expanded="false"
				>
				<button type="submit" class="search-submit-inline"><?php esc_html_e( 'Search', 'chubes-docs' ); ?></button>
			</form>
			<div id="docs-search-results" class="docs-search-results" role="listbox" aria-live="polite" hidden></div>
		</div>
		<?php
	}
}
