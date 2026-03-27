# Research: Status Report API

**Branch**: `001-status-report-api` | **Date**: 2026-03-28

## Status Report Data Source

**Decision**: Use `SystemManager::listRequirements()` from the `system.manager` service.

**Rationale**: This is the same service used by Drupal's admin status report controller (`SystemInfoController::status()`). It invokes `hook_requirements('runtime')`, `hook_runtime_requirements()`, and their alter hooks, then sorts by weight/title.

**Alternatives considered**:
- Direct hook invocation (`module_handler->invokeAll('requirements')`) — rejected because `SystemManager` already handles sorting, loading `.install` files, and invoking both legacy and modern hooks.
- Parsing the rendered HTML of `/admin/reports/status` — rejected as fragile and violates Drupal Core First principle.

## Requirement Entry Structure

**Decision**: Each requirement from `listRequirements()` is an associative array keyed by machine name with:
- `title` (TranslatableMarkup)
- `value` (mixed — string, markup, or Link object)
- `description` (TranslatableMarkup, optional)
- `severity` (RequirementSeverity enum: Info=-1, OK=0, Warning=1, Error=2)
- `weight` (optional int, for sorting)

**Rationale**: This is the native data structure. The API must convert `TranslatableMarkup` to plain strings and map `RequirementSeverity` enum values to human-readable strings (info, ok, warning, error).

## Severity Enum

**Decision**: Use `Drupal\Core\Extension\Requirement\RequirementSeverity` PHP enum.

**Rationale**: This is the modern Drupal 11 approach. Legacy integer constants (`SystemManager::REQUIREMENT_OK`, etc.) are deprecated since Drupal 11.2.0. The enum provides `->status()` method returning lowercase strings ('checked', 'ok', 'warning', 'error').

**Note**: The enum maps `Info` to 'checked' via `->status()`. The spec calls for 'info' — we will map `RequirementSeverity::Info` to 'info' rather than 'checked' for API clarity.

## Caching Strategy

**Decision**: Time-based TTL (~60 seconds) via Drupal cache API.

**Rationale**: Status checks invoke PHP functions, filesystem checks, and database queries on every call. There is no event/hook that signals "status data changed," so event-based invalidation is impossible. A 60-second TTL balances freshness with performance. Cache is also invalidated on `drush cr` / manual cache rebuild (standard Drupal behavior for all cache bins).

**Implementation**:
```php
$expire = \Drupal::time()->getRequestTime() + 60;
\Drupal::cache('default')->set('sitepulse:status', $data, $expire);
```

## Authentication

**Decision**: Require `basic_auth` core module as a dependency. Declare `_auth: [basic_auth, cookie]` in route options.

**Rationale**: Monitoring tools and scripts need non-browser authentication. `basic_auth` is a core module requiring zero contrib dependencies. Cookie auth is also included for browser-based testing.

**Alternatives considered**:
- Keys module token auth — deferred to v2 (out of scope per spec).
- OAuth — overkill for a status endpoint, adds heavy dependency.

## Routing Pattern

**Decision**: Custom routes via `sitepulse.routing.yml` with controllers returning `JsonResponse`.

**Rationale**: Using Drupal's standard routing + controller system avoids dependency on REST module or JSON:API module. Routes under `/api/sitepulse/v1/` with `_controller` pointing to service methods.

**Alternatives considered**:
- REST module (`rest.resource`) — adds core dependency, complex config, not needed for simple JSON endpoints.
- JSON:API module — designed for entity CRUD, not status reporting.

## Sensitive Data Filtering

**Decision**: Configurable denylist in `sitepulse.settings` config, default: database credentials only.

**Rationale**: Per clarification, only DB credentials are blocked by default. Admins can extend. Implementation filters requirement entries by machine name against the denylist before serialization.

## Settings Form

**Decision**: Standard `ConfigFormBase` at `/admin/config/system/sitepulse`.

**Rationale**: Aligns with constitution v1.1.0 (standard Drupal config forms are permitted). Uses `#config_target` pattern for binding form elements to config keys.

## OpenAPI Specification

**Decision**: Static JSON file served by a controller at `/api/sitepulse/v1/openapi.json`.

**Rationale**: The API surface is small and stable. A static OpenAPI document avoids runtime generation complexity. It can be updated as endpoints evolve. The controller reads the file and returns it as JSON.

**Alternatives considered**:
- Dynamic generation via OpenAPI library — adds third-party dependency, violates constitution.
- YAML format — JSON is more universally consumed by Swagger UI.

## Testing Strategy

**Decision**: Kernel tests for service logic, Functional tests for HTTP endpoints.

**Rationale**: Kernel tests are fast and sufficient for testing the status data collection and filtering service. Functional tests (extending `BrowserTestBase`) are needed to test actual HTTP requests, authentication, permissions, and JSON responses.

**Test namespaces**:
- `Drupal\Tests\sitepulse\Kernel\*`
- `Drupal\Tests\sitepulse\Functional\*`
