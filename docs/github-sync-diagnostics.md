# GitHub Sync Diagnostics

This page documents the REST endpoints used for GitHub connectivity checks and manual GitHub-based sync runs.

For the full endpoint list, see [API Reference](api-reference.md).

## Endpoints

### GET /wp-json/chubes/v1/sync/test-token

Test the configured GitHub Personal Access Token (PAT).

- **Permissions:** requires `manage_options`
- **Response:** diagnostic object returned by the GitHub client.
- **Errors:**
  - `400 no_token` when no PAT is configured
  - `500 connection_failed` when the GitHub API request fails

### POST /wp-json/chubes/v1/sync/test-repo

Test access to a repository URL.

- **Permissions:** requires `manage_options`
- **Body:**
  ```json
  { "repo_url": "https://github.com/owner/repo" }
  ```
- **Response:** repository diagnostic object on success.
- **Errors:** returns a WP REST error response; error `data` may include `owner`, `repo`, and `sso_url`.

## Manual GitHub Sync

### POST /wp-json/chubes/v1/sync/all

Run GitHub sync across all codebase terms that have a GitHub URL configured.

- **Permissions:** requires `manage_options`
- **Response:**
  ```json
  {
    "repos_synced": 2,
    "total_added": 10,
    "total_updated": 3,
    "total_removed": 1,
    "errors": [],
    "message": "Synced 2 repos. Added: 10, Updated: 3, Removed: 1"
  }
  ```

### POST /wp-json/chubes/v1/sync/term/{id}

Run GitHub sync for a single codebase term.

- **Permissions:** requires `manage_options`
- **Body parameters:**
  - `force` (boolean, default `false`)
- **Response:**
  ```json
  {
    "added": 3,
    "updated": 1,
    "removed": 0,
    "message": "Added: 3, Updated: 1, Removed: 0"
  }
  ```
