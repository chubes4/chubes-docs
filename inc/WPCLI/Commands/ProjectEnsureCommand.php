<?php

namespace ChubesDocs\WPCLI\Commands;

use ChubesDocs\Core\Project;
use WP_CLI;
use WP_CLI\Utils;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProjectEnsureCommand {
	public static function run( array $args, array $assoc_args ): void {
		$type_slug = sanitize_title( $assoc_args['type'] ?? '' );
		$project_slug = sanitize_title( $assoc_args['project'] ?? '' );
		$project_name = sanitize_text_field( $assoc_args['name'] ?? '' );
		$github_url = isset( $assoc_args['github'] ) ? esc_url_raw( $assoc_args['github'] ) : '';
		$wporg_url = isset( $assoc_args['wporg'] ) ? esc_url_raw( $assoc_args['wporg'] ) : '';
		$format = sanitize_key( $assoc_args['format'] ?? 'table' );

		if ( empty( $type_slug ) ) {
			WP_CLI::error( '--type is required (e.g. --type=cli)' );
		}

		if ( empty( $project_slug ) ) {
			WP_CLI::error( '--project is required (e.g. --project=homeboy)' );
		}

		if ( empty( $project_name ) ) {
			WP_CLI::error( '--name is required (e.g. --name="Homeboy")' );
		}

		if ( ! in_array( $format, [ 'table', 'json', 'yaml' ], true ) ) {
			WP_CLI::error( '--format must be one of: table, json, yaml' );
		}

		$type_term = self::ensure_project_type_term( $type_slug );
		if ( is_wp_error( $type_term ) ) {
			WP_CLI::error( $type_term->get_error_message() );
		}

		$project_term = self::ensure_project_term( $project_slug, $project_name );
		if ( is_wp_error( $project_term ) ) {
			WP_CLI::error( $project_term->get_error_message() );
		}

		if ( ! empty( $github_url ) ) {
			update_term_meta( $project_term->term_id, 'project_github_url', $github_url );
		}

		if ( ! empty( $wporg_url ) ) {
			update_term_meta( $project_term->term_id, 'project_wp_url', $wporg_url );
		}

		// Set project type meta on the project term
		update_term_meta( $project_term->term_id, 'project_type', $type_term->slug );

		WP_CLI::success( 'Project terms ensured.' );

		$rows = [
			[
				'type_slug'        => $type_term->slug,
				'type_term_id'     => (string) $type_term->term_id,
				'project_slug'     => $project_term->slug,
				'project_term_id'  => (string) $project_term->term_id,
				'github_url'       => Project::get_github_url( $project_term->term_id ) ?? '',
				'wporg_url'        => Project::get_wp_url( $project_term->term_id ) ?? '',
				'project_type'     => Project::get_project_type( $project_term ),
			],
		];

		Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
	}

	private static function ensure_project_type_term( string $slug ): \WP_Term|WP_Error {
		$existing = get_term_by( 'slug', $slug, 'project_type' );
		if ( $existing && ! is_wp_error( $existing ) ) {
			WP_CLI::log( "Using existing project type: {$slug}" );
			return $existing;
		}

		WP_CLI::warning( "Creating new project type: {$slug}" );

		$result = wp_insert_term( $slug, 'project_type', [
			'slug' => $slug,
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return get_term( $result['term_id'], 'project_type' );
	}

	private static function ensure_project_term( string $slug, string $name ): \WP_Term|WP_Error {
		$existing = get_term_by( 'slug', $slug, Project::TAXONOMY );
		if ( $existing && ! is_wp_error( $existing ) ) {
			if ( (int) $existing->parent !== 0 ) {
				return new WP_Error( 'term_parent_mismatch', "Project term '{$slug}' exists but is not top-level" );
			}
			WP_CLI::log( "Using existing project: {$slug}" );
			return $existing;
		}

		WP_CLI::log( "Creating new project: {$slug}" );

		$result = wp_insert_term( $name, Project::TAXONOMY, [
			'slug'   => $slug,
			'parent' => 0,
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return get_term( $result['term_id'], Project::TAXONOMY );
	}
}
