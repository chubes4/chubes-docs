# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.6.0] - 2026-01-24

### Added
- Add project_type taxonomy for explicit project categorization (wordpress-plugins, wordpress-themes, cli)
- Add TaxonomyMigrateCommand for automated data migration from codebase to project taxonomy

### Changed
- Rename codebase taxonomy to project, update all references and API endpoints

## [0.5.6] - 2026-01-23

### Changed
- Related posts section uses semantic article.related-post-item structure instead of doc-card classes
- Project link text changed from 'Home' to 'All ... Docs' for clarity

## [0.5.5] - 2026-01-23

- Added WP-CLI 'chubes codebase tree' command to display taxonomy hierarchy
- Improved logging in 'chubes codebase ensure' command

## [0.5.4] - 2026-01-23

### Added
- Add documentation search bar for archive pages (WIP)

### Fixed
- Fix resolve_path to search by name instead of slug to prevent duplicate taxonomy terms

- Add filter to suppress duplicate archive description on codebase project pages
- Convert EM units to REM/CSS variables in archives and related-posts CSS

## [0.5.3] - 2026-01-23

- Update documentation CPT description to mention GitHub sync

## [0.5.2] - 2026-01-23

### Added
- Sync GitHub repository description to codebase term description during sync

## [0.5.1] - 2026-01-23

### Fixed
- Always process markdown for synced GitHub content, fixing formatting issues where content displayed as raw text

## [0.5.0] - 2026-01-23

### Added
- Project info card component for codebase term archives

### Changed
- Migrated markdown library from erusev/parsedown to league/commonmark ^2.5 for improved formatting support
- Archive styling: centered card stats and updated label from Guide/Guides to Doc/Docs

## [0.4.1] - 2026-01-20

- Changed: replaced hardcoded colors with CSS design system variables

## [0.4.0] - 2026-01-20

- Relocated Abilities API to inc/Abilities/ directory with new chubes/get-codebase-tree ability for hierarchy inspection

## [0.3.3] - 2026-01-20

- Fixed term section styling - card wrapper for nested sections, properly centered view all button

## [0.3.2] - 2026-01-20

- Improved term section visual hierarchy with header borders and centered View all links

## [0.3.1] - 2026-01-20

### Fixed
- **Redundant "View all" links**: Conditionally render "View all →" link in archive term sections only when the term has direct posts assigned, preventing redundant navigation for terms that only contain children with posts

## [0.2.8] - 2026-01-03

### Added
- **REST API Sync Endpoints**: New authenticated endpoints for all sync operations (`sync/all`, `sync/term`, `test-token`, `test-repo`).
- **Unified Permission Callbacks**: Centralized REST API capability checks for admin operations.

### Changed
- **AJAX to REST Migration**: Completely replaced legacy WordPress AJAX handlers with modern REST API endpoints for manual sync operations.
- **Architectural Refactor**: Consolidated sync logic into `SyncController`, reducing codebase complexity.
- **Improved Asset Management**: Centralized admin JavaScript enqueuing in `inc/Core/Assets.php` with proper screen targeting.
- **Settings UI Cleanup**: Streamlined the GitHub connection diagnostic interface.

### Removed
- **Legacy AJAX Handler**: Deleted `inc/Admin/SyncAjax.php` and its associated hooks.

## [0.2.7] - 2026-01-02

### Changed
- **Robust Error Handling in Admin UI**: Improved `admin-sync.js` to gracefully handle empty or malformed API responses during repository connectivity tests.
- **GitHub Repository Parsing**: Enhanced `GitHubClient` to properly handle and strip `.git` extensions from repository URLs, ensuring consistent API endpoint construction.

## [0.2.6] - 2026-01-02

### Added
- **GitHub Connection Diagnostics**: New tools on the settings page to test the GitHub Personal Access Token and verify API permissions
- **Individual Repository Testing**: Ability to test connectivity and permissions for specific repository URLs directly from the settings page
- **On-demand Term Sync**: Added "Sync Now" and "Test Connection" buttons directly to the codebase term edit screen for immediate manual synchronization and troubleshooting

