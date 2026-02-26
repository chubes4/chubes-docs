<?php
/**
 * Plugin Name: DocSync
 * Plugin URI: https://github.com/chubes4/docsync
 * Description: GitHub-to-WordPress documentation sync system with REST API, WP-CLI, and hierarchical project organization.
 * Version: 1.0.1
 * Author: Chris Huber
 * Author URI: https://chubes.net
 * License: GPL v2 or later
 * Text Domain: docsync
 * Requires at least: 6.9
 * Requires PHP: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DOCSYNC_VERSION', '0.10.0' );
define( 'DOCSYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOCSYNC_URL', plugin_dir_url( __FILE__ ) );

require_once DOCSYNC_PATH . 'vendor/autoload.php';

use DocSync\Api\Routes;
use DocSync\Abilities\Abilities;
use DocSync\Core\Assets;
use DocSync\Core\Documentation;
use DocSync\Core\Project;
use DocSync\Core\RewriteRules;
use DocSync\Core\Breadcrumbs;
use DocSync\Fields\RepositoryFields;
use DocSync\Fields\InstallTracker;
use DocSync\Templates\DocumentationLayout;
use DocSync\Templates\ProjectTree;
use DocSync\Templates\RelatedPosts;
use DocSync\Templates\Archive;
use DocSync\Templates\ProjectCard;
use DocSync\Templates\Homepage;
use DocSync\Templates\SearchBar;
use DocSync\Templates\TableOfContents;
use DocSync\Sync\CronSync;
use DocSync\Admin\SettingsPage;
use DocSync\Admin\ProjectColumns;
use DocSync\Admin\DocumentationColumns;

Documentation::init();
Project::init();
RewriteRules::init();
Assets::init();

add_action( 'docsync_project_registered', function() {
	RepositoryFields::init();
	InstallTracker::init();
	ProjectColumns::init();
} );

CronSync::init();
SettingsPage::init();
DocumentationColumns::init();

/**
 * Template layer — only loads when the active theme declares support.
 *
 * Themes opt in with: add_theme_support( 'docsync-templates' );
 *
 * Without theme support, DocSync registers the CPT, taxonomy, sync engine,
 * REST API, WP-CLI, Abilities, and admin — but produces zero frontend output.
 * The theme handles all rendering via standard WordPress template hierarchy.
 */
add_action( 'after_setup_theme', function() {
	if ( ! current_theme_supports( 'docsync-templates' ) ) {
		return;
	}

	add_action( 'init', function() {
		DocumentationLayout::init();
		ProjectTree::init();
		RelatedPosts::init();
		Breadcrumbs::init();
		Archive::init();
		Homepage::init();
		SearchBar::init();
		TableOfContents::init();
	} );
}, 20 );

Abilities::init();

add_action( 'rest_api_init', function() {
	Routes::register();
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once DOCSYNC_PATH . 'inc/WPCLI/CLI.php';
	\DocSync\WPCLI\CLI::register();
}

add_filter( 'html_to_blocks_supported_post_types', function( $post_types ) {
	$post_types[] = 'documentation';
	return $post_types;
} );

add_filter( 'docsync_search_post_types', function( $post_types ) {
	$post_types[] = 'documentation';
	return $post_types;
} );

register_activation_hook( __FILE__, function() {
	docsync_maybe_migrate_options();
	flush_rewrite_rules();
} );

/**
 * Migrate options from the old chubes_docs_ prefix to docsync_ prefix.
 *
 * Runs on activation. Safe to call multiple times — only migrates
 * if old options exist and new ones don't.
 *
 * @since 0.1.0
 */
function docsync_maybe_migrate_options() {
	$migrations = [
		'chubes_docs_github_pat'    => 'docsync_github_pat',
		'chubes_docs_sync_interval' => 'docsync_sync_interval',
	];

	foreach ( $migrations as $old_key => $new_key ) {
		$old_value = get_option( $old_key );
		if ( $old_value !== false && get_option( $new_key ) === false ) {
			update_option( $new_key, $old_value );
			delete_option( $old_key );
		}
	}

	// Migrate cron hook.
	$old_hook = 'chubes_docs_github_sync';
	$new_hook = 'docsync_github_sync';
	$old_next = wp_next_scheduled( $old_hook );
	if ( $old_next ) {
		wp_unschedule_event( $old_next, $old_hook );
	}
}

register_deactivation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );

/**
 * Global wrapper for Project::get_repository_info()
 *
 * Provides theme templates with access to repository metadata (GitHub URL, WP.org URL, installs)
 * for a given project term without requiring direct class access.
 *
 * @param WP_Term|array $term_or_terms Single term object or array of term objects
 * @return array Repository info with github_url, wp_url, and installs keys
 */
function docsync_get_repository_info( $term_or_terms ) {
	return Project::get_repository_info( $term_or_terms );
}

/**
 * Generate URL for viewing content of specific type for a project
 *
 * @param string  $post_type The post type
 * @param WP_Term $term      The project term
 * @return string The URL to view this content type for this project
 */
function docsync_generate_content_type_url( $post_type, $term ) {
	return ProjectCard::generate_content_type_url( $post_type, $term );
}
