# Chubes Docs

A WordPress plugin that provides REST API sync system and admin enhancements for chubes.net documentation.

## Quick Start

See the docs in [`docs/`](docs/) for endpoint details and sync workflows:

- [API Reference](docs/api-reference.md)
- [Sync Guide](docs/sync-guide.md)
- [GitHub Sync Diagnostics](docs/github-sync-diagnostics.md)

> Note: `POST /sync/doc` requires `filesize` (int) and `timestamp` (string), and `subpath` is an array of strings.

```bash
curl -X POST /wp-json/chubes/v1/sync/doc \
  -H "Content-Type: application/json" \
  -d '{
    "source_file": "README.md",
    "title": "Getting Started",
    "content": "# Getting Started\n\nInstallation instructions...",
    "project_term_id": 123,
    "filesize": 1024,
    "timestamp": "2026-01-11T00:00:00Z",
    "subpath": ["guides"]
  }'
```
## Features

- **REST API Endpoints**: Full CRUD operations for documentation posts and codebase taxonomy management
- **Enhanced Sync System**: Project-based sync with `project_term_id` and `subpath` parameters (v0.2.0+)
- **GitHub Integration**: Full GitHub API integration for automated documentation sync from repositories
- **Codebase Integration**: GitHub and WordPress.org repository metadata tracking with admin columns
- **Install Tracking**: Automatic fetching of active install counts from WordPress.org API with daily updates
- **Markdown Processing**: Convert markdown content to HTML with internal link resolution
- **Related Posts**: Hierarchical codebase-aware related documentation display
- **Cron Sync**: Scheduled automated synchronization with configurable intervals (hourly/twice daily/daily)
- **Admin Interface**: GitHub PAT configuration, connection diagnostics, and sync management UI
- **WP-CLI Commands**: Command-line tools for codebase management and documentation sync
- **WP Abilities API**: AI agent capabilities for documentation synchronization
- **Sync Notifications**: Email alerts for sync completion and failures

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
- `POST /wp-json/chubes/v1/sync/setup` - Setup project + category terms
- `GET /wp-json/chubes/v1/sync/status` - Get sync status for a project (requires `project`)
- `POST /wp-json/chubes/v1/sync/doc` - Sync a single document
- `POST /wp-json/chubes/v1/sync/batch` - Batch sync documents
- `POST /wp-json/chubes/v1/sync/all` - Manually sync GitHub docs for all codebases with GitHub URLs
- `POST /wp-json/chubes/v1/sync/term/{id}` - Manually sync GitHub docs for a single codebase term
- `GET /wp-json/chubes/v1/sync/test-token` - GitHub token diagnostics
- `POST /wp-json/chubes/v1/sync/test-repo` - GitHub repo diagnostics (`repo_url`)

### WP-CLI Commands
- `wp chubes codebase ensure` - Ensure codebase taxonomy terms exist and are properly configured
- `wp chubes docs sync` - Manually trigger documentation synchronization

### WP Abilities API
- `chubes/sync-docs` - Sync a single codebase term from GitHub
- `chubes/sync-docs-batch` - Sync multiple codebase terms from GitHub
## Development

### Building

```bash
./build.sh
```

This creates a production-ready ZIP file in the `build/` directory.

### Code Structure

- `inc/Api/` - REST API controllers, routes, and WP Abilities API integration
- `inc/Core/` - Core plugin functionality (assets, breadcrumbs, codebase, documentation, rewrite rules)
- `inc/Fields/` - Admin interface fields and install tracking
- `inc/Sync/` - Markdown processing, GitHub client, cron sync, repo sync, and notifications
- `inc/Templates/` - Frontend template enhancements (archive, codebase cards, homepage, related posts)
- `inc/Admin/` - Admin interface components (settings page, admin columns)
- `inc/WPCLI/` - WP-CLI commands for codebase management and documentation sync
- `assets/css/` - Frontend stylesheets (archives, related posts)
- `assets/js/` - Admin JavaScript (sync interface)

## License

GPL v2 or later

## Author

Chris Huber - https://chubes.net