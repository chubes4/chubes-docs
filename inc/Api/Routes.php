<?php

namespace ChubesDocs\Api;

use ChubesDocs\Api\Controllers\DocsController;
use ChubesDocs\Api\Controllers\CodebaseController;
use ChubesDocs\Api\Controllers\SyncController;

class Routes {

    public const NAMESPACE = 'chubes/v1';

    public static function register(): void {
        self::register_docs_routes();
        self::register_codebase_routes();
        self::register_sync_routes();
    }

    private static function register_docs_routes(): void {
        register_rest_route(self::NAMESPACE, '/docs', [
            [
                'methods'             => 'GET',
                'callback'            => [DocsController::class, 'list_items'],
                'permission_callback' => '__return_true',
                'args'                => self::get_docs_list_args(),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [DocsController::class, 'create_item'],
                'permission_callback' => [self::class, 'check_edit_permission'],
                'args'                => self::get_docs_create_args(),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/docs/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [DocsController::class, 'get_item'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [DocsController::class, 'update_item'],
                'permission_callback' => [self::class, 'check_edit_permission'],
                'args'                => self::get_docs_update_args(),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [DocsController::class, 'delete_item'],
                'permission_callback' => [self::class, 'check_delete_permission'],
                'args'                => [
                    'id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'force' => [
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ]);
    }

    private static function register_codebase_routes(): void {
        register_rest_route(self::NAMESPACE, '/codebase', [
            'methods'             => 'GET',
            'callback'            => [CodebaseController::class, 'list_terms'],
            'permission_callback' => '__return_true',
            'args'                => [
                'parent' => [
                    'type'              => 'integer',
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ],
                'hide_empty' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/codebase/tree', [
            'methods'             => 'GET',
            'callback'            => [CodebaseController::class, 'get_tree'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/codebase/resolve', [
            'methods'             => 'POST',
            'callback'            => [CodebaseController::class, 'resolve_path'],
            'permission_callback' => [self::class, 'check_resolve_permission'],
            'args'                => [
                'path' => [
                    'type'     => 'array',
                    'required' => true,
                    'items'    => ['type' => 'string'],
                ],
                'create_missing' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
                'project_meta' => [
                    'type'    => 'object',
                    'default' => [],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/codebase/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [CodebaseController::class, 'get_term'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [CodebaseController::class, 'update_term'],
                'permission_callback' => [self::class, 'check_manage_terms_permission'],
                'args'                => [
                    'id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'name' => [
                        'type' => 'string',
                    ],
                    'description' => [
                        'type' => 'string',
                    ],
                    'meta' => [
                        'type'    => 'object',
                        'default' => [],
                    ],
                ],
            ],
        ]);
    }

    private static function register_sync_routes(): void {
        register_rest_route(self::NAMESPACE, '/sync/status', [
            'methods'             => 'GET',
            'callback'            => [SyncController::class, 'get_status'],
            'permission_callback' => [self::class, 'check_edit_permission'],
            'args'                => [
                'project' => [
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/sync/doc', [
            'methods'             => 'POST',
            'callback'            => [SyncController::class, 'sync_doc'],
            'permission_callback' => [self::class, 'check_edit_permission'],
            'args'                => self::get_sync_doc_args(),
        ]);

        register_rest_route(self::NAMESPACE, '/sync/batch', [
            'methods'             => 'POST',
            'callback'            => [SyncController::class, 'sync_batch'],
            'permission_callback' => [self::class, 'check_edit_permission'],
            'args'                => [
                'docs' => [
                    'type'     => 'array',
                    'required' => true,
                ],
            ],
        ]);
    }

    public static function check_edit_permission(): bool {
        return current_user_can('edit_posts');
    }

    public static function check_delete_permission(): bool {
        return current_user_can('delete_posts');
    }

    public static function check_manage_terms_permission(): bool {
        return current_user_can('manage_categories');
    }

    public static function check_resolve_permission(\WP_REST_Request $request): bool {
        $create_missing = $request->get_param('create_missing');
        if ($create_missing) {
            return current_user_can('manage_categories');
        }
        return true;
    }

    private static function get_docs_list_args(): array {
        return [
            'codebase' => [
                'type' => 'string',
            ],
            'status' => [
                'type'    => 'string',
                'default' => 'publish',
            ],
            'per_page' => [
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ],
            'page' => [
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'type' => 'string',
            ],
        ];
    }

    private static function get_docs_create_args(): array {
        return [
            'title' => [
                'type'     => 'string',
                'required' => true,
            ],
            'content' => [
                'type'     => 'string',
                'required' => true,
            ],
            'excerpt' => [
                'type' => 'string',
            ],
            'status' => [
                'type'    => 'string',
                'default' => 'publish',
            ],
            'codebase_path' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
            'meta' => [
                'type'    => 'object',
                'default' => [],
            ],
        ];
    }

    private static function get_docs_update_args(): array {
        return [
            'id' => [
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ],
            'title' => [
                'type' => 'string',
            ],
            'content' => [
                'type' => 'string',
            ],
            'excerpt' => [
                'type' => 'string',
            ],
            'status' => [
                'type' => 'string',
            ],
            'codebase_path' => [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ],
            'meta' => [
                'type'    => 'object',
                'default' => [],
            ],
        ];
    }

    private static function get_sync_doc_args(): array {
        return [
            'source_file' => [
                'type'     => 'string',
                'required' => true,
            ],
            'title' => [
                'type'     => 'string',
                'required' => true,
            ],
            'content' => [
                'type'     => 'string',
                'required' => true,
            ],
            'codebase_path' => [
                'type'     => 'array',
                'required' => true,
                'items'    => ['type' => 'string'],
            ],
            'excerpt' => [
                'type' => 'string',
            ],
            'force' => [
                'type'    => 'boolean',
                'default' => false,
            ],
        ];
    }
}
