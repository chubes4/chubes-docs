<?php

namespace ChubesDocs\Api\Controllers;

use ChubesDocs\Core\Project;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Query;

class DocsController {

    public static function list_items(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $args = [
            'post_type'      => 'documentation',
            'post_status'    => sanitize_text_field($request->get_param('status') ?? 'publish'),
            'posts_per_page' => absint($request->get_param('per_page') ?? 10),
            'paged'          => absint($request->get_param('page') ?? 1),
        ];

        $project = $request->get_param('project');
        if ($project) {
            $args['tax_query'] = [
                [
                    'taxonomy' => Project::TAXONOMY,
                    'field'    => is_numeric($project) ? 'term_id' : 'slug',
                    'terms'    => $project,
                ],
            ];
        }

        $search = $request->get_param('search');
        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }

        $query = new WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = self::prepare_item($post);
        }

        return rest_ensure_response([
            'items'       => $items,
            'total'       => $query->found_posts,
            'pages'       => $query->max_num_pages,
            'current_page' => absint($request->get_param('page') ?? 1),
        ]);
    }

    public static function get_item(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = absint($request->get_param('id'));
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'documentation') {
            return new WP_Error('not_found', 'Documentation not found', ['status' => 404]);
        }

        $format = sanitize_text_field($request->get_param('format') ?? 'markdown');
        return rest_ensure_response(self::prepare_item($post, true, $format));
    }

    public static function create_item(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $title = sanitize_text_field(wp_unslash($request->get_param('title')));
        $content = wp_kses_post(wp_unslash($request->get_param('content')));
        $excerpt = sanitize_textarea_field(wp_unslash($request->get_param('excerpt') ?? ''));
        $status = sanitize_text_field($request->get_param('status') ?? 'publish');

        $post_data = [
            'post_type'    => 'documentation',
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $project_path = $request->get_param('project_path');
        if (!empty($project_path) && is_array($project_path)) {
            $resolved = Project::resolve_path($project_path, true);
            if ($resolved['success'] && $resolved['leaf_term_id']) {
                wp_set_object_terms($post_id, $resolved['leaf_term_id'], Project::TAXONOMY);
            }
        }

        $meta = $request->get_param('meta');
        if (!empty($meta) && is_array($meta)) {
            foreach ($meta as $key => $value) {
                $key = sanitize_key($key);
                if (strpos($key, '_sync_') === 0) {
                    update_post_meta($post_id, $key, sanitize_text_field($value));
                }
            }
        }

        $post = get_post($post_id);
        return rest_ensure_response(self::prepare_item($post, true));
    }

    public static function update_item(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = absint($request->get_param('id'));
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'documentation') {
            return new WP_Error('not_found', 'Documentation not found', ['status' => 404]);
        }

        $post_data = ['ID' => $post_id];

        $title = $request->get_param('title');
        if ($title !== null) {
            $post_data['post_title'] = sanitize_text_field(wp_unslash($title));
        }

        $content = $request->get_param('content');
        if ($content !== null) {
            $post_data['post_content'] = wp_kses_post(wp_unslash($content));
        }

        $excerpt = $request->get_param('excerpt');
        if ($excerpt !== null) {
            $post_data['post_excerpt'] = sanitize_textarea_field(wp_unslash($excerpt));
        }

        $status = $request->get_param('status');
        if ($status !== null) {
            $post_data['post_status'] = sanitize_text_field($status);
        }

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            return $result;
        }

        $project_path = $request->get_param('project_path');
        if (!empty($project_path) && is_array($project_path)) {
            $resolved = Project::resolve_path($project_path, true);
            if ($resolved['success'] && $resolved['leaf_term_id']) {
                wp_set_object_terms($post_id, $resolved['leaf_term_id'], Project::TAXONOMY);
            }
        }

        $meta = $request->get_param('meta');
        if (!empty($meta) && is_array($meta)) {
            foreach ($meta as $key => $value) {
                $key = sanitize_key($key);
                if (strpos($key, '_sync_') === 0) {
                    update_post_meta($post_id, $key, sanitize_text_field($value));
                }
            }
        }

        $post = get_post($post_id);
        return rest_ensure_response(self::prepare_item($post, true));
    }

    public static function delete_item(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = absint($request->get_param('id'));
        $force = (bool) $request->get_param('force');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'documentation') {
            return new WP_Error('not_found', 'Documentation not found', ['status' => 404]);
        }

        if ($force) {
            $result = wp_delete_post($post_id, true);
        } else {
            $result = wp_trash_post($post_id);
        }

        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete documentation', ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'id'      => $post_id,
            'deleted' => $force,
            'trashed' => !$force,
        ]);
    }

    private static function prepare_item(\WP_Post $post, bool $include_content = false, string $format = 'html'): array {
        $terms = get_the_terms($post->ID, Project::TAXONOMY);
        $project_data = self::prepare_project_data($terms ?: []);

        $item = [
            'id'       => $post->ID,
            'title'    => $post->post_title,
            'slug'     => $post->post_name,
            'excerpt'  => $post->post_excerpt,
            'status'   => $post->post_status,
            'link'     => get_permalink($post->ID),
            'project' => $project_data,
            'meta'     => [
                'sync_source_file' => get_post_meta($post->ID, '_sync_source_file', true),
                'sync_hash'        => get_post_meta($post->ID, '_sync_hash', true),
                'sync_timestamp'   => get_post_meta($post->ID, '_sync_timestamp', true),
            ],
        ];

        if ($include_content) {
            if ($format === 'markdown' || $format === 'md') {
                $markdown = get_post_meta($post->ID, '_sync_markdown', true);
                $item['content'] = !empty($markdown) ? $markdown : $post->post_content;
                $item['content_format'] = !empty($markdown) ? 'markdown' : 'html';
            } else {
                $item['content'] = $post->post_content;
                $item['content_format'] = 'html';
            }
        }

        return $item;
    }

    private static function prepare_project_data(array $terms): array {
        if (empty($terms)) {
            return [
                'assigned_term'  => null,
                'project'        => null,
                'category'       => null,
                'project_type'   => null,
                'hierarchy_path' => '',
            ];
        }

        $primary = Project::get_primary_term($terms);
        $project = Project::get_project_term($terms);
        $category = Project::get_top_level_term($terms);

        $project_type_data = null;
        if ( $project ) {
            $type_slug = Project::get_project_type( $project );
            if ( $type_slug ) {
                $type_term = get_term_by( 'slug', $type_slug, 'project_type' );
                if ( $type_term && ! is_wp_error( $type_term ) ) {
                    $project_type_data = [
                        'id'   => $type_term->term_id,
                        'name' => $type_term->name,
                        'slug' => $type_term->slug,
                    ];
                }
            }
        }

        return [
            'assigned_term'  => $primary ? [
                'id'   => $primary->term_id,
                'slug' => $primary->slug,
                'name' => $primary->name,
            ] : null,
            'project'        => $project ? [
                'id'   => $project->term_id,
                'slug' => $project->slug,
                'name' => $project->name,
            ] : null,
            'category'       => $category ? [
                'id'   => $category->term_id,
                'slug' => $category->slug,
                'name' => $category->name,
            ] : null,
            'project_type'   => $project_type_data,
            'hierarchy_path' => Project::build_term_hierarchy_path($terms),
        ];
    }
}
