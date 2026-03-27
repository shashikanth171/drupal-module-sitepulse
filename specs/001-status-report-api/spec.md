# Feature Specification: Status Report API

**Feature Branch**: `001-status-report-api`
**Created**: 2026-03-28
**Status**: Draft
**Input**: User description: "Expose Drupal site status report data via a REST JSON API. Start with basics from the status report. Use Drupal core services, keep code minimal and lightweight."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Retrieve Full Site Status (Priority: P1)

As a site administrator or monitoring tool, I want to
retrieve the full site status report as structured JSON
so that I can monitor site health without logging into
the Drupal admin interface.

**Why this priority**: This is the core value proposition
of the module. Without this, nothing else matters.

**Independent Test**: Can be fully tested by making an
authenticated request to the status endpoint and verifying
that structured status data is returned.

**Acceptance Scenarios**:

1. **Given** an authenticated user with the "access sitepulse api" permission, **When** they request the status
   endpoint, **Then** they receive a JSON response containing
   all status report entries with title, value, severity,
   and description for each entry.
2. **Given** an authenticated user with the required
   permission, **When** they request the status endpoint,
   **Then** each entry includes a severity level
   (ok, warning, error, info) that matches what Drupal's
   admin status report page shows.
3. **Given** a user without the required permission,
   **When** they request the status endpoint, **Then** they
   receive a 403 Forbidden response with no status data.

---

### User Story 2 - Filter Status by Severity (Priority: P2)

As a monitoring tool operator, I want to filter status
entries by severity level so that I can quickly identify
only warnings or errors without parsing the full report.

**Why this priority**: Filtering is a natural extension of
the core endpoint that significantly improves usability for
automated monitoring, but the full report (US1) delivers
value on its own.

**Independent Test**: Can be tested by requesting the
status endpoint with a severity filter parameter and
verifying only matching entries are returned.

**Acceptance Scenarios**:

1. **Given** an authenticated user, **When** they request
   the status endpoint with a severity filter of "error",
   **Then** only entries with error severity are returned.
2. **Given** an authenticated user, **When** they request
   the status endpoint with multiple severity values,
   **Then** entries matching any of the specified severities
   are returned.
3. **Given** an authenticated user, **When** they request
   the status endpoint with an invalid severity value,
   **Then** a 400 Bad Request response is returned with a
   descriptive error message.

---

### User Story 3 - Summary Health Check (Priority: P3)

As an uptime monitoring service, I want a lightweight
summary endpoint that returns only the overall site health
status (ok, warning, error) so that I can do frequent
polling without retrieving the full report.

**Why this priority**: Useful for high-frequency health
checks, but the full endpoint (US1) already provides all
the data needed. This is an optimization for specific
monitoring use cases.

**Independent Test**: Can be tested by requesting the
summary endpoint and verifying a single severity value
is returned representing the worst status across all
entries.

**Acceptance Scenarios**:

1. **Given** an authenticated user, **When** they request
   the summary endpoint, **Then** they receive a JSON
   response with a single overall severity value
   representing the highest severity across all entries.
2. **Given** a site with no warnings or errors, **When**
   the summary endpoint is requested, **Then** the overall
   severity is "ok".
3. **Given** a site with at least one error-level entry,
   **When** the summary endpoint is requested, **Then** the
   overall severity is "error".

---

### Edge Cases

- What happens when a status report checker produces an
  exception during data collection? The API MUST still
  return a valid JSON response, reporting the failing
  checker as an error-severity entry.
- What happens when the site has no status report entries
  at all (e.g., very early install state)? The API MUST
  return an empty array with a 200 status code.
- What happens when a status entry contains HTML markup
  in its value or description? The API MUST return the
  raw text or sanitized plain-text version, not HTML.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST expose a JSON endpoint that
  returns all Drupal status report entries as a flat list
  using an envelope structure: a `data` array containing
  the entries and a `meta` object with count and
  generation timestamp (ISO 8601).
