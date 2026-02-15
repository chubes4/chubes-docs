<?php
/**
 * Plugin Name: Chubes Docs
 * Description: REST API sync system and admin enhancements for chubes.net documentation. Requires Chubes theme.
 * Version: 0.9.7
 * Author: Chris Huber
 * Author URI: https://chubes.net
 * License: GPL v2 or later
 * Text Domain: chubes-docs
 * Requires at least: 6.9
 * Requires PHP: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CHUBES_DOCS_VERSION', '0.9.7' );
define( 'CHUBES_DOCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHUBES_DOCS_URL', plugin_dir_url( __FILE__ ) );

require_once CHUBES_DOCS_PATH . 'vendor/autoload.php';

use ChubesDocs\Api\Routes;
use ChubesDocs\Abilities\Abilities;
use ChubesDocs\Core\Assets;
use ChubesDocs\Core\Documentation;
use ChubesDocs\Core\Project;
use ChubesDocs\Core\RewriteRules;
use ChubesDocs\Core\Breadcrumbs;
use ChubesDocs\Fields\RepositoryFields;
use ChubesDocs\Fields\InstallTracker;
use ChubesDocs\Templates\RelatedPosts;
use ChubesDocs\Templates\Archive;
use ChubesDocs\Templates\ProjectCard;
use ChubesDocs\Templates\Homepage;
use ChubesDocs\Templates\SearchBar;
use ChubesDocs\Sync\CronSync;
use ChubesDocs\Admin\SettingsPage;
use ChubesDocs\Admin\ProjectColumns;
use ChubesDocs\Admin\DocumentationColumns;

Documentation::init();
Project::init();
RewriteRules::init();
Assets::init();

add_action( 'chubes_project_registered', function() {
	RepositoryFields::init();
	InstallTracker::init();
	ProjectColumns::init();
} );

CronSync::init();
SettingsPage::init();
DocumentationColumns::init();

add_action( 'init', function() {
	RelatedPosts::init();
	Breadcrumbs::init();
	Archive::init();
	Homepage::init();
	SearchBar::init();
} );

add_action( 'rest_api_init', function() {
	Routes::register();
	Abilities::init();
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CHUBES_DOCS_PATH . 'inc/WPCLI/CLI.php';
	\ChubesDocs\WPCLI\CLI::register();
}

add_filter( 'html_to_blocks_supported_post_types', function( $post_types ) {
	$post_types[] = 'documentation';
	return $post_types;
} );

add_filter( 'chubes_search_post_types', function( $post_types ) {
	$post_types[] = 'documentation';
	return $post_types;
} );

register_activation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );

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
function chubes_get_repository_info( $term_or_terms ) {
	return Project::get_repository_info( $term_or_terms );
}

/**
 * Generate URL for viewing content of specific type for a project
 *
 * @param string  $post_type The post type
 * @param WP_Term $term      The project term
 * @return string The URL to view this content type for this project
 */
function chubes_generate_content_type_url( $post_type, $term ) {
	return ProjectCard::generate_content_type_url( $post_type, $term );
}
