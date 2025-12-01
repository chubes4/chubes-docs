# Sync Guide

This guide explains how to use the Chubes Docs synchronization system to import and manage documentation from external sources.

## Overview

The sync system allows you to automatically import documentation from external repositories, convert Markdown to HTML, and organize content using the codebase taxonomy. Version 0.2.1 introduces enhanced project-based sync with improved term management and hierarchical subpath support.

## Key Concepts

### Project Structure
- **Projects**: Top-level organizational units (e.g., "my-wordpress-plugin")
- **Categories**: Group projects by type (e.g., "wordpress-plugins", "php-libraries")
- **Subpaths**: Hierarchical organization within projects (e.g., "api/endpoints", "guides/getting-started")

### Sync Process
1. **Setup**: Use `/sync/setup` to create project and category taxonomy terms
2. **Import**: Sync individual documents or batches using `project_term_id` and `subpath`
3. **Process**: Convert Markdown and resolve internal links with taxonomy-aware URLs
4. **Maintain**: Update existing content as sources change with timestamp validation

## Setup

### 1. Project Setup

Before syncing documentation, set up the project structure using the new `/sync/setup` endpoint:

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

**Response:**
```json
{
  "success": true,
  "category_term_id": 5,
  "category_slug": "wordpress-plugins",
  "project_term_id": 15,
  "project_slug": "my-plugin"
}
```

This creates the taxonomy hierarchy and returns the `project_term_id` needed for sync operations.

### 2. Repository Metadata (Optional)

Associate repository information with your project:

```bash
curl -X PUT /wp-json/chubes/v1/codebase/{project_term_id} \
  -H "Content-Type: application/json" \
  -d '{
    "meta": {
      "github_url": "https://github.com/username/my-plugin",
      "wordpress_url": "https://wordpress.org/plugins/my-plugin",
      "version": "1.0.0"
    }
  }'
```

## Syncing Documentation

### Single Document Sync

Import individual documentation files using the enhanced sync endpoint:

```bash
curl -X POST /wp-json/chubes/v1/sync/doc \
  -H "Content-Type: application/json" \
  -d '{
    "source_file": "docs/README.md",
    "title": "Getting Started",
    "content": "# Getting Started\n\nThis is my plugin...",
    "project_term_id": 15,
    "filesize": 1024,
    "timestamp": "2025-12-01T10:00:00Z",
    "subpath": ["guides"],
    "excerpt": "Learn how to get started with My Plugin"
  }'
```

**Parameters:**
- `source_file`: Path to original file (required, for tracking)
- `title`: Display title (required)
- `content`: Markdown content (required)
- `project_term_id`: ID from project setup (required)
- `filesize`: Size of source file in bytes (required)
- `timestamp`: ISO 8601 timestamp of last modification (required)
- `subpath`: Hierarchical path as array (optional)
- `excerpt`: Brief description (optional)
- `force`: Override existing content (default: false)

### Batch Sync

Import multiple documents at once with the enhanced batch endpoint:

```bash
curl -X POST /wp-json/chubes/v1/sync/batch \
  -H "Content-Type: application/json" \
  -d '{
    "docs": [
      {
        "source_file": "docs/installation.md",
        "title": "Installation",
        "content": "# Installation\n\nInstall instructions...",
        "project_term_id": 15,
        "filesize": 2048,
        "timestamp": "2025-12-01T10:00:00Z",
        "subpath": ["guides"]
      },
      {
        "source_file": "docs/api.md",
        "title": "API Reference",
        "content": "# API Reference\n\nAPI docs...",
        "project_term_id": 15,
        "filesize": 4096,
        "timestamp": "2025-12-01T10:15:00Z",
        "subpath": ["api"]
      }
    ]
  }'
```

## Taxonomy Management

### Automatic Subpath Resolution

The sync system automatically creates taxonomy terms for subpaths within projects:

```bash
# This creates: wordpress-plugins/my-plugin/guides/installation
curl -X POST /wp-json/chubes/v1/sync/doc \
  -d '{
    "title": "Installation Guide",
    "content": "...",
    "project_term_id": 15,
    "filesize": 1024,
    "timestamp": "2025-12-01T10:00:00Z",
    "subpath": ["guides", "installation"]
  }'
```

### Manual Taxonomy Management

Resolve taxonomy paths manually using the codebase API:

