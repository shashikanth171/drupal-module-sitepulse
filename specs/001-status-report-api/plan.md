# Implementation Plan: Status Report API

**Branch**: `001-status-report-api` | **Date**: 2026-03-28 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-status-report-api/spec.md`

## Summary

Expose Drupal's site status report data (`/admin/reports/status`) as a
structured JSON API at `/api/sitepulse/v1/status`. The module uses
Drupal core's `system.manager` service to collect status data, applies
a configurable denylist filter, caches responses with a time-based TTL,
and serves JSON via standard Drupal routing and controllers. A summary
endpoint provides lightweight health checks for monitoring tools.

## Technical Context

**Language/Version**: PHP 8.3+ (Drupal 11.x core requirement)
**Primary Dependencies**: Drupal core only (`system`, `basic_auth`, `user`)
**Storage**: Drupal config API (`sitepulse.settings`) + cache API (no custom tables)
**Testing**: PHPUnit via Drupal's test runner (Kernel + Functional)
**Target Platform**: Drupal 11.x web server (Apache/Nginx + PHP-FPM)
**Project Type**: Drupal contrib module
**Performance Goals**: Full status <2s, summary <500ms (SC-001, SC-002)
**Constraints**: Zero contrib dependencies, zero JS, zero custom DB tables
**Scale/Scope**: Single module, 3 routes, 1 service, 1 config form, ~10 files

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Evidence |
|-----------|--------|----------|
| I. Drupal Core First | PASS | Uses `system.manager` service, `basic_auth` core module, Drupal cache API, Form API, routing system. Zero third-party dependencies. |
| II. Minimal Footprint | PASS | Zero DB tables, zero entities, zero JS. One `ConfigFormBase` settings form (permitted per constitution v1.1.0). All code serves the single purpose. |
| III. API-Driven | PASS | Three JSON endpoints under `/api/sitepulse/v1/`. Cached via Drupal cache API. Permission-gated. Denylist prevents sensitive data exposure. |
| IV. Drupal Coding Standards | PASS | PSR-4 under `src/`, services in `sitepulse.services.yml`, routes in `sitepulse.routing.yml`, permissions in `sitepulse.permissions.yml`, tests under `tests/src/`. |

**Post-design re-check**: All principles still satisfied. No violations.

## Project Structure

### Documentation (this feature)

```text
specs/001-status-report-api/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── openapi.json     # Phase 1 output — OpenAPI 3.0 spec
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code

```text
web/modules/custom/sitepulse/
├── sitepulse.info.yml              # Module metadata
├── sitepulse.permissions.yml       # Permission declarations
├── sitepulse.routing.yml           # Route definitions (3 routes + settings)
├── sitepulse.services.yml          # Service declarations
├── sitepulse.links.menu.yml        # Admin menu link for settings
├── config/
│   ├── install/
│   │   └── sitepulse.settings.yml  # Default config values
│   └── schema/
│       └── sitepulse.schema.yml    # Config schema
├── src/
│   ├── Controller/
│   │   └── StatusController.php    # JSON endpoint controller
│   ├── Form/
│   │   └── SettingsForm.php        # Config form (denylist, TTL)
│   └── Service/
│       └── StatusCollector.php     # Core service: collect, filter, cache
└── tests/
    └── src/
        ├── Kernel/
        │   └── StatusCollectorTest.php   # Service logic tests
        └── Functional/
            └── StatusEndpointTest.php    # HTTP endpoint tests
```

**Structure Decision**: Standard Drupal module layout under
`web/modules/custom/sitepulse/`. PSR-4 namespace `Drupal\sitepulse`.
Test namespace `Drupal\Tests\sitepulse\{Kernel,Functional}`.

## Component Design

### StatusCollector Service

**Purpose**: Central service that collects, filters, caches, and
structures status report data.

**Service ID**: `sitepulse.status_collector`

**Dependencies** (injected):
- `system.manager` — to call `listRequirements()`
- `cache.default` — for time-based caching
- `config.factory` — to read `sitepulse.settings`
- `datetime.time` — for request timestamps

**Methods**:
- `getStatusEntries(?array $severityFilter = NULL): array` — Returns filtered, serialized status entries.
- `getStatusSummary(): array` — Returns aggregate severity + counts.
- `buildEntries(): array` — Internal: collects from SystemManager, applies denylist, converts to plain arrays. Cached.

### StatusController

**Purpose**: Thin controller that delegates to `StatusCollector` and returns `JsonResponse`.

**Routes**:
- `sitepulse.status` → `GET /api/sitepulse/v1/status` → `StatusController::status()`
- `sitepulse.status_summary` → `GET /api/sitepulse/v1/status/summary` → `StatusController::summary()`
- `sitepulse.openapi` → `GET /api/sitepulse/v1/openapi.json` → `StatusController::openapi()`

**All routes** require `_permission: 'access sitepulse api'` (except openapi which is public) and declare `_auth: [basic_auth, cookie]`.

### SettingsForm

**Purpose**: Admin form at `/admin/config/system/sitepulse` for managing cache TTL and denylist.

**Config**: `sitepulse.settings` with keys `cache_ttl` (int) and `denylist` (sequence of strings).

## Complexity Tracking

> No Constitution Check violations. No complexity justification needed.
