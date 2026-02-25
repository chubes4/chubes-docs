<?php
/**
 * Documentation Custom Post Type Registration
 * 
 * Registers the documentation CPT with Gutenberg support and public archives.
 * Provides hooks for extensibility.
 */

namespace DocSync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Documentation {

	const POST_TYPE = 'documentation';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_fields' ] );
	}

	public static function register() {
		$labels = array(
			'name'                  => _x( 'Documentation', 'Post Type General Name', 'docsync' ),
			'singular_name'         => _x( 'Doc', 'Post Type Singular Name', 'docsync' ),
			'menu_name'             => __( 'Documentation', 'docsync' ),
			'name_admin_bar'        => __( 'Documentation', 'docsync' ),
			'archives'              => __( 'Doc Archives', 'docsync' ),
			'attributes'            => __( 'Doc Attributes', 'docsync' ),
			'parent_item_colon'     => __( 'Parent Doc:', 'docsync' ),
			'all_items'             => __( 'All Docs', 'docsync' ),
			'add_new_item'          => __( 'Add New Doc', 'docsync' ),
			'add_new'               => __( 'Add New', 'docsync' ),
			'new_item'              => __( 'New Doc', 'docsync' ),
			'edit_item'             => __( 'Edit Doc', 'docsync' ),
			'update_item'           => __( 'Update Doc', 'docsync' ),
			'view_item'             => __( 'View Doc', 'docsync' ),
			'view_items'            => __( 'View Docs', 'docsync' ),
			'search_items'          => __( 'Search Docs', 'docsync' ),
			'not_found'             => __( 'Not found', 'docsync' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'docsync' ),
			'featured_image'        => __( 'Featured Image', 'docsync' ),
			'set_featured_image'    => __( 'Set featured image', 'docsync' ),
			'remove_featured_image' => __( 'Remove featured image', 'docsync' ),
			'use_featured_image'    => __( 'Use as featured image', 'docsync' ),
			'insert_into_item'      => __( 'Insert into doc', 'docsync' ),
			'uploaded_to_this_item' => __( 'Uploaded to this doc', 'docsync' ),
			'items_list'            => __( 'Docs list', 'docsync' ),
			'items_list_navigation' => __( 'Docs list navigation', 'docsync' ),
			'filter_items_list'     => __( 'Filter docs list', 'docsync' ),
		);

		$args = array(
			'label'               => __( 'Documentation', 'docsync' ),
			'description'         => __( 'Technical documentation for my coding projects. Automatically synchronized with GitHub repositories.', 'docsync' ),
			'labels'              => $labels,
		'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'post_tag' ),
		'taxonomies'          => array( 'post_tag' ),
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

		$args = apply_filters( 'docsync_documentation_args', $args );

		register_post_type( self::POST_TYPE, $args );

		do_action( 'docsync_documentation_registered' );
	}

	public static function register_rest_fields() {
		register_rest_field( self::POST_TYPE, 'project_info', [
			'get_callback' => function( $post ) {
				$terms = get_the_terms( $post['id'], Project::TAXONOMY );

				if ( ! $terms || is_wp_error( $terms ) ) {
					return null;
				}

				$project_term = Project::get_project_term( $terms );

				if ( ! $project_term ) {
					return null;
				}

				return [
					'id'   => $project_term->term_id,
					'name' => $project_term->name,
					'slug' => $project_term->slug,
				];
			},
			'schema'       => [
				'type'        => 'object',
				'description' => 'Project information',
				'properties'  => [
					'id'   => [ 'type' => 'integer' ],
					'name' => [ 'type' => 'string' ],
					'slug' => [ 'type' => 'string' ],
				],
			],
		] );

		register_rest_field( self::POST_TYPE, 'project_type_info', [
			'get_callback' => function( $post ) {
				if ( function_exists( 'wp_abilities_execute' ) ) {
					$ability_result = wp_abilities_execute( 'docsync/get-projects', [
						'post_ids' => [ $post['id'] ],
					] );
					
					if ( $ability_result['success'] && ! empty( $ability_result['projects'] ) ) {
						$project = $ability_result['projects'][0];
						return $project['project_type'] ?? null;
					}
				}
				
				// Fallback to direct method if Abilities API not available
				$project_type = Project::get_project_type( $post['id'] );
				if ( ! $project_type ) {
					return null;
				}
				
				$term = get_term_by( 'slug', $project_type, 'project_type' );
				if ( ! $term || is_wp_error( $term ) ) {
					return null;
				}
				
				return [
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				];
			},
			'schema'       => [
				'type'        => 'object',
				'description' => 'Project type information',
				'properties'  => [
					'id'   => [ 'type' => 'integer' ],
					'name' => [ 'type' => 'string' ],
					'slug' => [ 'type' => 'string' ],
				],
			],
		] );

		register_rest_field( self::POST_TYPE, 'tags', [
			'get_callback' => function( $post ) {
				$tags = get_the_terms( $post['id'], 'post_tag' );
				if ( ! $tags || is_wp_error( $tags ) ) {
					return [];
				}
				return array_map( function( $tag ) {
					return [
						'id'   => $tag->term_id,
						'name' => $tag->name,
						'slug' => $tag->slug,
					];
				}, $tags );
			},
			'schema' => [
				'type'        => 'array',
				'description' => 'Post tags',
				'items' => [
					'type'       => 'object',
					'properties' => [
						'id'   => [ 'type' => 'integer' ],
						'name' => [ 'type' => 'string' ],
						'slug' => [ 'type' => 'string' ],
					],
				],
			],
		] );
	}
}