```bash
curl -X POST /wp-json/chubes/v1/codebase/resolve \
  -H "Content-Type: application/json" \
  -d '{
    "path": ["wordpress-plugins", "my-plugin", "api", "v1"],
    "create_missing": true
  }'
```

## Content Processing

### Markdown Conversion

Content is automatically converted from Markdown to HTML:

```markdown
# Heading 1
## Heading 2

[Internal Link](api/endpoints.md)
[External Link](https://example.com)

```php
echo "code block";
```
```

Becomes:
```html
<h1>Heading 1</h1>
<h2>Heading 2</h2>

<a href="/docs/wordpress-plugins/my-plugin/api/endpoints/">Internal Link</a>
<a href="https://example.com">External Link</a>

<pre><code class="language-php">echo "code block";</code></pre>
```

### Internal Link Resolution

Internal `.md` links are automatically converted to WordPress URLs:
- `[API Docs](api.md)` → `/docs/project/api/`
- `[Parent](../readme.md)` → `/docs/project/`
- `[Nested](guides/advanced.md)` → `/docs/project/guides/advanced/`

## Monitoring Sync Status

Check sync status and progress for a specific project:

```bash
curl /wp-json/chubes/v1/sync/status?project=my-plugin
```

Response:
```json
{
  "total_docs": 15,
  "synced_docs": 15,
  "project_slug": "my-plugin",
  "project_term": 15,
  "docs": [
    {
      "post_id": 125,
      "title": "Getting Started",
      "source_file": "docs/README.md",
      "sync_filesize": 1024,
      "sync_timestamp": "2025-12-01T10:00:00Z",
      "status": "synced"
    }
  ]
}
```

## Best Practices

### File Organization
- Use consistent subpath structures (e.g., `guides/`, `api/`, `examples/`)
- Keep source files in a `docs/` directory in your repository
- Use descriptive filenames that become good URLs

### Content Guidelines
- Write in Markdown for easy maintenance
- Use relative links for internal documentation
- Include excerpts for better SEO and listings
- Keep titles concise but descriptive

### Automation
- Integrate sync into your CI/CD pipeline
- Use webhooks to trigger sync on repository changes
- Schedule regular sync for documentation updates
- Monitor sync status for failed imports

### Error Handling
- Check response codes and error messages
- Handle rate limiting for external API calls
- Validate content before syncing
- Log sync operations for debugging

## Troubleshooting

For comprehensive troubleshooting guidance, see the [Troubleshooting Guide](troubleshooting.md).

### Common Issues

**"Project term not found"**
- Ensure you've run `/sync/setup` first and used the returned `project_term_id`
- Verify the project term exists in WordPress admin under Codebase taxonomy
- Check that the term ID is numeric and valid

**"Invalid subpath"**
- Ensure `subpath` is an array of strings, not a string
- Check that subpath segments don't contain invalid characters (only letters, numbers, hyphens, underscores)
- Keep subpath hierarchies shallow (max 4 levels)

**"Content not updating"**
- Set `force: true` to override existing content
- Ensure the `timestamp` parameter is newer than the existing document's timestamp
- Verify the content hash has actually changed

**"Links not resolving"**
- Ensure internal links use `.md` extension
- Check that target documents exist and are synced
- Verify taxonomy hierarchy matches link paths

### Debug Mode

Enable debug logging by adding to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for detailed error information.

## Integration Examples

### GitHub Actions Workflow

```yaml
name: Sync Documentation
on:
  push:
    paths:
      - 'docs/**'
jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Sync Docs
        run: |
          curl -X POST ${{ secrets.WP_SYNC_URL }}/wp-json/chubes/v1/sync/batch \
            -H "Authorization: Bearer ${{ secrets.WP_API_TOKEN }}" \
            -H "Content-Type: application/json" \
            -d @docs-sync-payload.json
```

### Node.js Script

```javascript
const fs = require('fs');
const path = require('path');

async function syncDocs() {
  const docsDir = './docs';
  const files = fs.readdirSync(docsDir).filter(f => f.endsWith('.md'));

  const docs = files.map(file => ({
    source_file: file,
    title: path.parse(file).name.replace(/-/g, ' '),
    content: fs.readFileSync(path.join(docsDir, file), 'utf8'),
    project_term_id: process.env.PROJECT_TERM_ID,
    subpath: 'guides'
  }));

  const response = await fetch('/wp-json/chubes/v1/sync/batch', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ docs })
  });

  console.log(await response.json());
}
```