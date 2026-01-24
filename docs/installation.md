# Installation

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Chubes theme (required for full functionality)
- Composer (for dependency management)

## Installation

(Developer note: this repo’s installation process varies by environment; this page avoids step-by-step setup details.)

## Installation Steps

1. **Install the Chubes theme** - This plugin requires the Chubes theme to be active for documentation features
2. Download the plugin ZIP file from the releases page
3. In WordPress admin, go to Plugins > Add New
4. Click "Upload Plugin"
5. Upload the `chubes-docs.zip` file
6. Activate the plugin

## Development Setup

For development:

1. Clone the repository
2. Run `composer install` to install PHP dependencies
3. Run `./build.sh` to create production build

## Features

- **Documentation Management**: Custom `documentation` post type with Gutenberg editor support
- **Project Taxonomy**: Hierarchical `project` taxonomy for organizing documentation by project
- **REST API Layer**: Complete CRUD operations for docs, codebase management, and sync operations
- **Markdown Processing**: Convert markdown to HTML with internal link resolution using Parsedown
- **Sync System**: External documentation synchronization with batch operations and project setup
- **Repository Integration**: GitHub and WordPress.org repository metadata tracking
- **Install Tracking**: Automatic fetching of active install counts from WordPress.org API
- **Template Enhancements**: Archive views, codebase cards, related posts, and breadcrumb navigation

## Post-Installation

After activation, the plugin will:
- Register the `documentation` post type
- Create the `project` taxonomy
- Set up REST API routes
- Initialize install tracking