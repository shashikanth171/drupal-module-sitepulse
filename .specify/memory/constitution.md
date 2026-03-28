<!--
  Sync Impact Report
  ==================
  Version change: 1.1.0 → 1.1.1 (narrow Drupal version to 11.x only)
  Modified sections:
    - Technical Constraints: D10.3 dropped — RequirementSeverity
      enum is D11+; PHP bumped to 8.3+ (D11 requirement).
  Added sections: none
  Removed sections: none
  Templates requiring updates:
    - .specify/templates/plan-template.md ✅ no changes needed
    - .specify/templates/spec-template.md ✅ no changes needed
    - .specify/templates/tasks-template.md ✅ no changes needed
  Follow-up TODOs: none
-->

# SitePulse Constitution

## Core Principles

### I. Drupal Core First

All functionality MUST leverage Drupal core services,
APIs, and plugin systems. The module MUST NOT introduce
third-party PHP dependencies. If core provides a service
(e.g., `system.manager`, `extension.list.module`), it
MUST be used instead of custom implementations.

**Rationale**: Contrib modules that depend only on core
are easier to maintain across Drupal releases and carry
zero supply-chain risk for adopters.

### II. Minimal Footprint

The module MUST remain lightweight:
- No custom database tables unless strictly required.
- No custom entities or field types.
- Admin UI is limited to standard Drupal config forms
  (e.g., `ConfigFormBase` for module settings). No custom
  dashboards, widgets, or JavaScript-driven UIs.
- Every line of code MUST serve the module's single
  purpose: exposing site status data via API.

**Rationale**: A status-reporting API module has no
business adding weight to the system it monitors.

### III. API-Driven

The module's primary interface is a REST/JSON API that
exposes data from Drupal's status report (`/admin/reports/status`).
- Endpoints MUST return structured JSON.
- Responses MUST be cacheable via standard Drupal cache
  mechanisms.
- Access MUST be controlled by Drupal permissions.
- The API MUST NOT expose sensitive configuration values
  (database credentials, API keys, file system paths)
  unless explicitly opted in via configuration.

**Rationale**: Machine-readable status data enables
monitoring dashboards, fleet management, and automated
auditing without scraping admin pages.

### IV. Drupal Coding Standards

All code MUST follow Drupal coding standards and pass
`phpcs --standard=Drupal,DrupalPractice`. Code MUST be
compatible with the Drupal.org contrib ecosystem:
- PSR-4 autoloading under `src/`.
- Services declared in `sitepulse.services.yml`.
- Routes declared in `sitepulse.routing.yml`.
- Permissions declared in `sitepulse.permissions.yml`.
- PHPUnit tests under `tests/src/`.

**Rationale**: Compliance with Drupal.org standards is
a prerequisite for project acceptance and community trust.

## Technical Constraints

- **Drupal version**: 11.x only (uses RequirementSeverity
  enum introduced in 11.x; D10 support would require
  fallback code violating Minimal Footprint).
- **PHP version**: 8.3+ (Drupal 11.x core requirement).
- **No JavaScript**: The module has no frontend component.
- **No configuration entities**: Use simple config where
  needed (`sitepulse.settings`).
- **Security**: All endpoints MUST check permissions.
  Status data MUST be filtered to prevent information
  disclosure to unauthorized users.

## Development Workflow

- One feature per branch, branched from `main`.
- All changes MUST include or update relevant PHPUnit
  tests (Kernel or Functional as appropriate).
- Commit messages follow Drupal.org conventions:
  `Issue #NNN by user: Description.`
- Code review MUST verify constitution compliance before
  merge.

## Governance

This constitution is the authoritative guide for all
SitePulse development decisions. When a proposed change
conflicts with these principles, the constitution wins
unless formally amended.

**Amendment procedure**:
1. Propose the change with rationale.
2. Document the amendment in this file.
3. Increment the version per semver rules:
   - MAJOR: Principle removal or redefinition.
   - MINOR: New principle or section added.
   - PATCH: Clarification or wording fix.
4. Update `LAST_AMENDED_DATE`.

**Compliance**: Every PR/review MUST verify alignment
with these principles. Use the Constitution Check section
in plan documents as the gate.

**Version**: 1.1.1 | **Ratified**: 2026-03-28 | **Last Amended**: 2026-03-28