- **FR-002**: Each status entry MUST include: machine_name
  (Drupal's internal key for the check), title, value,
  description, and severity (mapped to a human-readable
  string: ok, warning, error, info). Field names MUST
  use Drupal's native naming wherever available.
- **FR-003**: System MUST restrict access to users with
  a dedicated permission ("access sitepulse api").
- **FR-004**: System MUST support filtering status entries
  by one or more severity levels via query parameter.
- **FR-005**: System MUST expose a lightweight summary
  endpoint returning only the aggregate severity.
- **FR-006**: System MUST NOT expose sensitive data
  in status entry values or descriptions. Filtering MUST
  use a configurable denylist (by machine_name) stored in
  module settings, pre-populated with database-related
  keys as defaults (database_system, database_system_version).
  Note: Drupal status entries expose metadata, not raw
  credentials. The denylist is defense-in-depth.
  Administrators MUST be able to add or remove keys
  from the denylist via a standard config form.
- **FR-007**: Responses MUST be cached using Drupal's
  cache API with a time-based TTL (default ~60 seconds).
  Cache MUST also be cleared on `drush cr` / manual cache
  rebuild. No event-based invalidation (status checks
  have no change signal).
- **FR-008**: System MUST return proper HTTP error codes
  (403 for unauthorized, 400 for invalid parameters,
  200 for success).
- **FR-009**: System MUST support HTTP Basic Authentication
  via Drupal's `basic_auth` core module to enable
  non-browser clients (monitoring tools, scripts) to
  authenticate against the API.
- **FR-010**: API endpoints MUST follow the versioned path
  pattern `/api/sitepulse/v1/` with endpoints at
  `/api/sitepulse/v1/status`,
  `/api/sitepulse/v1/status/summary`, and
  `/api/sitepulse/v1/openapi.json`.
- **FR-011**: System MUST expose an OpenAPI 3.0+
  specification document at `/api/sitepulse/v1/openapi.json`
  that fully describes all available endpoints, request
  parameters, response schemas, and authentication
  requirements. The spec MUST be loadable in any external
  Swagger UI instance. The module does NOT bundle Swagger
  UI (no JavaScript per constitution II).

### Key Entities

- **StatusEntry**: A single item from the status report.
  Attributes: machine_name (string, unique stable
  identifier sourced from Drupal's internal key),
  title (string), value (string), description (string),
  severity (enumeration: ok, warning, error, info).
- **StatusSummary**: Aggregate health indicator. Attributes:
  overall severity (enumeration), count of entries per
  severity level.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Authenticated users can retrieve the full
  site status report in under 2 seconds on a standard
  Drupal installation.
- **SC-002**: Monitoring tools can poll the summary
  endpoint and receive a response in under 500
  milliseconds on a standard installation.
- **SC-003**: Unauthorized requests are rejected 100% of
  the time with no status data leaked.
- **SC-004**: The module adds zero database tables and
  zero JavaScript assets to the site.
- **SC-005**: All status report entries visible on the
  admin status page are also available via the API
  (complete data parity).

## Clarifications

### Session 2026-03-28

- Q: Should sensitive data filtering use a hardcoded denylist, configurable denylist, or allowlist? → A: Configurable denylist via module settings, pre-populated with sensible defaults.
- Q: How should non-browser clients authenticate? → A: Require `basic_auth` core module as dependency. Future: Keys module token support (out of scope for v1).
- Q: Should status entries include a stable unique identifier? → A: Yes, include machine_name from Drupal's internal key. Use Drupal's native names wherever available for consistency.
- Q: What JSON response structure should the API use? → A: Envelope with `data` + `meta` keys, mirroring Drupal's status report page structure for familiarity.
- Q: What URL path convention and should the API be OpenAPI compatible? → A: Versioned paths under `/api/sitepulse/v1/`. Expose OpenAPI 3.0+ spec at `/api/sitepulse/v1/openapi.json` for Swagger UI compatibility.
- Q: Which categories of status data should the default denylist block? → A: Only database credentials. Admins can extend the denylist via settings.
- Q: What level of request logging should the module provide? → A: None custom — rely on Drupal's existing watchdog/dblog for error-level events only.
- Q: What cache strategy should the API use? → A: Time-based TTL (e.g., 60s) via Drupal cache API. Status checks are expensive and have no change event.

## Future Enhancements

Items explicitly out of scope for v1, captured for future
planning:

- **Keys module integration**: Support the Keys contrib
  module to configure a token that can be passed as a
  header for API authentication (alternative to
  basic_auth).

## Assumptions

- The target audience is site administrators, DevOps
  engineers, and automated monitoring tools — not
  anonymous site visitors.
- The module will use Drupal's built-in permission system
  for access control, with `basic_auth` core module as a
  dependency for non-browser client authentication.
- Future versions may integrate with the Keys contrib
  module to support token-based header authentication,
  but this is out of scope for v1.
- The status report data is sourced from Drupal core's
  system requirements checking mechanism
  (`system.manager` service).
- Caching will use Drupal's standard cache API with a
  time-based TTL (~60s default) — no custom cache
  backends and no event-based invalidation.
- No custom logging — the module relies on Drupal's
  watchdog/dblog for error-level events. Web server
  access logs cover request-level observability.
- The API uses Drupal's routing and controller system,
  not the REST module or JSON:API module, to keep
  dependencies at zero contrib/core optional modules.
- Sensitive data filtering uses a configurable denylist
  in module settings, shipped with database-related keys
  (database_system, database_system_version) as defaults.
  Drupal status entries expose metadata, not raw
  credentials — the denylist is defense-in-depth. Admins
  can extend it without code changes.
