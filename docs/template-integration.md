# Template Integration

This guide explains how Chubes Docs integrates with the Chubes theme and provides template enhancements for documentation display.

## Theme Requirements

Chubes Docs requires the Chubes theme to be active for full functionality. The theme provides:

- Base `documentation` post type and `codebase` taxonomy
- Core template structure and styling
- Homepage integration for documentation columns
- Archive and single post templates

## Plugin Template Components

### Core Templates

The plugin enhances theme templates through action hooks and filters:

#### Archive Template (`inc/Templates/Archive.php`)
- Enhances documentation archive pages
- Adds codebase filtering and navigation
- Integrates with theme's archive layout

#### CodebaseCard Template (`inc/Templates/CodebaseCard.php`)
- Generates project cards for listings
- Displays repository metadata (GitHub stars, WP installs)
- Provides consistent card styling

#### Homepage Template (`inc/Templates/Homepage.php`)
- Adds documentation column to theme homepage
- Integrates with theme's grid layout
- Displays featured or recent documentation

#### RelatedPosts Template (`inc/Templates/RelatedPosts.php`)
- Generates related documentation based on codebase taxonomy
- Hierarchical relationship logic
- Sidebar or content area integration

### Global Helper Functions

The plugin provides helper functions for theme integration:

```php
// Get repository information for a codebase term
$repo_info = chubes_get_repository_info($term_id);
// Returns: array with github_url, wp_url, installs, etc.

// Generate content type URLs
$content_url = chubes_generate_content_type_url('documentation', $post_id);
// Returns: proper permalink with taxonomy context
```

## Integration Points

### WordPress Hooks

#### Actions
```php
// After documentation post type registration
do_action('chubes_docs_post_type_registered');

// After codebase taxonomy registration
do_action('chubes_docs_taxonomy_registered');

// Before markdown processing
do_action('chubes_docs_before_process', $content, $post_id);

// After markdown processing
do_action('chubes_docs_after_process', $processed_content, $post_id);
```

#### Filters
```php
// Modify processed content
$content = apply_filters('chubes_docs_content', $content, $post);

// Modify markdown processor options
$options = apply_filters('chubes_docs_markdown_options', $options);

// Modify taxonomy terms for a post
$terms = apply_filters('chubes_docs_taxonomy_terms', $terms, $post);

// Modify repository API data
$data = apply_filters('chubes_docs_repository_data', $data, $term_id);
```

### Theme Integration

#### Template Overrides

Override plugin templates by creating files in your theme:

```
your-theme/
├── chubes-docs/
│   ├── archive.php          # Custom archive template
│   ├── single.php           # Custom single post template
│   ├── codebase-card.php    # Custom project card
│   ├── homepage-column.php  # Custom homepage integration
│   └── related-posts.php    # Custom related posts
```

#### CSS Integration

The plugin includes CSS files that integrate with the theme:

- `assets/css/archives.css` - Archive page styling
- `assets/css/related-posts.css` - Related posts styling

Theme CSS variables are automatically available.

### URL Routing

#### Rewrite Rules

The plugin adds custom rewrite rules for hierarchical documentation URLs:

```php
// /docs/codebase/project/subpath/ → archive view
// /docs/codebase/project/document/ → single post view
```

#### Breadcrumb Integration

Enhanced breadcrumbs via `inc/Core/Breadcrumbs.php`:

- Hierarchical codebase navigation
- Proper parent/child relationships
- Integration with theme breadcrumb system

## Frontend Features

### Archive Pages

Documentation archives include:

- **Codebase Filtering**: Dropdown or sidebar filters
- **Search Integration**: Full-text search across documentation
- **Pagination**: Standard WordPress pagination
- **Sorting**: By date, title, or relevance

### Single Post Views

Individual documentation posts feature:

- **Table of Contents**: Auto-generated from headings
- **Related Posts**: Context-aware suggestions
- **Codebase Navigation**: Breadcrumb trail
- **Social Sharing**: Integration with theme sharing features

### Homepage Integration

The homepage column displays:

- **Featured Documentation**: Editor-selected posts
- **Recent Updates**: Latest documentation changes
- **Popular Projects**: Based on repository metrics
- **Category Overview**: Quick access to documentation categories

## Admin Integration

### Meta Boxes

The plugin adds meta boxes to the documentation edit screen:

