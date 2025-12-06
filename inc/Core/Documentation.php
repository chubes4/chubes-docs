<?php
/**
 * Documentation Custom Post Type Registration
 * 
 * Registers the documentation CPT with Gutenberg support and public archives.
 * Provides hooks for extensibility.
 */

namespace ChubesDocs\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Documentation {

	const POST_TYPE = 'documentation';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		$labels = array(
			'name'                  => _x( 'Documentation', 'Post Type General Name', 'chubes-docs' ),
			'singular_name'         => _x( 'Doc', 'Post Type Singular Name', 'chubes-docs' ),
			'menu_name'             => __( 'Documentation', 'chubes-docs' ),
			'name_admin_bar'        => __( 'Documentation', 'chubes-docs' ),
			'archives'              => __( 'Doc Archives', 'chubes-docs' ),
			'attributes'            => __( 'Doc Attributes', 'chubes-docs' ),
			'parent_item_colon'     => __( 'Parent Doc:', 'chubes-docs' ),
			'all_items'             => __( 'All Docs', 'chubes-docs' ),
			'add_new_item'          => __( 'Add New Doc', 'chubes-docs' ),
			'add_new'               => __( 'Add New', 'chubes-docs' ),
			'new_item'              => __( 'New Doc', 'chubes-docs' ),
			'edit_item'             => __( 'Edit Doc', 'chubes-docs' ),
			'update_item'           => __( 'Update Doc', 'chubes-docs' ),
			'view_item'             => __( 'View Doc', 'chubes-docs' ),
			'view_items'            => __( 'View Docs', 'chubes-docs' ),
			'search_items'          => __( 'Search Docs', 'chubes-docs' ),
			'not_found'             => __( 'Not found', 'chubes-docs' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'chubes-docs' ),
			'featured_image'        => __( 'Featured Image', 'chubes-docs' ),
			'set_featured_image'    => __( 'Set featured image', 'chubes-docs' ),
			'remove_featured_image' => __( 'Remove featured image', 'chubes-docs' ),
			'use_featured_image'    => __( 'Use as featured image', 'chubes-docs' ),
			'insert_into_item'      => __( 'Insert into doc', 'chubes-docs' ),
			'uploaded_to_this_item' => __( 'Uploaded to this doc', 'chubes-docs' ),
			'items_list'            => __( 'Docs list', 'chubes-docs' ),
			'items_list_navigation' => __( 'Docs list navigation', 'chubes-docs' ),
			'filter_items_list'     => __( 'Filter docs list', 'chubes-docs' ),
		);

		$args = array(
			'label'               => __( 'Documentation', 'chubes-docs' ),
			'description'         => __( 'Technical documentation for projects.', 'chubes-docs' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'taxonomies'          => array(),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-media-document',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => 'docs',
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rewrite'             => false,
		);

		$args = apply_filters( 'chubes_documentation_args', $args );

		register_post_type( self::POST_TYPE, $args );

		do_action( 'chubes_documentation_registered' );
	}
}