### Changed
- **Enhanced AJAX Sync Interface**: Improved admin-sync.js with modular event listeners and detailed diagnostic reporting
- **Improved GitHub API Diagnostics**: `GitHubClient` now returns detailed diagnostic info including OAuth scopes, organization visibility, SAML SSO enforcement, and rate limit status
- **Stricter GitHub API Error Handling**: Enhanced detection of SAML SSO requirements and missing commit SHAs with descriptive error messages
- **Dynamic Asset Versioning**: Switched `admin-sync.js` to use `filemtime` for versioning to ensure immediate cache busting on updates

### Fixed
- **Improved Error Reporting in RepoSync**: Now correctly captures and displays specific GitHub API error messages when sync fails
- **Admin Script Loading**: Restricted `admin-sync.js` to only load on documentation settings and codebase edit screens
- **DOMContentLoaded logic**: Updated admin-sync.js to use standard DOMContentLoaded listener for better reliability

## [0.2.5] - 2026-01-02

### Fixed
- **Meta key consistency** in `Codebase::get_github_url()` and `Codebase::get_wp_url()` to use `codebase_` prefix, aligning with the rest of the plugin's metadata handling

## [0.2.4] - 2025-12-23

### Fixed
- **Centered project and card statistics** in archive templates using `justify-content: center;`
- **Removed redundant margin-bottom** from `.project-stats` and `.card-stats` for cleaner layout

### Technical Details
- Updated `.project-stats` and `.card-stats` flexbox styling in `assets/css/archives.css`

## [0.2.1] - 2025-12-01

### Added
- **New `/sync/setup` API endpoint** for automated project and category creation with proper hierarchy
- **Enhanced sync system** with improved term handling using `project_term_id` and `subpath` parameters
- **Complete rewrite of URL routing** for hierarchical `/docs/` URLs with better path resolution
- **Enhanced path resolution** in `Codebase::resolve_path()` with term creation capabilities and better error handling
- **New documentation files**: `docs/troubleshooting.md`, `docs/migration-guide.md`, `docs/development.md` with comprehensive guides
- **CLAUDE.md** file with AI agent collaboration instructions

### Changed
- **Refactored SyncController** to use new term structure instead of `codebase_path` arrays
- **Updated API routes** to support new sync parameters and project setup functionality
- **Improved RewriteRules** system for better hierarchical documentation URL handling
- **Enhanced SyncManager** with better term resolution and subpath handling
- **Updated all documentation** to reflect v0.2.1 API changes and new parameter formats

### Technical Details
- Added `setup_project()` method to SyncController for automated taxonomy setup
- Refactored `sync_post()` method to use `project_term_id` and `subpath` parameters
- Complete rewrite of `RewriteRules::resolve_docs_path()` for better URL parsing
- Enhanced `Codebase::resolve_path()` with creation capabilities and improved error handling
- Added `resolve_subpath()` private method to SyncManager for nested term creation
- Updated API parameter validation for new required fields (`filesize`, `timestamp`)

### Fixed
- Improved error handling in sync operations with better validation
- Enhanced term creation logic to prevent duplicate terms and maintain proper hierarchy
- Better URL routing for nested documentation structures
- Updated API examples throughout documentation to use current parameter formats

## [0.2.3] - 2025-12-05

### Added
- **Complete GitHub integration system** with automated repository synchronization
- **Admin settings page** (`/wp-admin/edit.php?post_type=documentation&page=chubes-docs-settings`) for GitHub PAT configuration and sync management
- **Enhanced codebase taxonomy columns** showing GitHub URLs, install counts, and sync status
- **AJAX-powered sync operations** with real-time status updates in admin interface
- **Cron-based automated sync** with configurable intervals (hourly/twice daily/daily)
- **Sync notification system** with email alerts for sync completion and failures
- **Admin JavaScript enhancements** (`admin-sync.js`) for improved user experience

