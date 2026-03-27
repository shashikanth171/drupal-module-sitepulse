# SitePulse

SitePulse exposes Drupal's site status report data as a structured JSON API.
It provides machine-readable access to the same information shown on the
Administration > Reports > Status report page, enabling monitoring dashboards,
fleet management tools, and automated auditing without scraping admin pages.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/sitepulse).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/sitepulse).

## Table of Contents

- Requirements
- Installation
- Configuration
- Usage
- API Reference
- Troubleshooting
- Maintainers

## Requirements

- Drupal 11.x
- PHP 8.3 or higher
- The following core modules (enabled automatically on install):
  - System
  - Basic Authentication (`basic_auth`)
  - User

No contributed modules or third-party PHP libraries are required.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-modules).

With Composer:

```bash
composer require drupal/sitepulse
drush en sitepulse
```

Or enable via the admin UI at Administration > Extend.

## Configuration

1. Navigate to Administration > Configuration > System > SitePulse
   (`/admin/config/system/sitepulse`).
2. Set the **Cache TTL** (default: 60 seconds). This controls how long status
   data is cached between requests. Set to 0 to disable caching.
3. Configure the **Denylist** to exclude specific status entries from API
   responses. Enter one machine name per line. Defaults exclude
   `database_system` and `database_system_version`.
4. Assign the "Access SitePulse API" permission to roles that should be able
   to query the API. Navigate to Administration > People > Permissions and
   grant the permission to the appropriate roles.

## Usage

All API endpoints are under `/api/sitepulse/v1/`. Authentication is required
for the status and summary endpoints. The module supports both cookie-based
authentication (browser sessions) and HTTP Basic Authentication (monitoring
tools, scripts).

### Full status report

```bash
curl -u username:password https://example.com/api/sitepulse/v1/status
```

Returns all status entries in a JSON envelope with `data` and `meta` keys.

### Filter by severity

```bash
curl -u username:password "https://example.com/api/sitepulse/v1/status?severity=error,warning"
```

Accepts a comma-separated list of severity values: `ok`, `info`, `warning`,
`error`. Returns only entries matching the specified severities.

### Summary health check

```bash
curl -u username:password https://example.com/api/sitepulse/v1/status/summary
```

Returns the overall site health severity (the worst severity across all
entries) along with counts per severity level. Designed for frequent polling
by uptime monitoring services.

### OpenAPI specification

```bash
curl https://example.com/api/sitepulse/v1/openapi.json
```

Returns the OpenAPI 3.0 specification document. This endpoint is public and
does not require authentication. Load the URL in any Swagger UI instance for
interactive documentation.

## API Reference

### Response structure

Status endpoint:

```json
{
  "data": [
    {
      "machine_name": "php",
      "title": "PHP",
      "value": "8.3.6",
      "description": null,
      "severity": "ok"
    }
  ],
  "meta": {
    "count": 42,
    "generated": "2026-03-28T14:30:00+00:00"
  }
}
```

Summary endpoint:

```json
{
  "data": {
    "overall_severity": "warning",
    "counts": { "ok": 35, "info": 3, "warning": 4, "error": 0 },
    "total": 42
  },
  "meta": {
    "generated": "2026-03-28T14:30:00+00:00"
  }
}
```

### HTTP status codes

- **200**: Success.
- **400**: Invalid query parameter (e.g., unknown severity value).
- **401**: No authentication credentials provided.
- **403**: Authenticated but missing the required permission.

### Severity values

- `ok` - Requirement met.
- `info` - Informational.
- `warning` - Warning condition.
- `error` - Error condition.

## Troubleshooting

**I get a 401 response.**
Ensure you are sending credentials. For non-browser clients, use HTTP Basic
Authentication (`-u username:password` with curl). The `basic_auth` core
module must be enabled (it is enabled automatically when SitePulse is
installed).

**I get a 403 response.**
The authenticated user does not have the "Access SitePulse API" permission.
Grant it at Administration > People > Permissions.

**Some status entries are missing from the API response.**
Check the denylist at Administration > Configuration > System > SitePulse.
Entries whose machine names appear in the denylist are excluded from API
responses.

**Cached data is stale.**
Clear the cache with `drush cr` or reduce the Cache TTL in the settings form.
Setting Cache TTL to 0 disables caching entirely, but this increases response
time since status checks run on every request.

## Maintainers

- [Your Name](https://www.drupal.org/u/your-username)
