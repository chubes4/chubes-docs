<?php

namespace ChubesDocs\Api\Controllers;

use ChubesDocs\Core\Codebase;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ChubesDocs\Sync\SyncManager;

class SyncController {

    public static function setup_project(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $project_slug = sanitize_title($request->get_param('project_slug'));
        $project_name = sanitize_text_field($request->get_param('project_name'));
        $category_slug = sanitize_title($request->get_param('category_slug'));
        $category_name = sanitize_text_field($request->get_param('category_name'));

        $category_term = get_term_by('slug', $category_slug, Codebase::TAXONOMY);
        
        if (!$category_term) {
            $result = wp_insert_term($category_name, Codebase::TAXONOMY, [
                'slug' => $category_slug,
            ]);
            
            if (is_wp_error($result)) {
                return new WP_Error('category_creation_failed', $result->get_error_message(), ['status' => 500]);
            }
            
            $category_term = get_term($result['term_id'], Codebase::TAXONOMY);
        }

        $project_term = get_term_by('slug', $project_slug, Codebase::TAXONOMY);
        
        if (!$project_term) {
            $result = wp_insert_term($project_name, Codebase::TAXONOMY, [
                'slug'   => $project_slug,
                'parent' => $category_term->term_id,
            ]);
            
            if (is_wp_error($result)) {
                return new WP_Error('project_creation_failed', $result->get_error_message(), ['status' => 500]);
            }
            
            $project_term = get_term($result['term_id'], Codebase::TAXONOMY);
        } elseif ($project_term->parent !== $category_term->term_id) {
            wp_update_term($project_term->term_id, Codebase::TAXONOMY, [
                'parent' => $category_term->term_id,
            ]);
            $project_term = get_term($project_term->term_id, Codebase::TAXONOMY);
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

        $term = get_term_by( 'slug', sanitize_text_field( $project ), Codebase::TAXONOMY );

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
                    'taxonomy'         => Codebase::TAXONOMY,
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
                'sync_hash'      => get_post_meta($post->ID, '_sync_hash', true),
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
        $source_file = sanitize_text_field(wp_unslash($request->get_param('source_file')));
        $title = sanitize_text_field(wp_unslash($request->get_param('title')));
        $content = wp_kses_post(wp_unslash($request->get_param('content')));
        $project_term_id = absint($request->get_param('project_term_id'));
        $subpath = $request->get_param('subpath') ?? [];
        $excerpt = sanitize_textarea_field(wp_unslash($request->get_param('excerpt') ?? ''));
        $force = (bool) $request->get_param('force');

        $project_term = get_term($project_term_id, Codebase::TAXONOMY);
        if (!$project_term || is_wp_error($project_term)) {
            return new WP_Error('invalid_project_term', 'Project term not found', ['status' => 400]);
        }

        $result = SyncManager::sync_post($source_file, $title, $content, $project_term_id, $subpath, $excerpt, $force);

        if (!$result['success']) {
            return new WP_Error('sync_failed', $result['error'] ?? 'Sync failed', ['status' => 500]);
        }

        return rest_ensure_response($result);
    }

    public static function sync_batch(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $docs = $request->get_param('docs');

        if (!is_array($docs) || empty($docs)) {
            return new WP_Error('invalid_docs', 'docs must be a non-empty array', ['status' => 400]);
        }

        $results = [];
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $failed = 0;

        foreach ($docs as $doc) {
            $source_file = sanitize_text_field(wp_unslash($doc['source_file'] ?? ''));
            $title = sanitize_text_field(wp_unslash($doc['title'] ?? ''));
            $content = wp_kses_post(wp_unslash($doc['content'] ?? ''));
            $project_term_id = absint($doc['project_term_id'] ?? 0);
            $subpath = $doc['subpath'] ?? [];
            $excerpt = sanitize_textarea_field(wp_unslash($doc['excerpt'] ?? ''));
            $force = (bool) ($doc['force'] ?? false);

            if (empty($source_file) || empty($title) || empty($content) || empty($project_term_id)) {
                $results[] = [
                    'source_file' => $source_file,
                    'success'     => false,
                    'error'       => 'Missing required fields',
                ];
                $failed++;
                continue;
            }

            $result = SyncManager::sync_post($source_file, $title, $content, $project_term_id, $subpath, $excerpt, $force);
            $results[] = $result;

            if ($result['success']) {
                if ($result['action'] === 'created') {
                    $created++;
                } elseif ($result['action'] === 'updated') {
                    $updated++;
                } else {
                    $unchanged++;
                }
            } else {
                $failed++;
            }
        }

        return rest_ensure_response([
            'total'     => count($docs),
            'created'   => $created,
            'updated'   => $updated,
            'unchanged' => $unchanged,
            'failed'    => $failed,
            'results'   => $results,
        ]);
    }
}
