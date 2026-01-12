# API Reference

This document provides comprehensive documentation for all REST API endpoints provided by the Chubes Docs plugin.

For workflows (non-authoritative examples), see the [Sync Guide](sync-guide.md). For GitHub diagnostics/manual sync, see [GitHub Sync Diagnostics](github-sync-diagnostics.md).
## Base URL

All endpoints use the base URL: `/wp-json/chubes/v1/`

## Authentication

Public endpoints do not require authentication. Write/admin endpoints require WordPress authentication (cookies, application passwords, or other WP-authenticated mechanisms).

## Documentation Endpoints

Permissions summary:
- `GET /docs` and `GET /docs/{id}`: public
- `POST /docs` and `PUT /docs/{id}`: `edit_posts`
- `DELETE /docs/{id}`: `delete_posts`

### GET /docs

List documentation posts.

**Permissions:** Public.

**Query parameters:**
- `per_page` (int, default `10`)
- `page` (int, default `1`)
- `codebase` (string): Codebase term slug or numeric term ID
- `status` (string, default `publish`)
- `search` (string)

**Response:**
```json
{
  "items": [
    {
      "id": 123,
      "title": "Getting Started",
      "slug": "getting-started",
      "excerpt": "Brief description",
      "status": "publish",
      "link": "https://example.com/docs/.../getting-started/",
      "codebase": {
        "assigned_term": {"id": 15, "slug": "my-plugin", "name": "My Plugin"},
        "project": {"id": 15, "slug": "my-plugin", "name": "My Plugin"},
        "category": {"id": 5, "slug": "wordpress-plugins", "name": "WordPress Plugins"},
        "project_type": "",
        "hierarchy_path": "wordpress-plugins/my-plugin"
      },
      "meta": {
        "sync_source_file": "README.md",
        "sync_hash": "...",
        "sync_timestamp": "2026-01-11T00:00:00Z"
      }
    }
  ],
  "total": 25,
  "pages": 3,
  "current_page": 1
}
```

> `content` is not included in list responses. Use `GET /docs/{id}` for content.
### POST /docs

Create a new documentation post.

**Permissions:** Requires `edit_posts`.
**Parameters:**
- `title` (string, required): Documentation title
- `content` (string, required): Documentation content (supports Markdown)
- `excerpt` (string): Brief description
- `status` (string): Post status (default: "publish")
- `codebase_path` (array): Taxonomy path as array (e.g., ["wordpress-plugins", "my-plugin"])
- `meta` (object): Additional metadata

**Response:**
```json
{
  "id": 124,
  "title": "New Documentation",
  "status": "publish",
  "codebase": {
    "assigned_term": {
      "id": 15,
      "slug": "my-plugin",
      "name": "My Plugin"
    },
    "project": {
      "id": 15,
      "slug": "my-plugin",
      "name": "My Plugin"
    },
    "category": {
      "id": 5,
      "slug": "wordpress-plugins",
      "name": "WordPress Plugins"
    },
    "project_type": "wordpress-plugin",
    "hierarchy_path": "wordpress-plugins/my-plugin"
  }
}
```

### GET /docs/{id}

Get a specific documentation post.

**Parameters:**
- `id` (int, required): Post ID

**Response:** Single documentation object. Same fields as list items, plus `content` (string) is included.
### PUT /docs/{id}

Update an existing documentation post.

**Permissions:** Requires `edit_posts`.
**Parameters:** Same as POST /docs, all optional except `id`.

**Response:** Updated documentation object.

### DELETE /docs/{id}

Delete a documentation post.

**Permissions:** Requires `delete_posts`.

**Parameters:**
- `force` (boolean, default `false`): Permanently delete instead of moving to trash

**Response:**
```json
{
  "success": true,
  "id": 123,
  "deleted": false,
  "trashed": true
}
```
## Codebase Taxonomy Endpoints

Permissions summary:
- `GET /codebase`, `GET /codebase/tree`, `GET /codebase/{id}`: public
- `POST /codebase/resolve`: public when `create_missing=false`, otherwise `manage_categories`
- `PUT /codebase/{id}`: `manage_categories`

### GET /codebase

List codebase taxonomy terms.

**Permissions:** Public.

**Query parameters:**
- `parent` (int, default `0`)
- `hide_empty` (boolean, default `false`)

**Response:** Unwrapped array of term objects.
```json
[
  {
    "id": 5,
    "name": "WordPress Plugins",
    "slug": "wordpress-plugins",
    "description": "",
    "parent": 0,
    "count": 15,
    "project_type": "",
    "is_top_level": true,
    "is_project": false
  }
]
```
### GET /codebase/tree

Get the complete hierarchical codebase tree.

**Permissions:** Public.

**Response:** Array of nested term objects.
```json
[
  {
    "id": 5,
    "name": "WordPress Plugins",
    "slug": "wordpress-plugins",
    "description": "",
    "parent": 0,
    "count": 15,
    "project_type": "",
    "is_top_level": true,
    "is_project": false,
    "children": [
      {
        "id": 12,
        "name": "My Plugin",
        "slug": "my-plugin",
        "description": "",
        "parent": 5,
        "count": 3,
        "project_type": "",
        "is_top_level": false,
        "is_project": true
      }
    ]
  }
]
```
### POST /codebase/resolve

Resolve a taxonomy path to terms, optionally creating missing terms.

**Permissions:**
- Public when `create_missing` is `false`
- Requires `manage_categories` when `create_missing` is `true`

**Body parameters:**
- `path` (array of strings, required): e.g. `["wordpress-plugins", "my-plugin", "api"]`
- `create_missing` (boolean, default `false`)
- `project_meta` (object, default `{}`): applied during resolution (when creating/ensuring the project term)

