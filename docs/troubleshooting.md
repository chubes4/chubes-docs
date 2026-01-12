# Troubleshooting Guide

This guide helps you diagnose and resolve common issues with the Chubes Docs plugin.

## Common Issues

### API Authentication Errors

**Problem:** Getting 401 Unauthorized or 403 Forbidden errors.

**Solutions:**
- Ensure you're using proper WordPress authentication (cookies or application passwords)
- Verify user has appropriate capabilities:
  - `edit_posts` for `POST/PUT /docs`, `GET /sync/status`, and `POST /sync/doc` + `POST /sync/batch`
  - `delete_posts` for `DELETE /docs/{id}`
  - `manage_categories` for `POST /sync/setup`, `PUT /codebase/{id}`, and `POST /codebase/resolve` when `create_missing: true`
  - `manage_options` for manual GitHub sync and diagnostics (`/sync/all`, `/sync/term/{id}`, `/sync/test-token`, `/sync/test-repo`)
- Check that the REST API is enabled in WordPress settings
- For external sync, ensure API credentials are configured correctly

### Sync Operations Failing

**Problem:** `/sync/doc` or `/sync/batch` endpoints return errors.

**Common Causes:**
- **Missing project setup:** Ensure `/sync/setup` was called first and `project_term_id` is valid
- **Invalid parameters:** Check that all required fields are provided (`source_file`, `title`, `content`, `project_term_id`, `filesize`, `timestamp`)
- **Subpath format:** Ensure `subpath` is an array of strings, not a single string
- **Timestamp issues:** Verify `timestamp` is a valid ISO 8601 format and newer than existing content

**Debug command:**
```bash
curl "/wp-json/chubes/v1/sync/status?project=your-project-slug"
```

If the request returns `403`, confirm the authenticated user has `edit_posts`.


### Markdown Processing Issues

**Problem:** Markdown content not converting properly or links not resolving.

**Common Issues:**
- Internal links not using `.md` extension
- Incorrect relative link paths
- Missing target documents
- Taxonomy hierarchy mismatches

**Check link resolution:**
```bash
# Get document and check rendered content
curl /wp-json/chubes/v1/docs/{post_id}
```

### Performance Issues

**Problem:** Slow API responses or high server load.

**Solutions:**
- Enable caching for taxonomy queries
- Use batch operations instead of individual requests
- Limit query result sets with `per_page` parameter
- Check for inefficient database queries in debug logs

### Install Tracking Not Working

**Problem:** WordPress.org install counts not updating.

**Causes:**
- Invalid repository URLs in term metadata
- API rate limiting from WordPress.org
- Plugin/theme not found in WordPress.org directory
- Metadata fields not properly set

**Verify metadata:**
```bash
curl /wp-json/chubes/v1/codebase/{term_id}
```

## Debug Mode

Enable detailed logging for troubleshooting:

### WordPress Debug Logging

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at `/wp-content/debug.log` for:
- API request details
- Database query performance
- Error stack traces
- Plugin execution flow

### API Debug Headers

Include debug headers in API responses:
```bash
curl -H "X-Debug: true" /wp-json/chubes/v1/docs
```

## Diagnostic Commands

### Check Plugin Status
```bash
# Verify plugin is active
wp plugin status chubes-docs

# Check for plugin errors
wp plugin verify-checksums chubes-docs
```

### Database Integrity
```bash
# Check for orphaned posts
wp db query "SELECT ID, post_title FROM wp_posts p LEFT JOIN wp_term_relationships tr ON p.ID = tr.object_id WHERE p.post_type = 'documentation' AND tr.term_taxonomy_id IS NULL"

# Verify taxonomy terms
wp term list codebase --format=table
```

### API Connectivity
```bash
# Test basic API connectivity
curl -I /wp-json/chubes/v1/

# Test authentication
curl -u "username:password" /wp-json/chubes/v1/docs
```

## Recovery Procedures

### Reset Sync Data
```bash
# Remove all sync metadata (CAUTION: destructive)
wp post meta delete --all --keys=_sync_%

# Reset taxonomy terms (CAUTION: destructive)
wp term delete codebase $(wp term list codebase --field=term_id)
```

### Rebuild Taxonomy Hierarchy
```bash
# Recreate project structure
curl -X POST /wp-json/chubes/v1/sync/setup \
  -d '{
    "project_slug": "my-project",
    "project_name": "My Project",
    "category_slug": "wordpress-plugins",
    "category_name": "WordPress Plugins"
  }'
```

### Clear Caches
```bash
# Clear WordPress object cache
wp cache flush

# Clear any external caches
wp transient delete --all
```

## Getting Help

If issues persist:

1. **Check the logs:** Enable debug logging and review error messages
2. **Verify configuration:** Ensure all required settings are configured
3. **Test with minimal data:** Use simple test cases to isolate issues
4. **Check WordPress compatibility:** Verify your WordPress version is supported
5. **Contact support:** Include debug logs and steps to reproduce the issue

## Prevention

- Regularly backup your WordPress database
- Test sync operations in a staging environment first
- Monitor API usage and rate limits
- Keep plugin and WordPress core updated
- Use version control for documentation sources