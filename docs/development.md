# Development Guide

This guide covers development workflows, build processes, and coding standards for the Chubes Docs plugin.

## Development Environment

### Local Setup

1. **Clone repository:**
   ```bash
   git clone https://github.com/chubes4/chubes-docs.git
   cd chubes-docs
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **WordPress setup:**
   - Use testing-grounds.local (multisite WordPress installation)
   - Activate the Chubes theme
   - Activate the Chubes Docs plugin

### Development Workflow

#### Code Changes
1. Create feature branch from `main`
2. Make changes following coding standards
3. Test changes locally
4. Run build process
5. Create pull request

#### Testing
- Manual testing on multisite WordPress installation
- API endpoint testing with curl/Postman
- Integration testing with Chubes theme
- Build validation with production ZIP creation

## Build Process

### Production Build

The `./build.sh` script creates a production-ready WordPress plugin package:

```bash
./build.sh
```

**What it does:**
1. Installs production dependencies (`composer install --no-dev`)
2. Copies files using `.buildignore` exclusions
3. Validates required files are present
4. Creates ZIP file in `build/` directory
5. Restores development dependencies

### Build Configuration

**Included in production build:**
- All PHP source files
- Composer autoloader (production optimized)
- CSS assets
- Required documentation files

**Excluded from production build:**
- Development dependencies (`vendor/` after production install)
- Development files (`.buildignore`, `build.sh`, `README.md`)
- Source control (`.git/`, `.gitignore`)
- Development documentation (`CLAUDE.md`, `CLAUDE.md`)

### File Structure

```
build/
└── chubes-docs.zip          # Production plugin package
```

## Coding Standards

### PHP Standards

- **PSR-4 autoloading** for all classes
- **WordPress coding standards** for PHP
- **Single responsibility principle** for class design
- **Comprehensive error handling** and input validation

### Architectural Principles

- **KISS (Keep It Simple, Stupid)**: Favor direct, centralized solutions
- **Single responsibility**: Each file handles one concern
- **Human-readable code**: Minimize inline comments through clear naming
- **Single source of truth**: No data duplication
- **REST API over admin-ajax.php**
- **Vanilla JavaScript over jQuery**
- **WordPress hooks and filters** for extensibility
- **Object-oriented programming** to reduce duplication

### Code Structure

```
inc/
├── Api/                    # REST API layer
│   ├── Controllers/       # Endpoint handlers
│   └── Routes.php         # Route registration
├── Core/                  # Plugin core systems
│   ├── Assets.php         # Asset management
│   ├── Breadcrumbs.php    # Breadcrumb generation
│   ├── Codebase.php       # Taxonomy management
│   ├── Documentation.php  # Post type handling
│   └── RewriteRules.php   # URL routing
├── Fields/                # Admin interface
├── Sync/                  # Synchronization
└── Templates/             # Frontend enhancements
```

## API Development

### Adding New Endpoints

1. **Define route in `Routes.php`:**
   ```php
   register_rest_route(self::NAMESPACE, '/new-endpoint', [
       'methods' => 'GET',
       'callback' => [NewController::class, 'handle_request'],
       'permission_callback' => [self::class, 'check_edit_permission'],
   ]);
   ```

2. **Create controller method:**
   ```php
   class NewController {
       public static function handle_request(WP_REST_Request $request): WP_REST_Response|WP_Error {
           // Implementation
           return rest_ensure_response($data);
       }
   }
   ```

3. **Add route registration:**
   ```php
   private static function register_new_routes(): void {
       // Route definitions
   }
   ```

### Parameter Validation

```php
'args' => [
    'param_name' => [
        'type' => 'string',
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'validate_callback' => function($value) {
            return !empty($value);
        }
    ]
]
```

## Database Operations

### Post Meta

```php
// Set meta
update_post_meta($post_id, '_custom_field', sanitize_text_field($value));

// Get meta
$value = get_post_meta($post_id, '_custom_field', true);
```

### Taxonomy Operations

```php
// Set terms
wp_set_object_terms($post_id, $term_ids, Codebase::TAXONOMY);

// Get terms
$terms = get_the_terms($post_id, Codebase::TAXONOMY);
```

## Security Considerations

### Input Validation

- Always sanitize user input
- Use WordPress sanitization functions
- Validate data types and formats
- Check user capabilities

### SQL Injection Prevention

- Use prepared statements
- Avoid direct SQL queries when possible
- Use WordPress query functions

### XSS Prevention

- Use `wp_kses_post()` for rich content
- Escape output with `esc_html()`, `esc_attr()`
- Sanitize before storing

## Testing

### Manual Testing Checklist

- [ ] Plugin activation/deactivation
- [ ] API endpoints functionality
- [ ] Taxonomy term creation
- [ ] Document sync operations
- [ ] Frontend display
- [ ] Admin interface
- [ ] Error handling

### API Testing

```bash
# Test endpoint
curl -X GET /wp-json/chubes/v1/docs \
  -H "Authorization: Bearer {token}"

# Test with data
curl -X POST /wp-json/chubes/v1/sync/setup \
  -H "Content-Type: application/json" \
  -d '{"project_slug":"test","project_name":"Test","category_slug":"test","category_name":"Test"}'
```

## Version Management

### Semantic Versioning

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Process

1. Update version in `composer.json`
2. Update `CHANGELOG.md`
3. Create git tag
4. Run production build
5. Test production package
6. Publish release

## Debugging

### WordPress Debug Mode

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Plugin Debug Logging

```php
// Add to plugin code
error_log('Debug message: ' . print_r($variable, true));
```

### API Debug Headers

```bash
curl -H "X-Debug: true" /wp-json/chubes/v1/docs
```

## Performance Optimization

### Database Queries

- Use `WP_Query` with proper caching
- Avoid N+1 query problems
- Cache expensive operations
- Use transients for external API data

### Asset Loading

- Conditional loading based on page type
- Minify CSS/JS in production
- Use WordPress asset versioning

### Memory Usage

- Process large datasets in chunks
- Clean up object references
- Use generators for large iterations

## Contributing

### Pull Request Process

1. Fork repository
2. Create feature branch
3. Make changes
4. Add tests if applicable
5. Update documentation
6. Create pull request
7. Code review
8. Merge

### Commit Messages

```
feat: add new sync endpoint
fix: resolve taxonomy term creation bug
docs: update API reference
refactor: improve error handling
```

### Code Review Checklist

- [ ] PSR-4 compliance
- [ ] WordPress coding standards
- [ ] Input validation
- [ ] Error handling
- [ ] Documentation updates
- [ ] Tests pass
- [ ] No security issues

## Deployment

### Staging Deployment

1. Build production package
2. Deploy to staging environment
3. Test all functionality
4. Verify no regressions

### Production Deployment

1. Backup production database
2. Deploy plugin update
3. Monitor error logs
4. Verify functionality
5. Update external systems if needed

## Support

For development support:

- Check existing issues on GitHub
- Review WordPress.org documentation
- Test with minimal reproduction case
- Include debug logs and error messages
- Specify WordPress/PHP versions