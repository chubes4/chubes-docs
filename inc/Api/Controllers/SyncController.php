<?php

namespace ChubesDocs\Api\Controllers;

use ChubesDocs\Core\Codebase;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ChubesDocs\Sync\SyncManager;

class SyncController {

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
        $codebase_path = $request->get_param('codebase_path');
        $excerpt = sanitize_textarea_field(wp_unslash($request->get_param('excerpt') ?? ''));
        $force = (bool) $request->get_param('force');

        if (!is_array($codebase_path) || empty($codebase_path)) {
            return new WP_Error('invalid_codebase_path', 'codebase_path must be a non-empty array', ['status' => 400]);
        }

        $result = SyncManager::sync_post($source_file, $title, $content, $codebase_path, $excerpt, $force);

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
            $codebase_path = $doc['codebase_path'] ?? [];
            $excerpt = sanitize_textarea_field(wp_unslash($doc['excerpt'] ?? ''));
            $force = (bool) ($doc['force'] ?? false);

            if (empty($source_file) || empty($title) || empty($content) || empty($codebase_path)) {
                $results[] = [
                    'source_file' => $source_file,
                    'success'     => false,
                    'error'       => 'Missing required fields',
                ];
                $failed++;
                continue;
            }

            $result = SyncManager::sync_post($source_file, $title, $content, $codebase_path, $excerpt, $force);
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
