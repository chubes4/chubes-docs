# Usage Guide

## Documentation Post Type

The plugin provides a `documentation` custom post type for creating and managing documentation.

### Features

- Gutenberg editor support
- Markdown processing
- Codebase taxonomy for organization
- REST API access
- Archive and single views

### Creating Documentation

1. In WordPress admin, go to Documentation > Add New
2. Write your documentation content
3. Assign to appropriate codebase taxonomy terms
4. Publish

## Codebase Taxonomy

Organize documentation with the `codebase` hierarchical taxonomy.

### Structure

- wordpress-plugins
- wordpress-themes
- discord-bots
- php-libraries

### Usage

Assign taxonomy terms to documentation posts for categorization and filtering.

## REST API

### Endpoints

- `GET /wp-json/chubes/v1/docs` - List documentation
- `GET /wp-json/chubes/v1/docs/{id}` - Get specific documentation
- `GET /wp-json/chubes/v1/docs/codebase/{term}` - Get docs by codebase

### Parameters

- `per_page` - Number of results per page
- `page` - Page number
- `codebase` - Filter by codebase taxonomy

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

- **MarkdownProcessor**: Converts Markdown to HTML
- **SyncManager**: Handles external documentation sync
- **RepositoryFields**: Manages repository metadata

### Sync Process

1. Fetch documentation from external sources
2. Process Markdown content
3. Update or create documentation posts
4. Maintain taxonomy relationships

## Install Tracking

The plugin tracks installation and usage data for analytics.

### Features

- Installation date tracking
- Usage statistics
- Repository metadata storage
- Performance monitoring

## Templates

The plugin provides custom templates for documentation display:

- Archive template for documentation lists
- Single template for individual docs
- Codebase card components
- Related posts functionality

## Development

### Hooks and Filters

- `chubes_docs_content` - Modify processed content
- `chubes_docs_sync_sources` - Add sync sources
- `chubes_docs_taxonomy_terms` - Modify taxonomy terms

### Components

- Api/Controllers/DocsController.php - REST API handling
- Fields/ - Metadata management
- Sync/ - Synchronization logic
- Templates/ - Display components