# Template Integration

This guide covers how the `chubes-docs` plugin integrates with the `chubes` theme.

## What The Plugin Owns

`chubes-docs` registers:

- The `documentation` post type
- The `project` taxonomy

It also injects archive/homepage enhancements by hooking into theme actions/filters.

## Templates (Plugin Side)

The plugin doesn’t provide WordPress template files to override. Instead it hooks into the theme’s templates:

- `inc/Templates/Archive.php`
  - Filters `chubes_archive_content` to render:
    - Project term archives (category grid vs term hierarchy)
    - Documentation archive grouping under `/docs`
  - Adds header UI via `chubes_archive_header_after`
  - Adjusts titles via `get_the_archive_title`
  - Renders project info cards in project term archives via `render_header_extras()`
    - Displays project statistics (doc count, install count, stars)
    - Shows action buttons (GitHub, WordPress.org, documentation)
    - Centers card statistics for improved layout

- `inc/Templates/Homepage.php`
  - Adds a “Documentation” column via the theme action `chubes_homepage_columns`

- `inc/Templates/RelatedPosts.php`
  - Renders related docs for single documentation posts (theme integration)

## Helper Functions

Defined in `chubes-docs/chubes-docs.php`:

- `chubes_get_repository_info( $term_or_terms )` is a wrapper for `ChubesDocs\Core\Project::get_repository_info()`.
- `chubes_generate_content_type_url( $post_type, $term )` proxies `ChubesDocs\Templates\ProjectCard::generate_content_type_url()`.

## Assets

Frontend CSS is enqueued by the plugin via `chubes-docs/inc/Core/Assets.php`:

- `assets/css/archives.css` on documentation/project archive contexts
- `assets/css/related-posts.css` on single `documentation` posts

Admin JS is enqueued on documentation settings and project term screens:

- `assets/js/admin-sync.js`

## Supported Hooks

The public hooks exposed by the plugin core are:

- `apply_filters( 'chubes_documentation_args', $args )`
- `do_action( 'chubes_documentation_registered' )`
- `apply_filters( 'chubes_project_args', $args )`
- `do_action( 'chubes_project_registered' )`
