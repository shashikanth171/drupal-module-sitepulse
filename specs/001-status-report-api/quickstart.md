# Quickstart: SitePulse Module

## Prerequisites

- Drupal 11.x site running (DDEV or equivalent)
- `basic_auth` core module enabled
- A user account with the "access sitepulse api" permission

## Install

```bash
# Enable the module
drush en sitepulse -y

# Verify it's enabled
drush pm:list --filter=sitepulse
```

## Quick Test

### Full Status Report

```bash
# Using basic auth (replace admin:password with real credentials)
curl -u admin:password https://your-site.ddev.site/api/sitepulse/v1/status
```

Expected response:
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

### Filter by Severity

```bash
curl -u admin:password "https://your-site.ddev.site/api/sitepulse/v1/status?severity=error,warning"
```

### Summary Health Check

```bash
curl -u admin:password https://your-site.ddev.site/api/sitepulse/v1/status/summary
```

Expected response:
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

### OpenAPI Spec

```bash
curl https://your-site.ddev.site/api/sitepulse/v1/openapi.json
```

## Configure

Settings form: `/admin/config/system/sitepulse`

- **Cache TTL**: Seconds to cache status data (default: 60)
- **Denylist**: Machine names of status entries to exclude from API responses

## Run Tests

```bash
# Kernel tests
php web/core/scripts/run-tests.sh --module sitepulse --types PHPUnit-Kernel

# Functional tests
php web/core/scripts/run-tests.sh --module sitepulse --types PHPUnit-Functional
```

## Verify Permission

If you get a 403, ensure the user has the permission:

```bash
drush role:perm:add authenticated "access sitepulse api"
```
