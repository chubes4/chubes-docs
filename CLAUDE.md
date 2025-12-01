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
│   │   └── Routes.php      # Route registration
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
│   │   ├── MarkdownProcessor.php # Markdown to HTML conversion
│   │   └── SyncManager.php # External data sync
│   └── Templates/          # Frontend enhancements
│       └── RelatedPosts.php # Related documentation logic
└── assets/                 # Frontend assets
    ├── css/
    │   ├── documentation.css
    │   └── related-posts.css
    └── (other frontend assets)
```

## Key Systems

### REST API Layer
- **Controllers**: Handle CRUD operations for docs, codebase taxonomy, and sync operations
- **Routes**: Centralized route registration under `chubes/v1` namespace
- **Authentication**: WordPress nonce verification and capability checks

### Documentation Management
- **Post Type Integration**: Extends theme's `documentation` post type
- **Markdown Processing**: Converts markdown to HTML with internal link resolution
- **Sync Capabilities**: External documentation source synchronization

### Codebase Integration
- **Taxonomy Management**: Extends theme's `codebase` taxonomy functionality
- **Repository Metadata**: GitHub and WordPress.org repository information
- **Install Tracking**: Automatic WordPress.org install count fetching

### Admin Interface
- **Repository Fields**: Meta boxes for repository URLs, versions, and metadata
- **Install Statistics**: Display of active install counts and trends
- **Sync Management**: Interface for external data synchronization

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

## API Endpoints

All endpoints use the `chubes/v1` namespace:

### Documentation
- `GET /docs` - List documentation posts
- `POST /docs` - Create documentation
- `GET /docs/{id}` - Get specific documentation
- `PUT /docs/{id}` - Update documentation
- `DELETE /docs/{id}` - Delete documentation

### Codebase
- `GET /codebase` - List codebase taxonomy terms
- `POST /codebase` - Create codebase terms

### Sync
- `POST /sync/doc` - Sync documentation from external sources

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