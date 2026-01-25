# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Chubes Docs is a WordPress plugin that provides REST API sync system and admin enhancements for documentation management on chubes.net. It integrates with the Chubes theme to provide comprehensive documentation features.

## Architecture

The plugin follows PSR-4 autoloading with organized component structure:

```
chubes-docs/
├── chubes-docs.php          # Main plugin file
├── composer.json            # Dependencies and autoloading
├── inc/                     # Core functionality
│   ├── Api/                # REST API layer
│   │   ├── Controllers/    # API endpoint handlers
│   │   │   ├── CodebaseController.php
│   │   │   ├── DocsController.php
│   │   │   └── SyncController.php
│   │   ├── Routes.php      # Route registration
│   │   └── Abilities.php   # WP Abilities API integration
│   ├── Core/               # Plugin core systems
│   │   ├── Assets.php      # Asset management
│   │   ├── Breadcrumbs.php # Breadcrumb generation
│   │   ├── Codebase.php    # Codebase taxonomy management
│   │   ├── Documentation.php # Documentation post type handling
│   │   └── RewriteRules.php # URL routing
│   ├── Fields/             # Admin interface components
│   │   ├── InstallTracker.php # WordPress.org install stats
│   │   └── RepositoryFields.php # Repository metadata
│   ├── Sync/               # Data synchronization
│   │   ├── CronSync.php    # Automated scheduling
│   │   ├── GitHubClient.php # GitHub API client
│   │   ├── MarkdownProcessor.php # Markdown to HTML conversion
│   │   ├── RepoSync.php    # Repository synchronization
│   │   ├── SyncManager.php # External data sync
│   │   └── SyncNotifier.php # Email notifications
│   ├── Templates/          # Frontend enhancements
│   │   ├── Archive.php     # Archive page enhancements
│   │   ├── CodebaseCard.php # Project card component
│   │   ├── Homepage.php    # Homepage integration
│   │   └── RelatedPosts.php # Related documentation logic
│   ├── Admin/              # Admin interface
│   │   ├── CodebaseColumns.php # Codebase taxonomy admin columns
│   │   ├── DocumentationColumns.php # Documentation post type admin columns
│   │   └── SettingsPage.php # Plugin settings page
│   └── WPCLI/              # WP-CLI commands
│       ├── CLI.php         # Command registration
│       └── Commands/       # Command implementations
│           ├── CodebaseEnsureCommand.php
│           └── DocsSyncCommand.php
└── assets/                 # Frontend assets
    ├── css/
    │   ├── archives.css
    │   └── related-posts.css
    └── js/
        └── admin-sync.js   # Admin sync interface
```

## Key Systems

### REST API Layer (v0.2.1)
- **Controllers**: Handle CRUD operations for docs, project taxonomy, and sync operations
- **Routes**: Centralized route registration under `chubes/v1` namespace with enhanced sync endpoints
- **Authentication**: WordPress nonce verification and capability checks
- **New Endpoints**: `/sync/setup` for project initialization, enhanced `/sync/doc` with `project_term_id`/`subpath` parameters

### Documentation Management
- **Post Type Integration**: Extends theme's `documentation` post type with hierarchical URL routing
- **Markdown Processing**: Converts markdown to HTML with internal link resolution and taxonomy-aware URL generation
- **Sync Capabilities**: External documentation source synchronization with automatic taxonomy term creation
- **Admin Columns**: Enhanced documentation list screen with relevant metadata display

### Codebase Integration
- **Taxonomy Management**: Extends theme's `codebase` taxonomy with hierarchical path resolution and automatic term creation
- **Repository Metadata**: GitHub and WordPress.org repository information with auto-fetching capabilities
- **Install Tracking**: Automatic WordPress.org install count fetching with daily updates
- **Admin Columns**: Enhanced taxonomy list screen with GitHub URLs, install counts, and sync status

### Admin Interface
- **Repository Fields**: Meta boxes for repository URLs, versions, and metadata with validation
- **Install Statistics**: Display of active install counts and trends with historical data
- **Sync Management**: Interface for external data synchronization with status monitoring
- **Settings Page**: GitHub PAT configuration, connection diagnostics, and sync management