### Changed
- **Enhanced RepositoryFields** with sync status display and improved form handling
- **Updated Archive template** with better project statistics rendering
- **Refined core classes** (Codebase, Documentation, RewriteRules) for GitHub integration
- **Removed unreleased 0.3.0 section** from changelog to align with current versioning

### Technical Details
- Added `inc/Admin/` namespace with `SettingsPage`, `CodebaseColumns`, and `SyncAjax` classes
- Added `inc/Sync/` namespace with `CronSync`, `GitHubClient`, `RepoSync`, and `SyncNotifier` classes
- Implemented GitHub API client for repository data fetching and file tree operations
- Added automated taxonomy term creation for hierarchical documentation organization
- Enhanced error handling and status tracking throughout sync operations

### Fixed
- Improved admin interface responsiveness and user feedback
- Better error handling in GitHub API communications
- Enhanced validation for repository URLs and sync parameters

## [0.2.2] - 2025-12-01

### Added
- **Enhanced sync validation** with `filesize` and `timestamp` parameters for improved change detection
- **New API reference documentation** (`docs/api-reference.md`) with comprehensive endpoint documentation
- **Development guide** (`docs/development.md`) with setup and contribution guidelines
- **Migration guide** (`docs/migration-guide.md`) for upgrading from previous versions
- **Sync guide** (`docs/sync-guide.md`) with detailed synchronization workflows
- **Taxonomy management guide** (`docs/taxonomy-management.md`) for codebase organization
- **Template integration guide** (`docs/template-integration.md`) for theme customization
- **Troubleshooting guide** (`docs/troubleshooting.md`) for common issues and solutions
- **Quick start section** in README.md with practical setup examples

### Changed
- **Replaced hash-based sync detection** with filesize and timestamp validation for better performance
- **Updated sync endpoints** to require `filesize` and `timestamp` parameters
- **Enhanced API parameter validation** with stricter requirements for sync operations
- **Improved documentation structure** with modular guides and comprehensive references

### Technical Details
- Modified `SyncManager::sync_post()` to accept `filesize` and `timestamp` parameters
- Updated `SyncController` to validate and pass new sync parameters
- Refactored change detection logic from content hashing to metadata comparison
- Added new API route parameters for enhanced sync validation

## [0.2.0] - 2025-11-30

### Added
- **Complete plugin architecture refactoring** with new Core classes for modular functionality
- **Documentation custom post type** with full Gutenberg support and public archives
- **Codebase taxonomy management** system with hierarchical term resolution
- **Homepage documentation column** integration for theme homepage grid
- **Archive and CodebaseCard template enhancements** for improved frontend display
- **New REST API endpoints**: codebase tree, path resolution, sync status, and batch operations
- **Global helper functions** for theme integration (`chubes_get_repository_info`, `chubes_generate_content_type_url`)
- **New CSS assets** (`archives.css`) for styling enhancements
- **Comprehensive development documentation** (CLAUDE.md, updated README.md)

### Changed
- **Refactored API controllers** to use new Core architecture instead of global functions
- **Updated initialization system** with proper class-based loading and dependency management
- **Enhanced plugin structure** following PSR-4 standards and single responsibility principle
- **Improved API documentation** in README with complete endpoint reference

### Technical Details
- Added 5 new Core classes: `Assets`, `Breadcrumbs`, `Codebase`, `Documentation`, `RewriteRules`
- Added 3 new Template classes: `Archive`, `CodebaseCard`, `Homepage`
- Refactored existing controllers to use dependency injection pattern
- Added proper WordPress hooks integration for theme compatibility
- Implemented hierarchical taxonomy resolution for codebase organization

### Fixed
- Improved error handling in API controllers
- Enhanced input validation for taxonomy operations

## [0.1.0] - 2025-11-XX

### Added
- Initial release of Chubes Docs plugin
- Basic REST API sync system for documentation
- Admin enhancements for chubes.net documentation management
- WordPress.org API integration for install tracking
- GitHub repository metadata fetching
- Markdown processing capabilities