- **Repository Fields**: GitHub URL, WordPress.org URL, version
- **Install Tracking**: Display of current install counts
- **Sync Status**: Last sync time and source file information

### Admin Columns

Custom columns in the documentation list:

- **Codebase**: Associated taxonomy terms
- **Source**: Original file path (for synced content)
- **Last Sync**: Timestamp of last synchronization
- **Repository**: Links to GitHub/WordPress.org

## Asset Management

### CSS Assets

The plugin enqueues stylesheets conditionally:

```php
// Only on documentation pages
wp_enqueue_style('chubes-docs-archives', plugin_dir_url(__FILE__) . 'assets/css/archives.css');

// Only when related posts are displayed
wp_enqueue_style('chubes-docs-related-posts', plugin_dir_url(__FILE__) . 'assets/css/related-posts.css');
```

### JavaScript (Future)

While currently minimal, the plugin structure supports JS enhancements:

- AJAX loading of related posts
- Dynamic filtering on archive pages
- Interactive table of contents

## Performance Optimizations

### Conditional Loading

Features load only when needed:

- Template classes load only on relevant pages
- API endpoints load only in admin or when requested
- Assets load conditionally based on page type

### Caching

The plugin implements caching for:

- Repository API data (24-hour cache)
- Taxonomy term hierarchies
- Processed markdown content
- Related posts queries

### Database Queries

Optimized queries for:

- Taxonomy term resolution
- Related posts calculation
- Archive filtering
- Metadata retrieval

## Customization Examples

### Custom Archive Template

```php
<?php
// your-theme/chubes-docs/archive.php
get_header();

if (have_posts()) {
    echo '<div class="docs-archive">';
    while (have_posts()) {
        the_post();
        // Custom post display
        get_template_part('template-parts/content', 'documentation');
    }
    echo '</div>';
    
    // Custom pagination
    chubes_docs_pagination();
}

get_footer();
```

### Custom Related Posts

```php
<?php
// Hook into related posts display
add_action('chubes_docs_after_single_post', function($post_id) {
    $related = chubes_get_related_docs($post_id, 3);
    
    if ($related) {
        echo '<div class="custom-related-docs">';
        foreach ($related as $doc) {
            // Custom related post display
        }
        echo '</div>';
    }
});
```

### Custom Repository Display

```php
<?php
// Display custom repository info
function display_custom_repo_info($term_id) {
    $info = chubes_get_repository_info($term_id);
    
    if ($info) {
        echo '<div class="repo-stats">';
        echo '<span class="stars">' . $info['stars'] . ' stars</span>';
        echo '<span class="installs">' . $info['installs'] . ' installs</span>';
        echo '</div>';
    }
}
```

## Troubleshooting

### Common Issues

**Templates not loading**
- Ensure Chubes theme is active
- Check theme template override paths
- Verify plugin is activated

**Styles not applying**
- Check CSS enqueue conditions
- Verify theme CSS variable availability
- Clear any caching plugins

**Breadcrumbs incorrect**
- Check taxonomy hierarchy
- Verify breadcrumb filter hooks
- Test with different post types

**Related posts empty**
- Ensure posts have codebase terms assigned
- Check related posts algorithm logic
- Verify taxonomy relationships

### Debug Mode

Enable debugging for template issues:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check which templates are loading
add_action('template_include', function($template) {
    error_log('Loading template: ' . $template);
    return $template;
});
```

## Migration Guide

### From Previous Versions

When updating the plugin:

1. **Backup custom templates** in `your-theme/chubes-docs/`
2. **Update theme integration** if hooks have changed
3. **Test archive and single pages** for layout issues
4. **Verify CSS compatibility** with new stylesheets

### Theme Compatibility

For custom themes integrating Chubes Docs:

1. **Register post type and taxonomy** (or use plugin's)
2. **Create template files** following WordPress standards
3. **Implement required hooks** for plugin integration
4. **Style with theme variables** for consistency

## Future Enhancements

### Planned Features

- **Block Editor Integration**: Gutenberg blocks for documentation
- **Advanced Search**: Faceted search with filters
- **Version Management**: Documentation versioning system
- **Comment Integration**: Discussion on documentation pages
- **Analytics**: Usage tracking and reporting

### Extensibility

The plugin is designed for extension:

- **Custom sync sources** via filters
- **Additional metadata fields** via term meta
- **Custom template components** via actions
- **API extensions** via custom endpoints