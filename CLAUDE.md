# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

DocSync is a WordPress plugin that syncs documentation from GitHub repositories to WordPress. It provides a complete documentation platform with REST API, WP-CLI commands, WP Abilities integration, and optional frontend templates.

**Key principle**: DocSync works on any WordPress site with any theme. The sync engine, API, CLI, and admin are always available. Frontend templates are opt-in via `add_theme_support( 'docsync-templates' )`.

## Architecture

```
docsync/
├── docsync.php              # Main plugin file
├── composer.json            # Dependencies and PSR-4 autoloading (DocSync\)
├── inc/
│   ├── PluginConfig.php     # Central config — all slugs/prefixes derive from here
│   ├── Api/                 # REST API layer (docsync/v1)
│   │   ├── Controllers/     # DocsController, ProjectController, SyncController
│   │   └── Routes.php       # Route registration
│   ├── Abilities/           # WP Abilities API (docsync/*)
│   │   ├── Abilities.php    # Category registration
│   │   ├── SyncAbilities.php
│   │   ├── DocsAbilities.php
│   │   ├── SearchAbilities.php
│   │   └── ProjectAbilities.php
│   ├── Core/                # Plugin core
│   │   ├── Documentation.php # documentation CPT registration
│   │   ├── Project.php      # project + project_type taxonomy
│   │   ├── RewriteRules.php # /docs/{cat}/{proj}/{sub}/{slug}/ routing
│   │   ├── Assets.php       # Conditional asset loading
│   │   └── Breadcrumbs.php  # Breadcrumb generation
│   ├── Sync/                # GitHub sync engine
│   │   ├── CronSync.php     # Scheduled sync via WP-Cron
│   │   ├── GitHubClient.php # GitHub REST API client (PAT auth)
│   │   ├── RepoSync.php     # Orchestrates full/incremental sync
│   │   ├── SyncManager.php  # Post create/update with source tracking
│   │   ├── MarkdownProcessor.php # GFM → HTML with link resolution
│   │   └── SyncNotifier.php # Email notifications
│   ├── Fields/              # Term meta fields
│   │   ├── RepositoryFields.php # GitHub URL, docs path, WP.org URL
│   │   └── InstallTracker.php   # WP.org install count cron
│   ├── Templates/           # Frontend (opt-in via theme support)
│   │   ├── Archive.php      # Archive page with project cards
│   │   ├── RelatedPosts.php # Related docs sidebar
│   │   ├── Homepage.php     # Homepage integration
│   │   ├── SearchBar.php    # Live search on archives
│   │   ├── ProjectCard.php  # Project card component
│   │   └── TableOfContents.php # Sticky TOC sidebar with scroll spy
│   ├── Admin/               # Admin enhancements
│   │   ├── SettingsPage.php # GitHub PAT, sync interval, diagnostics
│   │   ├── ProjectColumns.php
│   │   └── DocumentationColumns.php
│   └── WPCLI/               # CLI commands (wp docsync)
│       ├── CLI.php
│       └── Commands/        # sync, list, get, search, project ensure/tree
└── assets/
    ├── css/
    │   ├── tokens.css       # Design tokens (--docsync-*) with dark mode
    │   ├── archives.css
    │   ├── related-posts.css
    │   └── toc.css
    └── js/
        ├── admin-sync.js
        ├── docs-search.js
        └── docsync-toc.js
```

## Key Identifiers

All derive from `inc/PluginConfig.php`:

| System | Identifier |
|---|---|
| PHP Namespace | `DocSync\` |
| REST API | `docsync/v1` |
| WP-CLI | `wp docsync` |
| Abilities | `docsync/*` |
| Options | `docsync_*` |
| Hooks/Filters | `docsync_*` |
| Text Domain | `docsync` |
| CSS Tokens | `--docsync-*` |

## Content Model

- **CPT**: `documentation` — archive at `/docs/`
- **Taxonomy**: `project` (hierarchical) — organizes docs by project
- **Taxonomy**: `project_type` — classifies projects (plugins, themes, CLI tools)
- **Term meta**: `project_github_url`, `project_docs_path`, `project_wp_url`
- **Post meta**: `_sync_source_file`, `_sync_hash`, `_sync_markdown`, `_sync_timestamp`

## API Endpoints (docsync/v1)

### Documentation
- `GET /docs` — list (filters: project, status, search)
- `POST /docs` — create
- `GET /docs/{id}` — get (returns markdown for agents)
- `PUT /docs/{id}` — update
- `DELETE /docs/{id}` — delete

### Project Taxonomy
- `GET /project` — list terms
- `GET /project/tree` — hierarchical tree
- `POST /project/resolve` — find or create taxonomy path

### Sync
- `POST /sync/all` — sync all projects
- `POST /sync/term/{id}` — sync single project
- `POST /sync/doc` — sync individual doc
- `GET /sync/status` — sync status
- `GET /sync/test-token` — GitHub PAT diagnostics

## Theme Integration

Templates are **opt-in**. Without theme support, DocSync is a headless sync + API plugin.

```php
// In theme's functions.php — enables full template layer
add_theme_support( 'docsync-templates' );
```

CSS uses `--docsync-*` custom properties with built-in defaults. Themes override:
```css
:root { --docsync-accent-strong: var(--my-theme-accent); }
```

## Development

```bash
composer install          # Install dependencies
composer dump-autoload    # Regenerate autoloader
./build.sh               # Production ZIP in build/
```

## Dependencies

- `league/commonmark` ^2.5 — GitHub Flavored Markdown
- PHP 8.0+, WordPress 6.9+
