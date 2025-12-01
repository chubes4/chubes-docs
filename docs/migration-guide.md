# Migration Guide

This guide covers breaking changes and migration steps when upgrading Chubes Docs from version 0.1.x to 0.2.x.

## Version 0.2.0 Breaking Changes

### API Parameter Changes

#### Sync Endpoints

**Before (v0.1.x):**
```bash
curl -X POST /wp-json/chubes/v1/sync/doc \
  -d '{
    "title": "My Doc",
    "content": "# Content",
    "codebase_path": "wordpress-plugins/my-plugin"
  }'
```

**After (v0.2.x):**
```bash
# Step 1: Setup project
curl -X POST /wp-json/chubes/v1/sync/setup \
  -d '{
    "project_slug": "my-plugin",
    "project_name": "My Plugin",
    "category_slug": "wordpress-plugins",
    "category_name": "WordPress Plugins"
  }'

# Step 2: Sync document
curl -X POST /wp-json/chubes/v1/sync/doc \
  -d '{
    "source_file": "README.md",
    "title": "My Doc",
    "content": "# Content",
    "project_term_id": 15,
    "filesize": 1024,
    "timestamp": "2025-12-01T10:00:00Z",
    "subpath": ["docs"]
  }'
```

#### New Required Parameters

- `source_file`: Original file path (string, required)
- `filesize`: File size in bytes (integer, required)
- `timestamp`: ISO 8601 timestamp (string, required)
- `project_term_id`: Project taxonomy term ID (integer, required)
- `subpath`: Hierarchical path as array (array, optional)

#### Parameter Format Changes

- `codebase_path`: Now expects array instead of string
- `subpath`: Now expects array instead of string
- Removed: `codebase_path` from docs endpoints (use taxonomy resolution separately)

### Taxonomy Management Changes

#### Path Resolution

**Before:**
```bash
curl -X POST /wp-json/chubes/v1/codebase/resolve \
  -d '{"path": "wordpress-plugins/my-plugin/api"}'
```

**After:**
```bash
curl -X POST /wp-json/chubes/v1/codebase/resolve \
  -d '{"path": ["wordpress-plugins", "my-plugin", "api"]}'
```

#### Documentation Assignment

**Before:**
```bash
curl -X POST /wp-json/chubes/v1/docs \
  -d '{
    "title": "API Docs",
    "content": "...",
    "codebase_path": "wordpress-plugins/my-plugin/api"
  }'
```

**After:**
```bash
# First resolve/create taxonomy path
curl -X POST /wp-json/chubes/v1/codebase/resolve \
  -d '{
    "path": ["wordpress-plugins", "my-plugin", "api"],
    "create_missing": true
  }'

# Then create documentation
curl -X POST /wp-json/chubes/v1/docs \
  -d '{
    "title": "API Docs",
    "content": "...",
    "codebase_path": ["wordpress-plugins", "my-plugin", "api"]
  }'
```

## Migration Steps

### 1. Update Sync Scripts

Modify existing sync scripts to use the new API:

```javascript
// Before
const syncData = {
  title: "My Doc",
  content: markdown,
  codebase_path: "wordpress-plugins/my-plugin"
};

// After
const setupResponse = await fetch('/wp-json/chubes/v1/sync/setup', {
  method: 'POST',
  body: JSON.stringify({
    project_slug: "my-plugin",
    project_name: "My Plugin",
    category_slug: "wordpress-plugins",
    category_name: "WordPress Plugins"
  })
});

const { project_term_id } = await setupResponse.json();

const syncData = {
  source_file: "docs/README.md",
  title: "My Doc",
  content: markdown,
  project_term_id: project_term_id,
  filesize: fs.statSync("docs/README.md").size,
  timestamp: new Date().toISOString(),
  subpath: ["docs"]
};
```

### 2. Update Taxonomy References

Replace string-based paths with array-based paths:

```bash
# Before
curl /wp-json/chubes/v1/docs?codebase=my-plugin

# After (same - slug-based filtering still works)
curl /wp-json/chubes/v1/docs?codebase=my-plugin
```

### 3. Update Batch Operations

Modify batch sync payloads:

```json
{
  "docs": [
    {
      "source_file": "docs/install.md",
      "title": "Installation",
      "content": "...",
      "project_term_id": 15,
      "filesize": 2048,
      "timestamp": "2025-12-01T10:00:00Z",
      "subpath": ["guides"]
    }
  ]
}
```

### 4. Update CI/CD Pipelines

Update GitHub Actions or other automation:

```yaml
- name: Sync Documentation
  run: |
    # Get project term ID
    SETUP_RESPONSE=$(curl -s /wp-json/chubes/v1/sync/setup \
      -d '{"project_slug":"my-plugin","project_name":"My Plugin","category_slug":"wordpress-plugins","category_name":"WordPress Plugins"}')
    PROJECT_TERM_ID=$(echo $SETUP_RESPONSE | jq -r '.project_term_id')

    # Sync documents
    curl /wp-json/chubes/v1/sync/batch \
      -d "{\"docs\": [{\"source_file\":\"README.md\",\"title\":\"README\",\"content\":\"...\",\"project_term_id\":$PROJECT_TERM_ID,\"filesize\":1024,\"timestamp\":\"$(date -Iseconds)\"}]}"
```

## Compatibility Notes

### Backward Compatibility

- **Not maintained:** Direct migration from 0.1.x to 0.2.x requires code changes
- **Data preservation:** Existing posts and taxonomy terms are preserved
- **URL compatibility:** Existing documentation URLs remain functional

### Deprecated Features

- String-based `codebase_path` parameters (use arrays)
- String-based `subpath` parameters (use arrays)
- Automatic taxonomy creation during document sync (use `/sync/setup` first)

## Troubleshooting Migration

### Common Issues

**"Project term not found"**
- Ensure `/sync/setup` is called before sync operations
- Verify `project_term_id` is used instead of `codebase_path`

**"Invalid parameter format"**
- Check that `subpath` is an array, not a string
- Ensure `timestamp` is valid ISO 8601 format

**"Content not updating"**
- Include `filesize` and `timestamp` parameters
- Use `force: true` if needed

### Rollback Plan

If migration fails:

1. **Restore backup** of WordPress database
2. **Downgrade plugin** to previous version
3. **Test functionality** before retrying migration
4. **Update scripts gradually** rather than all at once

### Validation Steps

After migration:

1. **Test sync operations** with sample data
2. **Verify taxonomy structure** in WordPress admin
3. **Check documentation URLs** are working
4. **Validate API responses** match expected format
5. **Test batch operations** with multiple documents

## Version 0.2.1 Updates

### Additional Changes in 0.2.1

- Enhanced `/sync/setup` endpoint with improved error handling
- Better subpath resolution for nested documentation
- Improved timestamp validation for content updates
- Enhanced metadata support for repository information

### Migration from 0.2.0 to 0.2.1

No breaking changes - update is backward compatible. New features include:

- Better error messages in sync responses
- Improved performance for large batch operations
- Enhanced debugging capabilities

## Support

For migration assistance:

1. **Review this guide** thoroughly before starting
2. **Test in staging** environment first
3. **Backup data** before migration
4. **Update scripts incrementally** to minimize downtime
5. **Contact support** if issues persist after following this guide</content>
<parameter name="filePath">/Users/chubes/Developer/chubes-net/chubes-docs/docs/migration-guide.md