<?php

namespace ChubesDocs\Api\Controllers;

use ChubesDocs\Core\Project;
use ChubesDocs\Sync\CronSync;
use ChubesDocs\Sync\GitHubClient;
use ChubesDocs\Sync\SyncManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SyncController {

    /**
     * Test GitHub token connection.
     */
    public static function test_token( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $pat = get_option( CronSync::OPTION_PAT );

        if ( empty( $pat ) ) {
            return new WP_Error( 'no_token', 'No token configured.', [ 'status' => 400 ] );
        }

        $github = new GitHubClient( $pat );
        $diagnostics = $github->test_connection();

        if ( is_wp_error( $diagnostics ) ) {
            return new WP_Error( 'connection_failed', $diagnostics->get_error_message(), [ 'status' => 500 ] );
        }

        return rest_ensure_response( $diagnostics );
    }

    /**
     * Test access to a specific repository.
     */
    public static function test_repo( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $pat = get_option( CronSync::OPTION_PAT );

        if ( empty( $pat ) ) {
            return new WP_Error( 'no_token', 'No token configured.', [ 'status' => 400 ] );
        }

        $repo_url = $request->get_param( 'repo_url' );

        if ( empty( $repo_url ) ) {
            return new WP_Error( 'no_repo_url', 'No repository URL provided.', [ 'status' => 400 ] );
        }

        $parsed = GitHubClient::parse_repo_url( $repo_url );

        if ( ! $parsed ) {
            return new WP_Error( 'invalid_url', 'Invalid GitHub URL format.', [ 'status' => 400 ] );
        }

        $github = new GitHubClient( $pat );
        $result = $github->test_repo( $parsed['owner'], $parsed['repo'] );

        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            return new WP_Error(
                'repo_test_failed',
                $result->get_error_message(),
                [
                    'status'  => $data['status'] ?? 500,
                    'owner'   => $parsed['owner'],
                    'repo'    => $parsed['repo'],
                    'sso_url' => $data['sso_url'] ?? null,
                ]
            );
        }

        return rest_ensure_response( $result );
    }

    public static function setup_project(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $project_slug = sanitize_title($request->get_param('project_slug'));
        $project_name = sanitize_text_field($request->get_param('project_name'));
        $category_slug = sanitize_title($request->get_param('category_slug'));
        $category_name = sanitize_text_field($request->get_param('category_name'));
        $github_url = esc_url_raw( $request->get_param( 'github_url' ) ?? '' );
        $wp_url = esc_url_raw( $request->get_param( 'wp_url' ) ?? '' );

        $category_term = get_term_by('slug', $category_slug, Project::TAXONOMY);
        
        if (!$category_term) {
            $result = wp_insert_term($category_name, Project::TAXONOMY, [
                'slug' => $category_slug,
            ]);
            
            if (is_wp_error($result)) {
                return new WP_Error('category_creation_failed', $result->get_error_message(), ['status' => 500]);
            }
            
            $category_term = get_term($result['term_id'], Project::TAXONOMY);
        }

        $project_term = get_term_by('slug', $project_slug, Project::TAXONOMY);
        
        if (!$project_term) {
            $result = wp_insert_term($project_name, Project::TAXONOMY, [
                'slug'   => $project_slug,
                'parent' => $category_term->term_id,
            ]);
            
            if (is_wp_error($result)) {
                return new WP_Error('project_creation_failed', $result->get_error_message(), ['status' => 500]);
            }
            
            $project_term = get_term($result['term_id'], Project::TAXONOMY);
        } elseif ($project_term->parent !== $category_term->term_id) {
            wp_update_term($project_term->term_id, Project::TAXONOMY, [
                'parent' => $category_term->term_id,
            ]);
            $project_term = get_term($project_term->term_id, Project::TAXONOMY);
        }

        if ( ! empty( $github_url ) ) {
            update_term_meta( $project_term->term_id, 'project_github_url', $github_url );
        }

        if ( ! empty( $wp_url ) ) {
            update_term_meta( $project_term->term_id, 'project_wp_url', $wp_url );
        }

        return rest_ensure_response([
            'success'          => true,
            'category_term_id' => $category_term->term_id,
            'category_slug'    => $category_term->slug,
            'project_term_id'  => $project_term->term_id,
            'project_slug'     => $project_term->slug,
        ]);
    }

    public static function get_status(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $project = $request->get_param('project');

        if ( ! $project ) {
            return new WP_Error(
                'missing_project',
                'project parameter is required',
                ['status' => 400]
            );
        }

        $term = get_term_by( 'slug', sanitize_text_field( $project ), Project::TAXONOMY );

        if ( ! $term ) {
            return new WP_Error(
                'project_not_found',
                "Project '{$project}' not found",
                ['status' => 404]
            );
        }

        $args = [
            'post_type'      => 'documentation',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_sync_source_file',
                    'compare' => 'EXISTS',
                ],
            ],
            'tax_query'      => [
                [
                    'taxonomy'         => Project::TAXONOMY,
                    'field'            => 'term_id',
                    'terms'            => $term->term_id,
                    'include_children' => true,
                ],
            ],
        ];

        $query = new \WP_Query($args);
        $docs = [];

        foreach ($query->posts as $post) {
            $docs[] = [
                'post_id'        => $post->ID,
                'title'          => $post->post_title,
                'source_file'    => get_post_meta($post->ID, '_sync_source_file', true),
                'sync_filesize'  => get_post_meta($post->ID, '_sync_filesize', true),
                'sync_timestamp' => get_post_meta($post->ID, '_sync_timestamp', true),
                'status'         => 'synced',
            ];
        }

        return rest_ensure_response([
            'total_docs'    => count($docs),
            'synced_docs'   => count($docs),
            'project_slug'  => $project,
            'project_term'  => $term->term_id,
            'docs'          => $docs,
        ]);
    }

    public static function sync_doc(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $source_file = sanitize_text_field($request->get_param('source_file'));
        $title = sanitize_text_field($request->get_param('title'));
        $content = wp_kses_post($request->get_param('content'));
        $project_term_id = absint($request->get_param('project_term_id'));
        $filesize = absint($request->get_param('filesize'));
        $timestamp = sanitize_text_field($request->get_param('timestamp'));
        $subpath = $request->get_param('subpath');
        $excerpt = sanitize_textarea_field($request->get_param('excerpt') ?? '');
        $force = (bool) $request->get_param('force');

        if (empty($source_file) || empty($title) || empty($content) || !$project_term_id) {
            return new WP_Error('missing_required_params', 'Missing required parameters', ['status' => 400]);
        }

        $result = SyncManager::sync_post(
            $source_file,
            $title,
            $content,
            $project_term_id,
            $filesize,
            $timestamp,
            $subpath ?: [],
            $excerpt,
            $force
        );

        if (!$result['success']) {
            return new WP_Error('sync_failed', $result['error'], ['status' => 500]);
        }

        return rest_ensure_response($result);
    }

    public static function sync_batch(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $docs = $request->get_param('docs');

        if (!is_array($docs) || empty($docs)) {
            return new WP_Error('invalid_docs', 'docs parameter must be a non-empty array', ['status' => 400]);
        }

        $results = [];
        $errors = [];
        $counts = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0,
        ];

        foreach ($docs as $index => $doc) {
            $source_file = sanitize_text_field($doc['source_file'] ?? '');
            $title = sanitize_text_field($doc['title'] ?? '');
            $content = wp_kses_post($doc['content'] ?? '');
            $project_term_id = absint($doc['project_term_id'] ?? 0);
            $filesize = absint($doc['filesize'] ?? 0);
            $timestamp = sanitize_text_field($doc['timestamp'] ?? '');
            $subpath = $doc['subpath'] ?? [];
            $excerpt = sanitize_textarea_field($doc['excerpt'] ?? '');
            $force = (bool) ($doc['force'] ?? false);

            if (empty($source_file) || empty($title) || empty($content) || !$project_term_id) {
                $errors[] = [
                    'index' => $index,
                    'error' => 'Missing required parameters',
                ];
                $counts['failed']++;
                continue;
            }

            $result = SyncManager::sync_post(
                $source_file,
                $title,
                $content,
                $project_term_id,
                $filesize,
                $timestamp,
                $subpath,
                $excerpt,
                $force
            );

            if ($result['success']) {
                $results[] = $result;
                $action = $result['action'] ?? 'unknown';
                if (isset($counts[$action])) {
                    $counts[$action]++;
                }
            } else {
                $errors[] = [
                    'index' => $index,
                    'source_file' => $source_file,
                    'error' => $result['error'],
                ];
                $counts['failed']++;
            }
        }

        return rest_ensure_response([
            'total' => count($docs),
            'created' => $counts['created'],
            'updated' => $counts['updated'],
            'unchanged' => $counts['unchanged'],
            'failed' => $counts['failed'],
            'results' => $results,
            'errors' => $errors,
        ]);
    }
}
