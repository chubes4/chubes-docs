<?php
/**
 * Converts markdown content to HTML with internal link resolution.
 * Uses Parsedown for markdown parsing and resolves .md links to WordPress URLs.
 */

namespace ChubesDocs\Sync;

use Parsedown;

class MarkdownProcessor {

	private string $project_slug;
	private string $current_file_path;
	private int $project_term_id;

	public function __construct( string $project_slug, string $current_file_path, int $project_term_id ) {
		$this->project_slug      = $project_slug;
		$this->current_file_path = $current_file_path;
		$this->project_term_id   = $project_term_id;
	}

	/**
	 * Process markdown content: resolve links and convert to HTML.
	 */
	public function process( string $markdown ): string {
		$markdown = $this->resolveInternalLinks( $markdown );

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( false );

		return $parsedown->text( $markdown );
	}

	/**
	 * Resolve internal .md links to WordPress documentation URLs.
	 */
	private function resolveInternalLinks( string $markdown ): string {
		return preg_replace_callback(
			'/\[([^\]]+)\]\(([^)]+\.md)(#[^)]*)?\)/',
			[ $this, 'resolveLink' ],
			$markdown
		);
	}

	/**
	 * Resolve a single markdown link to WordPress URL.
	 */
	private function resolveLink( array $matches ): string {
		$text    = $matches[1];
		$md_path = $matches[2];
		$anchor  = $matches[3] ?? '';

		$target_source_file = $this->resolvePath( $md_path );

		$target_post_id = SyncManager::find_post_by_source( $target_source_file, $this->project_term_id );

		if ( $target_post_id ) {
			$permalink = get_permalink( $target_post_id );
			return "[{$text}]({$permalink}{$anchor})";
		}

		$slug = $this->pathToSlug( $target_source_file );
		return "[{$text}](/docs/{$this->project_slug}/{$slug}/{$anchor})";
	}

	/**
	 * Resolve relative path from current file location.
	 */
	private function resolvePath( string $md_path ): string {
		if ( strpos( $md_path, './' ) === 0 ) {
			$md_path = substr( $md_path, 2 );
		}

		if ( strpos( $md_path, '/' ) === 0 ) {
			return ltrim( $md_path, '/' );
		}

		$current_dir = dirname( $this->current_file_path );
		if ( $current_dir === '.' ) {
			return $md_path;
		}

		$full_path = $current_dir . '/' . $md_path;

		return $this->normalizePath( $full_path );
	}

	/**
	 * Normalize path by resolving ../ and ./ segments.
	 */
	private function normalizePath( string $path ): string {
		$parts      = explode( '/', $path );
		$normalized = [];

		foreach ( $parts as $part ) {
			if ( $part === '..' ) {
				array_pop( $normalized );
			} elseif ( $part !== '.' && $part !== '' ) {
				$normalized[] = $part;
			}
		}

		return implode( '/', $normalized );
	}

	/**
	 * Convert file path to URL slug.
	 */
	private function pathToSlug( string $path ): string {
		$slug = preg_replace( '/\.md$/', '', $path );
		$slug = strtolower( $slug );
		$slug = preg_replace( '/[^a-z0-9\/]+/', '-', $slug );
		return trim( $slug, '-/' );
	}

	/**
	 * Detect if content is markdown (vs HTML or Gutenberg blocks).
	 */
	public static function isMarkdown( string $content ): bool {
		$content = trim( $content );

		if ( empty( $content ) ) {
			return false;
		}

		if ( strpos( $content, '<!-- wp:' ) !== false ) {
			return false;
		}

		if ( preg_match( '/^<(html|head|body|div|section|article|header|footer|nav|aside|main)/i', $content ) ) {
			return false;
		}

		$markdown_patterns = [
			'/^#{1,6}\s+.+$/m',
			'/^\s*[-*+]\s+.+$/m',
			'/^\s*\d+\.\s+.+$/m',
			'/^```/m',
			'/\[.+?\]\(.+?\)/',
			'/^\|.+\|$/m',
			'/^>\s+.+$/m',
			'/\*\*.+?\*\*/',
			'/`.+?`/',
		];

		foreach ( $markdown_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		if ( ! preg_match( '/^<[a-z]/i', $content ) ) {
			return true;
		}

		return false;
	}
}
