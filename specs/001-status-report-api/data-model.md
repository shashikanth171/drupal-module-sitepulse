# Data Model: Status Report API

**Branch**: `001-status-report-api` | **Date**: 2026-03-28

## Entities

### StatusEntry (value object — not a Drupal entity)

Represents a single item from Drupal's status report. This is a
transient data structure derived from `SystemManager::listRequirements()`,
not a stored entity.

| Field | Type | Source | Notes |
|-------|------|--------|-------|
| `machine_name` | string | Array key from `listRequirements()` | Unique stable identifier (e.g., `php`, `database_system`, `cron`) |
| `title` | string | `$requirement['title']` | Cast from `TranslatableMarkup` to string |
| `value` | string | `$requirement['value']` | Cast to plain text; strip HTML markup |
| `description` | string\|null | `$requirement['description']` | Cast to plain text; null if not provided |
| `severity` | enum string | `$requirement['severity']` | Mapped from `RequirementSeverity` enum to: `ok`, `warning`, `error`, `info` |

**Identity**: `machine_name` is the unique key. No database storage.

**Lifecycle**: Created on each API request (or cache hit). No state
transitions. Entries are ephemeral snapshots.

### StatusSummary (value object)

Aggregate health indicator computed from all StatusEntry items.

| Field | Type | Derivation | Notes |
|-------|------|------------|-------|
| `overall_severity` | enum string | Highest severity across all entries | `ok` < `info` < `warning` < `error` |
| `counts` | object | Count per severity level | `{ ok: N, info: N, warning: N, error: N }` |
| `total` | integer | Sum of all counts | Total number of status entries |

**Derivation rules**:
- `overall_severity` = the worst (highest weight) severity found.
- Canonical severity weights for summary computation:

  | API string | Drupal enum value | Summary weight |
  |------------|-------------------|----------------|
  | `ok`       | `OK = 0`          | 0 (lowest)     |
  | `info`     | `Info = -1`       | 1              |
  | `warning`  | `Warning = 1`     | 2              |
  | `error`    | `Error = 2`       | 3 (highest)    |

- Note: Drupal's `RequirementSeverity::Info` has enum value
  `-1`, but for the API summary it ranks above `ok` since
  informational entries indicate checked-but-noteworthy items.

### Configuration: sitepulse.settings (simple config)

Stored in Drupal's config system (`config/install/sitepulse.settings.yml`).

| Key | Type | Default | Notes |
|-----|------|---------|-------|
| `cache_ttl` | integer | `60` | Cache lifetime in seconds |
| `denylist` | sequence of strings | `['database_system', 'database_system_version']` | Machine names of status entries to exclude from API responses (defense-in-depth; entries expose metadata, not raw credentials) |

**Schema**: Defined in `config/schema/sitepulse.schema.yml`.

## Relationships

```
SystemManager::listRequirements()
    │
    ▼
[Raw requirements array]
    │
    ├──(filter by denylist)──▶ [Filtered entries]
    │                              │
    │                              ├──(serialize)──▶ StatusEntry[]  ──▶ /status endpoint
    │                              │
    │                              └──(aggregate)──▶ StatusSummary  ──▶ /status/summary endpoint
    │
    └──(cache with TTL)──▶ Drupal cache bin
```

## Validation Rules

- `machine_name`: Non-empty string. Sourced from Drupal; no custom validation needed.
- `severity`: Must be one of `ok`, `warning`, `error`, `info`. Invalid values from Drupal (if any) default to `info`.
- `denylist` entries: Must be valid machine name strings (lowercase alphanumeric + underscore).
- `cache_ttl`: Integer >= 0. Zero disables caching.

## No Database Tables

Per constitution principle II (Minimal Footprint), this module
creates zero database tables. All data is derived at runtime from
Drupal core's status checking mechanism.
