# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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