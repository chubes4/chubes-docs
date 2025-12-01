# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.1] - 2025-12-01

### Added
- **New `/sync/setup` API endpoint** for automated project and category creation with proper hierarchy
- **Enhanced sync system** with improved term handling using `project_term_id` and `subpath` parameters
- **Complete rewrite of URL routing** for hierarchical `/docs/` URLs with better path resolution
- **Enhanced path resolution** in `Codebase::resolve_path()` with term creation capabilities and better error handling
- **New documentation files**: `docs/troubleshooting.md`, `docs/migration-guide.md`, `docs/development.md` with comprehensive guides
- **AGENTS.md** file with AI agent collaboration instructions

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

## [0.3.0] - Unreleased

### Planned Features
- **Enhanced admin interface** with improved repository management
- **Bulk taxonomy operations** for better performance
- **Advanced sync filtering** and status reporting
- **Integration improvements** with external documentation sources
- **Performance optimizations** for large documentation sets

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