### GitHub Integration
- **GitHub Client**: Full GitHub API integration for repository operations
- **Repo Sync**: Automated documentation sync from GitHub repositories
- **Connection Testing**: Diagnostic tools for token and repository validation
- **Cron Sync**: Scheduled automated synchronization with configurable intervals

### WP-CLI Commands
- **Project Ensure**: Ensure project taxonomy terms exist and are properly configured
- **Docs Sync**: Manually trigger documentation synchronization from command line

### WP Abilities API
- **Sync Documentation**: Individual repository sync ability
- **Sync All Documentation**: Batch sync multiple repositories ability

## Development Commands

```bash
# Build production package
./build.sh

# Install dependencies
composer install

# Install production dependencies only
composer install --no-dev
```

## Build Process

The build script creates a production-ready WordPress plugin package:
1. Runs `composer install --no-dev` for production dependencies
2. Copies files using `.buildignore` exclusions
3. Validates required files are present
4. Creates ZIP file in `build/` directory
5. Restores development dependencies

## Development Workflow

### Local Development Setup
1. Clone repository and run `composer install`
2. Use testing-grounds.local (multisite WordPress installation)
3. Activate plugin and test with Chubes theme
4. Use `./build.sh` for production builds

### Code Standards
- PSR-4 autoloading for all classes
- WordPress coding standards for PHP
- Single responsibility principle for class design
- Comprehensive error handling and input validation

### Testing Strategy
- Manual testing on multisite WordPress installation
- API endpoint testing with curl/Postman
- Integration testing with Chubes theme
- Build validation with production ZIP creation

## API Endpoints

All endpoints use the `chubes/v1` namespace:

### Documentation
- `GET /docs` - List documentation posts (with filters: codebase, status, per_page, page, search)
- `POST /docs` - Create documentation (with title, content, excerpt, status, codebase_path, meta)
- `GET /docs/{id}` - Get specific documentation
- `PUT /docs/{id}` - Update documentation
- `DELETE /docs/{id}` - Delete documentation (with force parameter)

### Codebase
- `GET /codebase` - List project taxonomy terms (with parent, hide_empty)
- `GET /codebase/tree` - Get hierarchical codebase tree
- `POST /codebase/resolve` - Resolve or create taxonomy path (with path, create_missing, project_meta)
- `GET /codebase/{id}` - Get specific taxonomy term
- `PUT /codebase/{id}` - Update taxonomy term (name, description, meta)

### Sync
- `POST /sync/setup` - Setup project and category (project_slug, project_name, category_slug, category_name)
- `GET /sync/status` - Get sync status (with project filter)
- `POST /sync/doc` - Sync documentation from external sources (source_file, title, content, project_term_id, filesize, timestamp, subpath, excerpt, force)
- `POST /sync/batch` - Batch sync multiple documents
- `POST /sync/all` - Manually sync GitHub docs for all codebases with GitHub URLs
- `POST /sync/term/{id}` - Manually sync GitHub docs for a single codebase term
- `GET /sync/test-token` - GitHub token diagnostics
- `POST /sync/test-repo` - GitHub repo diagnostics (repo_url)

## Dependencies

- **erusev/parsedown**: Markdown parsing and HTML conversion
- **PHP**: 8.0+ required
- **WordPress**: 6.0+ required

## Integration Points

### Theme Integration
- Requires Chubes theme for full functionality
- Extends theme's post types and taxonomies
- Provides enhanced templates and breadcrumb logic

### External APIs
- **WordPress.org API**: Install count tracking
- **GitHub API**: Repository metadata fetching
- **Custom sync endpoints**: Documentation source synchronization

## Security Considerations

- All API endpoints use WordPress nonce verification
- Capability checks for admin operations
- Input sanitization and validation
- Secure API key storage for external services

## Development Guidelines

- Follow PSR-4 autoloading standards
- Use WordPress coding standards for PHP
- Implement proper error handling for API calls
- Validate all external data before processing
- Use WordPress hooks and filters for extensibility

## Architectural Principles

- **Single Responsibility**: Each class handles one specific concern
- **Modular Design**: Clear separation between API, Core, Fields, Sync, and Templates
- **WordPress Integration**: Leverages WordPress APIs and conventions
- **Performance**: Conditional loading and efficient data processing

## Contact

- Owner: Chris Huber — https://chubes.net