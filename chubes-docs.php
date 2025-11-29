<?php
/**
 * Plugin Name: Chubes Docs
 * Description: REST API sync system and admin enhancements for chubes.net documentation. Requires the Chubes theme.
 * Version: 0.1.0
 * Author: Chris Huber
 * Author URI: https://chubes.net
 * License: GPL v2 or later
 * Text Domain: chubes-docs
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHUBES_DOCS_VERSION', '0.1.0' );
define( 'CHUBES_DOCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHUBES_DOCS_URL', plugin_dir_url( __FILE__ ) );

require_once CHUBES_DOCS_PATH . 'vendor/autoload.php';

use ChubesDocs\Api\Routes;
use ChubesDocs\Fields\RepositoryFields;
use ChubesDocs\Fields\InstallTracker;
use ChubesDocs\Templates\RelatedPosts;

add_action( 'chubes_codebase_registered', function() {
	RepositoryFields::init();
	InstallTracker::init();
} );

add_action( 'init', function() {
	RelatedPosts::init();
} );

add_action( 'rest_api_init', function() {
	Routes::register();
} );

add_filter( 'html_to_blocks_supported_post_types', function( $post_types ) {
	$post_types[] = 'documentation';
	return $post_types;
} );

register_activation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
	flush_rewrite_rules();
} );
