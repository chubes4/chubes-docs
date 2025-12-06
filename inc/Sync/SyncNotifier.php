<?php
/**
 * Sync Email Notifier
 *
 * Sends email notifications to the site admin when documentation
 * is synced and changes occur.
 */

namespace ChubesDocs\Sync;

use ChubesDocs\Core\Codebase;

class SyncNotifier {

	/**
	 * Send sync report email if changes occurred.
	 *
	 * @param int   $term_id Project term ID.
	 * @param array $results Sync results from RepoSync.
	 */
	public function send( int $term_id, array $results ): void {
		if ( ! $this->has_changes( $results ) ) {
			return;
		}

		$term = get_term( $term_id, Codebase::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$to = get_option( 'admin_email' );
		$subject = $this->build_subject( $term->name, $results['success'] ? 'Success' : 'Failed' );
		$body = $this->build_body( $term, $results );
		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Check if sync results contain actual changes.
	 *
	 * @param array $results Sync results.
	 * @return bool True if changes occurred.
	 */
	private function has_changes( array $results ): bool {
		return ! empty( $results['added'] ) ||
			   ! empty( $results['updated'] ) ||
			   ! empty( $results['removed'] ) ||
			   ! empty( $results['terms_created'] );
	}

	/**
	 * Build email subject line.
	 *
	 * @param string $project_name Project name.
	 * @param string $status       Status: 'Success' or 'Failed'.
	 * @return string Email subject.
	 */
	private function build_subject( string $project_name, string $status ): string {
		$site_name = get_bloginfo( 'name' );
		return sprintf( '[%s] Docs Sync: %s - %s', $site_name, $project_name, $status );
	}

	/**
	 * Build email body.
	 *
	 * @param \WP_Term $term    Project term.
	 * @param array    $results Sync results.
	 * @return string Email body.
	 */
	private function build_body( \WP_Term $term, array $results ): string {
		$lines = [];

		$lines[] = 'Documentation Sync Report';
		$lines[] = str_repeat( '-', 40 );
		$lines[] = '';

		$project_line = 'Project: ' . $term->name;
		$installs = $this->get_install_count( $term->term_id );
		if ( $installs > 0 ) {
			$project_line .= ' (' . $this->format_installs( $installs ) . ' active installs)';
		}
		$lines[] = $project_line;

		$github_url = get_term_meta( $term->term_id, 'codebase_github_url', true );
		if ( $github_url ) {
			$lines[] = 'Repository: ' . $github_url;
		}

		$lines[] = 'Status: ' . ( $results['success'] ? 'Success' : 'Failed' );
		$lines[] = 'Time: ' . wp_date( 'M j, Y \a\t g:ia' );

		if ( $results['old_sha'] && $results['new_sha'] ) {
			$old_short = substr( $results['old_sha'], 0, 7 );
			$new_short = substr( $results['new_sha'], 0, 7 );
			$lines[] = "Commit: {$old_short} -> {$new_short}";
		} elseif ( $results['new_sha'] ) {
			$new_short = substr( $results['new_sha'], 0, 7 );
			$lines[] = "Commit: {$new_short} (initial sync)";
		}

		$lines[] = '';

		if ( ! empty( $results['added'] ) ) {
			$lines[] = 'Files Added (' . count( $results['added'] ) . '):';
			foreach ( $results['added'] as $file ) {
				$lines[] = '  - ' . $file;
			}
			$lines[] = '';
		}

		if ( ! empty( $results['updated'] ) ) {
			$lines[] = 'Files Updated (' . count( $results['updated'] ) . '):';
			foreach ( $results['updated'] as $file ) {
				$lines[] = '  - ' . $file;
			}
			$lines[] = '';
		}

		if ( ! empty( $results['removed'] ) ) {
			$lines[] = 'Files Removed (' . count( $results['removed'] ) . '):';
			foreach ( $results['removed'] as $file ) {
				$lines[] = '  - ' . $file;
			}
			$lines[] = '';
		}

		if ( ! empty( $results['terms_created'] ) ) {
			$lines[] = 'Terms Created (' . count( $results['terms_created'] ) . '):';
			foreach ( $results['terms_created'] as $term_name ) {
				$lines[] = '  - ' . $term_name;
			}
			$lines[] = '';
		}

		if ( ! empty( $results['error'] ) ) {
			$lines[] = 'Error: ' . $results['error'];
			$lines[] = '';
		}

		$lines[] = str_repeat( '-', 40 );
		$docs_url = $this->get_docs_url( $term );
		if ( $docs_url ) {
			$lines[] = 'View documentation: ' . $docs_url;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get install count for a term.
	 *
	 * @param int $term_id Term ID.
	 * @return int Install count.
	 */
	private function get_install_count( int $term_id ): int {
		$wp_url = get_term_meta( $term_id, 'codebase_wp_url', true );
		if ( empty( $wp_url ) ) {
			return 0;
		}
		return (int) get_term_meta( $term_id, 'codebase_installs', true );
	}

	/**
	 * Format install count for display.
	 *
	 * @param int $installs Install count.
	 * @return string Formatted string (e.g., "1,000+").
	 */
	private function format_installs( int $installs ): string {
		if ( $installs >= 1000000 ) {
			return number_format( floor( $installs / 1000000 ) ) . 'M+';
		}
		if ( $installs >= 1000 ) {
			return number_format( floor( $installs / 1000 ) ) . 'K+';
		}
		return number_format( $installs ) . '+';
	}

	/**
	 * Get the documentation URL for a project.
	 *
	 * @param \WP_Term $term Project term.
	 * @return string|null Documentation URL or null.
	 */
	private function get_docs_url( \WP_Term $term ): ?string {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return null;
		}
		return $link;
	}
}