**Response:**
```json
{
  "success": true,
  "leaf_term_id": 15,
  "leaf_term_slug": "api",
  "path": "wordpress-plugins/my-plugin/api",
  "created": ["api"],
  "terms": [
    {"id": 5, "slug": "wordpress-plugins", "name": "WordPress Plugins"},
    {"id": 12, "slug": "my-plugin", "name": "My Plugin"},
    {"id": 15, "slug": "api", "name": "API"}
  ]
}
```
### GET /codebase/{id}

Get a specific taxonomy term.

**Parameters:**
- `id` (int, required): Term ID

**Response:** Single term object as shown in GET /codebase.

### PUT /codebase/{id}

Update a taxonomy term.

**Permissions:** Requires `manage_categories`.

**Body parameters:**
- `name` (string, optional)
- `description` (string, optional)
- `meta` (object, optional): only these keys are applied:
  - `github_url` (stored as term meta `codebase_github_url`)
  - `wp_url` (stored as term meta `codebase_wp_url`)

**Response:** Updated term object (includes `meta` and `repository_info`).
## Sync Endpoints

Permissions summary:
- `POST /sync/setup`: `manage_categories`
- `GET /sync/status`: `edit_posts`
- `POST /sync/doc`, `POST /sync/batch`: `edit_posts`
- Manual GitHub sync and diagnostics (`/sync/all`, `/sync/term/{id}`, `/sync/test-token`, `/sync/test-repo`): `manage_options`

### POST /sync/all

Manually sync all codebases that have a GitHub URL configured.

**Permissions:** Requires `manage_options`.

**Response:**
```json
{
  "repos_synced": 2,
  "total_added": 10,
  "total_updated": 3,
  "total_removed": 1,
  "errors": [],
  "message": "Synced 2 repos. Added: 10, Updated: 3, Removed: 1"
}
```

### POST /sync/term/{id}

Manually sync a single codebase term.

**Permissions:** Requires `manage_options`.

**Parameters:**
- `id` (int, required): Codebase term ID
- `force` (boolean, default `false`)

**Response:**
```json
{
  "added": 3,
  "updated": 1,
  "removed": 0,
  "message": "Added: 3, Updated: 1, Removed: 0"
}
```

### GET /sync/test-token

Run GitHub token diagnostics using the configured PAT.

**Permissions:** Requires `manage_options`.

**Response:** Diagnostic object returned by the GitHub client.

### POST /sync/test-repo

Test access to a specific GitHub repository.

**Permissions:** Requires `manage_options`.

**Body parameters:**
- `repo_url` (string, required)

**Response:** Repository diagnostic object on success. On failure, returns a WP REST error; error `data` may include `owner`, `repo`, and `sso_url`.

### POST /sync/setup

Setup a project and its category taxonomy terms.

**Permissions:** Requires `manage_categories`.
**Parameters:**
- `project_slug` (string, required): Project slug
- `project_name` (string, required): Project display name
- `category_slug` (string, required): Category slug (e.g., "wordpress-plugins")
- `category_name` (string, required): Category display name

**Response:**
```json
{
  "success": true,
  "category_term_id": 5,
  "category_slug": "wordpress-plugins",
  "project_term_id": 16,
  "project_slug": "my-project"
}
```

### GET /sync/status

Get sync status for a specific project.

**Permissions:** Requires `edit_posts`.

**Query parameters:**
- `project` (string, required): project term slug

**Response:**
```json
{
  "total_docs": 15,
  "synced_docs": 15,
  "project_slug": "my-plugin",
  "project_term": 15,
  "docs": [
    {
      "post_id": 125,
      "title": "Getting Started",
      "source_file": "docs/README.md",
      "sync_filesize": "1024",
      "sync_timestamp": "2025-12-01T10:00:00Z",
      "status": "synced"
    }
  ]
}
```
### POST /sync/doc

Sync a single documentation post from external sources.

**Permissions:** Requires `edit_posts`.
**Parameters:**
- `source_file` (string, required): Original source file path
- `title` (string, required): Documentation title
- `content` (string, required): Markdown content
- `project_term_id` (int, required): Project taxonomy term ID
- `filesize` (int, required): Size of source file in bytes
- `timestamp` (string, required): ISO 8601 timestamp of last modification
- `subpath` (array): Hierarchical path within project as array
- `excerpt` (string): Brief description
- `force` (boolean): Override existing content (default: false)

**Response:**
```json
{
  "success": true,
  "post_id": 125,
  "action": "created",
  "term_id": 20,
  "term_path": "wordpress-plugins/my-plugin/api"
}
```

### POST /sync/batch

Batch sync multiple documentation posts.

**Permissions:** Requires `edit_posts`.
**Parameters:**
- `docs` (array, required): Array of document objects (same structure as POST /sync/doc)

**Response:**
```json
{
  "total": 5,
  "created": 3,
  "updated": 2,
  "unchanged": 0,
  "failed": 0,
  "results": [
    {
      "success": true,
      "post_id": 125,
      "action": "created"
    }
  ]
}
```

## Error Responses

All endpoints return standard HTTP status codes:

- `200`: Success
- `201`: Created
- `400`: Bad Request (invalid parameters)
- `401`: Unauthorized
- `403`: Forbidden (insufficient permissions)
- `404`: Not Found
- `500`: Internal Server Error

Error response format:

This plugin uses WordPress REST error responses (see WP REST API). Shapes vary by error; for errors explicitly thrown by this plugin you typically receive:

```json
{
  "code": "not_found",
  "message": "Documentation not found",
  "data": {
    "status": 404
  }
}
```