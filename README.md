# DocSync

A WordPress plugin that syncs documentation from GitHub repositories into WordPress. Markdown files in your repo become structured, searchable documentation posts — with hierarchical project organization, a REST API, WP-CLI commands, and optional frontend templates.

## How It Works

```
GitHub repo (docs/*.md)
        │
        ▼
   DocSync sync engine
   ├── Fetches via GitHub API
   ├── Compares commit SHAs (incremental)
   ├── Converts GFM markdown → HTML
   ├── Resolves internal .md links → WP permalinks
   └── Creates/updates documentation posts
        │
        ▼
WordPress (documentation CPT)
   ├── REST API (docsync/v1)
   ├── WP-CLI (wp docsync)
   ├── WP Abilities API
   └── Optional frontend templates
```

## Features

- **GitHub sync** — full and incremental sync via commit SHA comparison
- **Markdown processing** — GitHub Flavored Markdown with internal link resolution
- **Hierarchical organization** — `project` and `project_type` taxonomies with deep URL routing
- **REST API** — full CRUD under `docsync/v1` with markdown output for AI agents
- **WP-CLI** — `wp docsync docs {list, get, search, sync}` and `wp docsync project {ensure, tree}`
- **WP Abilities API** — AI agent capabilities for sync, search, and project inspection
- **Orphan cleanup** — docs removed from the repo are automatically deleted
- **Scheduled sync** — configurable WP-Cron intervals (hourly / twice daily / daily)
- **Admin UI** — settings page with GitHub PAT configuration, connection diagnostics, per-project sync controls
- **Theme-agnostic** — works on any WordPress theme. Frontend templates are opt-in.

## Requirements

- WordPress 6.9+
- PHP 8.0+

## Installation

1. Upload the `docsync` directory to `wp-content/plugins/`
2. Activate the plugin
3. Go to **Documentation → Settings** and enter your GitHub Personal Access Token
4. Create a project term under **Documentation → Projects**, set its GitHub URL
5. Sync: click "Sync" in the admin, use `wp docsync docs sync --all`, or wait for cron

## Configuration

### GitHub PAT

Generate a [Personal Access Token](https://github.com/settings/tokens) with `repo` scope (for private repos) or no scope (for public repos only). Enter it at **Documentation → Settings**.

### Adding a Repository

1. Go to **Documentation → Projects**
2. Create or edit a term
3. Set **GitHub URL** (e.g., `https://github.com/your-org/your-repo`)
4. Set **Docs Path** (default: `docs`) — the directory in the repo containing `.md` files
5. Click **Sync** to pull docs

### Sync Intervals

Configure at **Documentation → Settings**:
- **Hourly** — best for actively developed projects
- **Twice Daily** — good default
- **Daily** — for stable projects

## Theme Integration

DocSync works on any theme with zero configuration. The sync engine, REST API, CLI, and admin always work.

For themes that want the full documentation frontend (archives, search, breadcrumbs, related posts, TOC sidebar), opt in:

```php
// In your theme's functions.php
add_theme_support( 'docsync-templates' );
```

### CSS Customization

DocSync ships standalone CSS with `--docsync-*` custom properties and sensible light/dark mode defaults. Override in your theme:

```css
:root {
    --docsync-accent-strong: #your-brand-color;
    --docsync-background-card: #your-card-bg;
}
```

See `assets/css/tokens.css` for all available properties.

## REST API

All endpoints use the `docsync/v1` namespace.

### Documentation

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/docs` | List docs (filters: `project`, `status`, `search`, `per_page`, `page`) |
| `POST` | `/docs` | Create a doc |
| `GET` | `/docs/{id}` | Get a doc (includes `markdown` field for AI agents) |
| `PUT` | `/docs/{id}` | Update a doc |
| `DELETE` | `/docs/{id}` | Delete a doc |

### Project Taxonomy

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/project` | List project terms |
| `GET` | `/project/tree` | Hierarchical project tree |
| `POST` | `/project/resolve` | Find or create a taxonomy path |
| `GET` | `/project/{id}` | Get a project term |
| `PUT` | `/project/{id}` | Update a project term |

### Sync

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/sync/all` | Sync all projects |
| `POST` | `/sync/term/{id}` | Sync a single project |
| `POST` | `/sync/doc` | Sync an individual document |
| `POST` | `/sync/batch` | Batch sync documents |
| `GET` | `/sync/status` | Sync status for a project |
| `GET` | `/sync/test-token` | GitHub PAT diagnostics |
| `POST` | `/sync/test-repo` | Test repo access |

### Example: Fetch docs as markdown

```bash
curl https://example.com/wp-json/docsync/v1/docs/123
```

The response includes a `markdown` field with the raw source — useful for AI agents and static site generators.

## WP-CLI

```bash
# List all docs
wp docsync docs list

# List docs for a project
wp docsync docs list --project=data-machine

# Get a doc as markdown
wp docsync docs get installation-guide

# Search docs
wp docsync docs search "webhook"

# Sync a single project
wp docsync docs sync 42

# Sync all projects
wp docsync docs sync --all

# Show project hierarchy
wp docsync project tree

# Ensure a project term exists
wp docsync project ensure --type=wordpress-plugins --project=my-plugin --name="My Plugin"
```

## WP Abilities API

For AI agent integration via [WP Abilities](https://developer.wordpress.org/abilities/):

| Ability | Description |
|---|---|
| `docsync/sync-docs` | Sync a single project from GitHub |
| `docsync/sync-docs-batch` | Sync multiple projects |
| `docsync/get-doc` | Fetch a doc (returns markdown) |
| `docsync/search-docs` | Full-text search |
| `docsync/get-projects` | Get project hierarchy with metadata |
| `docsync/get-project-types` | Get project type classifications |
| `docsync/reset-documentation` | Delete all docs and reset sync state |

## URL Structure

```
/docs/                                    → Documentation archive
/docs/{project-type}/                     → Project type archive
/docs/{project-type}/{project}/           → Project docs
/docs/{project-type}/{project}/{sub}/     → Sub-section
/docs/{project-type}/{project}/{sub}/{doc}/ → Single doc
```

## Development

```bash
# Install dependencies
composer install

# Regenerate autoloader
composer dump-autoload --optimize

# Build production ZIP
./build.sh
```

### Architecture

```
docsync/
├── docsync.php              # Main plugin file
├── inc/
│   ├── PluginConfig.php     # Central config (all identifiers derive from here)
│   ├── Api/                 # REST API (docsync/v1)
│   ├── Abilities/           # WP Abilities API (docsync/*)
│   ├── Core/                # CPT, taxonomy, routing, assets
│   ├── Sync/                # GitHub sync engine
│   ├── Fields/              # Term meta fields
│   ├── Templates/           # Frontend (opt-in via theme support)
│   ├── Admin/               # Settings, admin columns
│   └── WPCLI/               # CLI commands
└── assets/
    ├── css/                 # tokens.css, archives.css, toc.css, related-posts.css
    └── js/                  # admin-sync.js, docs-search.js, docsync-toc.js
```

## License

GPL v2 or later

## Author

Chris Huber — https://chubes.net
