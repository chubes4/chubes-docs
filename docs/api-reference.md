# API Reference

This document provides comprehensive documentation for all REST API endpoints provided by the Chubes Docs plugin.

For practical usage examples and workflow guidance, see the [Usage Guide](usage.md).

## Base URL

All endpoints use the base URL: `/wp-json/chubes/v1/`

## Authentication

All endpoints require WordPress authentication. Use WordPress cookies or application passwords for API access.

## Documentation Endpoints

### GET /docs

List documentation posts with optional filtering.

**Parameters:**
- `per_page` (int): Number of results per page (default: 10, max: 100)
- `page` (int): Page number for pagination (default: 1)
- `codebase` (string): Filter by codebase taxonomy term slug
- `status` (string): Filter by post status (publish, draft, pending, etc.)
- `search` (string): Search term for title or content

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "title": "Getting Started",
      "content": "<p>Documentation content...</p>",
      "excerpt": "Brief description",
      "status": "publish",
      "codebase": ["wordpress-plugins", "my-plugin"],
      "meta": {
        "source_file": "README.md",
        "last_sync": "2025-12-01T10:00:00Z"
      },
      "date": "2025-12-01T09:00:00Z"
    }
  ],
  "total": 25,
  "pages": 3,
  "current_page": 1
}
```

### POST /docs

Create a new documentation post.

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

**Response:** Single documentation object as shown in GET /docs.

### PUT /docs/{id}

Update an existing documentation post.

**Parameters:** Same as POST /docs, all optional except `id`.

**Response:** Updated documentation object.

### DELETE /docs/{id}

Delete a documentation post.

**Parameters:**
- `force` (boolean): Permanently delete instead of moving to trash (default: false)

**Response:**
```json
{
  "deleted": true,
  "id": 123
}
```

## Codebase Taxonomy Endpoints

### GET /codebase

List codebase taxonomy terms.

**Parameters:**
- `parent` (int): Filter by parent term ID
- `hide_empty` (int): Hide terms with no associated posts (0 or 1, default: 0)

**Response:**
```json
{
  "data": [
    {
      "id": 5,
      "name": "WordPress Plugins",
      "slug": "wordpress-plugins",
      "description": "",
      "parent": 0,
      "count": 15
    }
  ]
}
```

### GET /codebase/tree

Get the complete hierarchical codebase tree.

**Response:**
```json
{
  "tree": [
    {
      "id": 5,
      "name": "WordPress Plugins",
      "slug": "wordpress-plugins",
      "children": [
        {
          "id": 12,
          "name": "My Plugin",
          "slug": "my-plugin",
          "children": []
        }
      ]
    }
  ]
}
```

### POST /codebase/resolve

Resolve or create a taxonomy path.

**Parameters:**
- `path` (string, required): Taxonomy path (e.g., "wordpress-plugins/my-plugin/api")
- `create_missing` (boolean): Create missing terms in the path (default: true)
- `project_meta` (object): Metadata for the project term

**Response:**
```json
{
  "term_id": 15,
  "path": "wordpress-plugins/my-plugin/api",
  "created": ["api"],
  "terms": [
    {"id": 5, "slug": "wordpress-plugins"},
    {"id": 12, "slug": "my-plugin"},
    {"id": 15, "slug": "api"}
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

**Parameters:**
- `name` (string): Term name
- `description` (string): Term description
- `meta` (object): Additional metadata

**Response:** Updated term object.

## Sync Endpoints

### POST /sync/setup

Setup a project and its category taxonomy terms.

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

Get synchronization status.

**Parameters:**
- `project` (string): Filter by project slug

**Response:**
```json
{
  "last_sync": "2025-12-01T10:00:00Z",
  "total_docs": 25,
  "synced_docs": 23,
  "projects": ["my-project", "another-project"]
}
```

### POST /sync/doc

Sync a single documentation post from external sources.

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
```json
{
  "code": "invalid_param",
  "message": "Invalid parameter: title",
  "data": {
    "param": "title"
  }
}
```