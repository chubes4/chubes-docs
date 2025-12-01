# Chubes Docs

A WordPress plugin that provides REST API sync system and admin enhancements for chubes.net documentation.

## Quick Start

1. **Install the plugin** on your WordPress site with the Chubes theme
2. **Set up a project**:
   ```bash
   curl -X POST /wp-json/chubes/v1/sync/setup \
     -H "Content-Type: application/json" \
     -d '{
       "project_slug": "my-plugin",
       "project_name": "My WordPress Plugin",
       "category_slug": "wordpress-plugins",
       "category_name": "WordPress Plugins"
     }'
   ```
3. **Sync documentation**:
   ```bash
   curl -X POST /wp-json/chubes/v1/sync/doc \
     -H "Content-Type: application/json" \
     -d '{
       "source_file": "README.md",
       "title": "Getting Started",
       "content": "# Getting Started\n\nInstallation instructions...",
       "project_term_id": 123,
       "subpath": "guides"
     }'
   ```

## Features

- **REST API Endpoints**: Full CRUD operations for documentation posts and codebase taxonomy management
- **Enhanced Sync System**: Project-based sync with `project_term_id` and `subpath` parameters (v0.2.0+)
- **Codebase Integration**: GitHub and WordPress.org repository metadata tracking
- **Install Tracking**: Automatic fetching of active install counts from WordPress.org API
- **Markdown Processing**: Convert markdown content to HTML with internal link resolution
- **Related Posts**: Hierarchical codebase-aware related documentation display

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Chubes theme (required for full functionality)

## Installation

1. Download the latest release from the [releases page](https://github.com/chubes4/chubes-docs/releases)
2. Upload the plugin to your WordPress installation
3. Activate the plugin
4. The plugin requires the Chubes theme to be active for documentation features

## API Endpoints

The plugin provides REST API endpoints under the `chubes/v1` namespace:

### Documentation
- `GET /wp-json/chubes/v1/docs` - List documentation posts
- `POST /wp-json/chubes/v1/docs` - Create new documentation
- `GET /wp-json/chubes/v1/docs/{id}` - Get specific documentation
- `PUT /wp-json/chubes/v1/docs/{id}` - Update documentation
- `DELETE /wp-json/chubes/v1/docs/{id}` - Delete documentation

### Codebase Taxonomy
- `GET /wp-json/chubes/v1/codebase` - List codebase taxonomy terms
- `GET /wp-json/chubes/v1/codebase/tree` - Get hierarchical codebase tree
- `POST /wp-json/chubes/v1/codebase/resolve` - Resolve or create taxonomy path
- `GET /wp-json/chubes/v1/codebase/{id}` - Get specific taxonomy term
- `PUT /wp-json/chubes/v1/codebase/{id}` - Update taxonomy term

### Sync
- `POST /wp-json/chubes/v1/sync/setup` - Setup project and category
- `GET /wp-json/chubes/v1/sync/status` - Get sync status
- `POST /wp-json/chubes/v1/sync/doc` - Sync documentation from external sources
- `POST /wp-json/chubes/v1/sync/batch` - Batch sync multiple documents

## Development

### Building

```bash
./build.sh
```

This creates a production-ready ZIP file in the `build/` directory.

### Code Structure

- `inc/Api/` - REST API controllers and routes
- `inc/Core/` - Core plugin functionality (assets, breadcrumbs, codebase, documentation, rewrite rules)
- `inc/Fields/` - Admin interface fields and install tracking
- `inc/Sync/` - Markdown processing and sync management
- `inc/Templates/` - Frontend template enhancements

## License

GPL v2 or later

## Author

Chris Huber - https://chubes.net