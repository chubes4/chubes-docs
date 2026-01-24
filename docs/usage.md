# Usage Guide

## Documentation Post Type

The plugin provides a `documentation` custom post type for creating and managing documentation.

### Features

- Gutenberg editor support
- Markdown processing
- Project taxonomy for organization
- REST API access
- Archive and single views

### Creating Documentation

1. In WordPress admin, go to Documentation > Add New
2. Write your documentation content
3. Assign to appropriate project taxonomy terms
4. Publish

## Project Taxonomy

Organize documentation with the `project` hierarchical taxonomy.

### Structure

- wordpress-plugins
- wordpress-themes
- discord-bots
- php-libraries

### Usage

Assign taxonomy terms to documentation posts for categorization and filtering.

## REST API

For exact request/response shapes and permissions, see the [API Reference](api-reference.md).

### Endpoints (Summary)

All endpoints use the `chubes/v1` namespace.

#### Documentation
- `GET /wp-json/chubes/v1/docs` - List documentation posts
- `POST /wp-json/chubes/v1/docs` - Create new documentation
- `GET /wp-json/chubes/v1/docs/{id}` - Get specific documentation
- `PUT /wp-json/chubes/v1/docs/{id}` - Update documentation
- `DELETE /wp-json/chubes/v1/docs/{id}` - Delete documentation

#### Project Taxonomy
- `GET /wp-json/chubes/v1/project` - List project taxonomy terms
- `GET /wp-json/chubes/v1/project/tree` - Get hierarchical project tree
- `POST /wp-json/chubes/v1/project/resolve` - Resolve or create taxonomy path
- `GET /wp-json/chubes/v1/project/{id}` - Get specific taxonomy term
- `PUT /wp-json/chubes/v1/project/{id}` - Update taxonomy term

#### Sync Operations
- `POST /wp-json/chubes/v1/sync/setup` - Setup project + category terms
- `GET /wp-json/chubes/v1/sync/status` - Get sync status for a project
- `POST /wp-json/chubes/v1/sync/doc` - Sync a single document
- `POST /wp-json/chubes/v1/sync/batch` - Batch sync documents
- `POST /wp-json/chubes/v1/sync/all` - Manually sync GitHub docs for all projects
- `POST /wp-json/chubes/v1/sync/term/{id}` - Manually sync GitHub docs for a term
- `GET /wp-json/chubes/v1/sync/test-token` - GitHub token diagnostics
- `POST /wp-json/chubes/v1/sync/test-repo` - GitHub repo diagnostics (`repo_url`)
### Parameters

See [API Reference](api-reference.md) for complete parameter documentation.

#### Quick Reference
- `per_page` - Number of results per page (default: 10)
- `page` - Page number for pagination
- `codebase` - Filter by project taxonomy term slug
- `status` - Filter by post status (publish, draft, etc.)
- `search` - Search term for title/content

## Markdown Processing

Documentation content supports Markdown syntax via Parsedown.

### Supported Features

- Headers (# ## ###)
- Lists (- * numbered)
- Code blocks (```)
- Links and images
- Bold and italic text

## Sync System

### Components

- **MarkdownProcessor**: Converts Markdown to HTML with internal link resolution
- **SyncManager**: Handles external documentation synchronization and taxonomy management
- **RepositoryFields**: Manages GitHub/WordPress.org repository metadata and install tracking

### Sync Process

1. **Project Setup**: Use `/sync/setup` to create project taxonomy terms and categories
2. **Document Sync**: Use `/sync/doc` or `/sync/batch` to import documentation with proper taxonomy assignment
3. **Content Processing**: Markdown content is converted to HTML with internal link resolution
4. **Taxonomy Management**: Automatic creation of hierarchical taxonomy terms as needed
5. **Metadata Updates**: Repository information and install counts are automatically fetched and updated

### Sync Parameters

- `project_term_id`: ID of the project taxonomy term (required)
- `subpath`: Hierarchical path within the project as array (e.g., ["api", "endpoints"])
- `source_file`: Original source file path for reference (required)
- `title`: Documentation title (required)
- `content`: Markdown content (required)
- `filesize`: Size of source file in bytes (required)
- `timestamp`: ISO 8601 timestamp of last modification (required)
- `excerpt`: Brief description
- `force`: Override existing content (boolean, default: false)

## Install Tracking

The plugin tracks install counts for project terms that have a WordPress.org URL configured (returned as `meta.installs` on `GET /project/{id}`).
## Templates

The plugin provides custom templates for documentation display:

- Archive template for documentation lists
- Single template for individual docs
- Codebase card components
- Related posts functionality

## Development

### Hooks and Filters

This plugin uses standard WordPress hooks internally. Public hooks/filters are not currently documented here; rely on the codebase when extending.
### Components

- Api/Controllers/DocsController.php - REST API handling
- Fields/ - Metadata management
- Sync/ - Synchronization logic
- Templates/ - Display components

## Troubleshooting

### Common Issues

**Sync fails with "Project term not found"**
- Ensure you've called `/sync/setup` first to create the project taxonomy terms
- Verify the `project_term_id` matches the ID returned from setup
- Check that the project term exists in the project taxonomy

**Content not updating**
- Set `force: true` in your sync request to override existing content
- Verify the `timestamp` parameter is newer than the existing document's timestamp
- Check if the document hash has actually changed

**Invalid subpath errors**
- Ensure `subpath` is an array of strings, not a single string
- Avoid special characters in subpath segments
- Keep subpath hierarchies shallow (max 3-4 levels)

**API authentication errors**
- Ensure you're using proper WordPress authentication (cookies or application passwords)
- Verify user has appropriate permissions (`edit_posts` for `/sync/doc` and `/sync/batch`; `manage_options` for manual GitHub sync and diagnostics)
- Check that the REST API is enabled on your WordPress installation

### Debug Mode

Enable WordPress debug logging to troubleshoot issues:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for detailed error information and API request logs.

### Checking Sync Status

Monitor sync operations:

```bash
# Get sync status for a project
curl /wp-json/chubes/v1/sync/status?project=my-plugin

# List recent documentation
curl /wp-json/chubes/v1/docs?codebase=my-plugin&per_page=10
```