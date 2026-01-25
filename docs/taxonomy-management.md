# Taxonomy Management

This guide covers the `project` taxonomy system used to organize documentation in Chubes Docs.

For API endpoint details, see the [API Reference](api-reference.md).

## Overview

The `project` taxonomy is a hierarchical system for categorizing documentation by project type and structure. It supports automatic term creation, path resolution, and repository metadata integration.

## Taxonomy Structure

### Hierarchy Levels

```
project/
├── wordpress-plugins/
│   ├── my-plugin/
│   │   ├── api/
│   │   ├── guides/
│   │   └── examples/
│   └── another-plugin/
├── wordpress-themes/
│   └── my-theme/
├── php-libraries/
└── discord-bots/
```

- **Category Terms**: Top-level groupings (wordpress-plugins, wordpress-themes, etc.)
- **Project Terms**: Specific projects within categories
- **Subpath Terms**: Hierarchical organization within projects

## Managing Taxonomy Terms

### Creating Terms via API

The REST API does not provide an endpoint to create arbitrary project terms directly (there is no `POST /project`). Use `POST /project/resolve` with `create_missing: true` to create missing segments in a path.

#### Path Resolution

Automatically create hierarchical paths:

```bash
curl -X POST /wp-json/chubes/v1/project/resolve \
  -H "Content-Type: application/json" \
  -d '{
    "path": ["wordpress-plugins", "my-plugin", "api", "endpoints"],
    "create_missing": true,
    "project_meta": {
      "github_url": "https://github.com/user/my-plugin",
      "wp_url": "https://wordpress.org/plugins/my-plugin"
    }
  }'
```

This creates all missing terms in the hierarchy.

Permissions note: `POST /project/resolve` is public when `create_missing` is `false`, and requires `manage_categories` when `create_missing` is `true`.
### Updating Terms

```bash
curl -X PUT /wp-json/chubes/v1/project/{term_id} \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Plugin Name",
    "description": "New description",
    "meta": {
      "github_url": "https://github.com/user/repo",
      "wp_url": "https://wordpress.org/plugins/repo"
    }
  }'
```

> `PUT /project/{id}` only updates `name`, `description`, `meta.github_url`, and `meta.wp_url`.
### Listing Terms

```bash
# Get all terms
curl /wp-json/chubes/v1/project

# Get hierarchical tree
curl /wp-json/chubes/v1/project/tree

# Filter by parent
curl /wp-json/chubes/v1/project?parent=5

# Hide empty terms
curl "/wp-json/chubes/v1/project?hide_empty=true"
```

## Repository Metadata

### Repository Metadata

The term update endpoint supports setting two URLs:

- `meta.github_url` is stored as `project_github_url`
- `meta.wp_url` is stored as `project_wp_url`

The term detail endpoint (`GET /project/{id}`) also returns:

- `meta.installs`
- `repository_info` (the computed repository info array returned by `chubes_get_repository_info()`)

### Automatic Fetching

The plugin automatically fetches metadata from APIs:

- **WordPress.org API**: Install counts, ratings, last updated
- **GitHub API**: Stars, forks, description, language

### Manual Metadata Updates

`PUT /wp-json/chubes/v1/project/{id}` only persists:

- `meta.github_url` → `project_github_url`
- `meta.wp_url` → `project_wp_url`

```bash
curl -X PUT /wp-json/chubes/v1/project/{project_term_id} \
  -H "Content-Type: application/json" \
  -d '{
    "meta": {
      "github_url": "https://github.com/user/repo",
      "wp_url": "https://wordpress.org/plugins/repo"
    }
  }'
```

## Integration with Documentation

### Assigning Taxonomy to Posts

When creating documentation via API:

```bash
curl -X POST /wp-json/chubes/v1/docs \
  -H "Content-Type: application/json" \
  -d '{
    "title": "API Documentation",
    "content": "# API Docs...",
    "codebase_path": ["wordpress-plugins", "my-plugin", "api"]
  }'
```

