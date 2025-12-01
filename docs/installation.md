# Installation

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Composer (for dependency management)
- Node.js 16 or higher (for development)

## Installation Steps

1. Download the plugin ZIP file from the releases page
2. In WordPress admin, go to Plugins > Add New
3. Click "Upload Plugin"
4. Upload the `chubes-docs.zip` file
5. Activate the plugin

## Development Setup

For development:

1. Clone the repository
2. Run `composer install` to install PHP dependencies
3. Run `npm install` to install Node.js dependencies (if any)
4. Run `./build.sh` to create production build

## Features

- Custom post type: `documentation`
- Taxonomy: `codebase` for organizing docs
- Markdown processing with Parsedown
- REST API endpoints for documentation
- Install tracking and repository metadata
- Sync system for external documentation

## Post-Installation

After activation, the plugin will:
- Register the `documentation` post type
- Create the `codebase` taxonomy
- Set up REST API routes
- Initialize install tracking