The `codebase_path` parameter automatically resolves to the appropriate taxonomy terms.

### Sync System Integration

The sync system uses taxonomy paths for organization:

```bash
curl -X POST /wp-json/chubes/v1/sync/doc \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Installation Guide",
    "content": "# Installation...",
    "project_term_id": 123,
    "filesize": 2048,
    "timestamp": "2025-12-01T10:00:00Z",
    "subpath": ["guides", "installation"]
  }'
```

## URL Structure

### Taxonomy URLs

Terms generate URLs following the pattern:
- `/project/wordpress-plugins/` - Category archive
- `/project/wordpress-plugins/my-plugin/` - Project archive
- `/project/wordpress-plugins/my-plugin/api/` - Subpath archive

### Documentation URLs

Documentation posts use hierarchical URLs:
- `/docs/wordpress-plugins/my-plugin/readme/` - Project README
- `/docs/wordpress-plugins/my-plugin/api/endpoints/` - API docs

## Admin Interface

### Repository Fields

The admin interface provides meta boxes for:

- **GitHub URL**: Repository link
- **WordPress.org URL**: Plugin/theme directory link
- **Version**: Current version string
- **Install Count**: Display of active installs (read-only, auto-updated)

### Install Tracking

The `InstallTracker` class automatically:

- Fetches install counts from WordPress.org API
- Updates term metadata daily
- Displays statistics in admin interface
- Provides trending data

## Best Practices

### Naming Conventions

- Use lowercase slugs with hyphens: `my-wordpress-plugin`
- Keep category names consistent: `wordpress-plugins`, `php-libraries`
- Use descriptive project names: `advanced-custom-fields`, not `acf`

### Hierarchy Organization

- **Categories**: Group by technology/platform
- **Projects**: One term per repository
- **Subpaths**: Logical content organization
  - `api/` - API reference
  - `docs/` - Documentation
  - `examples/` - Code examples
  - `changelog/` - Version history

### Metadata Management

- Always include repository URLs for auto-fetching
- Keep version numbers current
- Use consistent metadata field names
- Regularly update project information

### Performance Considerations

- Limit deep hierarchies (max 4-5 levels)
- Use `hide_empty=1` for display queries
- Cache taxonomy queries when possible
- Batch taxonomy operations

## Troubleshooting

### Common Issues

**"Term not found"**
- Check slug spelling and case sensitivity
- Verify term exists with `GET /project/{id}`

**"Path resolution failed"**
- Ensure parent terms exist
- Check for special characters in slugs
- Verify hierarchy doesn't create loops

**"Metadata not updating"**
- Confirm repository URLs are valid
- Check API rate limits
- Verify term has proper permissions

### Debug Queries

Enable taxonomy debugging:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check debug.log for taxonomy operations
```

### Cleanup Operations

Remove unused terms:

```bash
# Find empty terms
curl /wp-json/chubes/v1/project?hide_empty=0

# Manual cleanup (use WordPress admin or custom script)
```

## Advanced Usage

### Custom Metadata Fields

Add custom fields to taxonomy terms:

```php
// Via PHP
update_term_meta($term_id, 'custom_field', 'value');

// Via API
curl -X PUT /wp-json/chubes/v1/codebase/{id} \
  -d '{"meta": {"custom_field": "value"}}'
```

### Taxonomy Queries

Complex queries using WordPress functions:

```php
$args = array(
  'taxonomy' => 'project',
  'parent' => 0, // Top level only
  'hide_empty' => false,
  'meta_query' => array(
    array(
      'key' => 'github_url',
      'value' => 'github.com',
      'compare' => 'LIKE'
    )
  )
);
$terms = get_terms($args);
```

### Bulk Operations

Update multiple terms:

```bash
# Get all project terms
curl /wp-json/chubes/v1/project?hide_empty=0

# Batch update metadata
# (Implement custom endpoint or use multiple PUT requests)
```