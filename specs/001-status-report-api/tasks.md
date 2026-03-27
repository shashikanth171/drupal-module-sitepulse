# Tasks: Status Report API

**Input**: Design documents from `/specs/001-status-report-api/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/openapi.json

**Tests**: Included — constitution mandates PHPUnit tests for all changes.

**Organization**: Tasks grouped by user story for independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story (US1, US2, US3)
- Exact file paths under `web/modules/custom/sitepulse/`

---

## Phase 1: Setup (Module Scaffolding)

**Purpose**: Create the module skeleton and configuration files

- [x] T001 [P] Create module info file at web/modules/custom/sitepulse/sitepulse.info.yml with name, type, description, package, version, core_version_requirement (^11), dependencies (drupal:system, drupal:basic_auth, drupal:user)
- [x] T002 [P] Create permission declaration at web/modules/custom/sitepulse/sitepulse.permissions.yml with "access sitepulse api" permission (title, description, restrict access: true)
- [x] T003 [P] Create default config at web/modules/custom/sitepulse/config/install/sitepulse.settings.yml with cache_ttl: 60 and denylist: ['database_system', 'database_system_version']
- [x] T004 [P] Create config schema at web/modules/custom/sitepulse/config/schema/sitepulse.schema.yml defining sitepulse.settings as config_object with cache_ttl (integer) and denylist (sequence of strings) mappings with constraints
- [x] T005 [P] Create service declaration at web/modules/custom/sitepulse/sitepulse.services.yml declaring sitepulse.status_collector service with class, arguments (@system.manager, @cache.default, @config.factory, @datetime.time), and interface alias

**Checkpoint**: Module can be enabled with `drush en sitepulse -y` — no functionality yet, but Drupal recognizes it.

---

## Phase 2: Foundational (StatusCollector Service)

**Purpose**: Core service that ALL user stories depend on — MUST complete before any story phase

**CRITICAL**: No user story work can begin until this phase is complete

- [x] T006 Implement StatusCollector service at web/modules/custom/sitepulse/src/Service/StatusCollector.php — inject SystemManager, CacheBackendInterface, ConfigFactoryInterface, TimeInterface via constructor with ContainerInjectionInterface. Implement buildEntries() method: call SystemManager::listRequirements(), filter by denylist from config, convert TranslatableMarkup to plain strings, strip HTML from value/description, map RequirementSeverity enum to string (ok/warning/error/info — map Info to 'info' not 'checked'), cache result with TTL from config. Return array of StatusEntry arrays keyed by machine_name.
- [x] T007 Add getStatusEntries(?array $severityFilter = NULL) method to StatusCollector — calls buildEntries(), wraps in envelope structure with 'data' array and 'meta' object (count, generated ISO 8601 timestamp). No severity filtering logic yet (added in US2).
- [x] T008 Add getStatusSummary() method to StatusCollector — calls buildEntries(), computes overall_severity (highest: error > warning > info > ok), counts per severity, total. Returns envelope with 'data' and 'meta'.
- [x] T009 Write Kernel test at web/modules/custom/sitepulse/tests/src/Kernel/StatusCollectorTest.php — test buildEntries() returns expected structure, test denylist filtering excludes entries, test severity mapping converts RequirementSeverity enum correctly, test HTML stripping in value/description fields, test cache TTL is applied, test empty requirements returns empty array.

**Checkpoint**: Foundation ready — StatusCollector tested in isolation. User story implementation can begin.

---

## Phase 3: User Story 1 - Retrieve Full Site Status (Priority: P1) MVP

**Goal**: Authenticated users can retrieve the full status report as JSON at `/api/sitepulse/v1/status`

**Independent Test**: `curl -u admin:password https://site/api/sitepulse/v1/status` returns JSON with all status entries

### Implementation for User Story 1

- [x] T010 [P] [US1] Create routing file at web/modules/custom/sitepulse/sitepulse.routing.yml with route sitepulse.status: path '/api/sitepulse/v1/status', defaults _controller '\Drupal\sitepulse\Controller\StatusController::status', requirements _permission 'access sitepulse api', options _auth [basic_auth, cookie]
- [x] T011 [US1] Implement StatusController at web/modules/custom/sitepulse/src/Controller/StatusController.php — implement ContainerInjectionInterface, inject StatusCollector via create(). Add status(Request $request) method: call StatusCollector::getStatusEntries() (no filter yet), return new JsonResponse with 200 status code. Handle edge case: if no entries, return empty data array with 200.
- [x] T012 [US1] Write Functional test at web/modules/custom/sitepulse/tests/src/Functional/StatusEndpointTest.php — extend BrowserTestBase, enable modules [sitepulse, basic_auth]. Test: authenticated user with permission gets 200 + JSON with data/meta envelope. Test: user without permission gets 403. Test: response contains machine_name, title, value, description, severity for each entry. Test: severity values are valid strings (ok/warning/error/info). Test: meta contains count (integer) and generated (ISO 8601 string). Test: basic_auth works — create user, send request with Authorization: Basic header, verify 200 response (FR-009).

**Checkpoint**: User Story 1 fully functional — the MVP endpoint works end-to-end.

---

## Phase 4: User Story 2 - Filter Status by Severity (Priority: P2)

**Goal**: Monitoring tools can filter status entries by severity level via query parameter

**Independent Test**: `curl -u admin:password "https://site/api/sitepulse/v1/status?severity=error,warning"` returns only matching entries

### Implementation for User Story 2

- [x] T013 [US2] Add severity filter logic to StatusController::status() in web/modules/custom/sitepulse/src/Controller/StatusController.php — parse ?severity query parameter (comma-separated string), validate each value against allowed set (ok, warning, error, info), return 400 JsonResponse with ErrorResponse structure if invalid. Pass validated filter array to StatusCollector::getStatusEntries().
- [x] T014 [US2] Update StatusCollector::getStatusEntries() in web/modules/custom/sitepulse/src/Service/StatusCollector.php — when $severityFilter is not NULL, filter the cached entries to include only those matching the specified severity values before building the envelope response. Update meta.count to reflect filtered count.
- [x] T015 [US2] Add severity filter tests to web/modules/custom/sitepulse/tests/src/Functional/StatusEndpointTest.php — Test: ?severity=error returns only error entries. Test: ?severity=error,warning returns entries matching either. Test: ?severity=invalid returns 400 with error message. Test: ?severity= (empty) returns all entries. Test: filtered response meta.count matches actual data array length.

**Checkpoint**: User Stories 1 AND 2 both work independently. Filtering does not break the unfiltered endpoint.

---

## Phase 5: User Story 3 - Summary Health Check (Priority: P3)

**Goal**: Uptime monitoring services can poll a lightweight summary endpoint for overall site health

**Independent Test**: `curl -u admin:password https://site/api/sitepulse/v1/status/summary` returns overall severity + counts

### Implementation for User Story 3

- [x] T016 [P] [US3] Add summary route to web/modules/custom/sitepulse/sitepulse.routing.yml — sitepulse.status_summary: path '/api/sitepulse/v1/status/summary', defaults _controller '\Drupal\sitepulse\Controller\StatusController::summary', requirements _permission 'access sitepulse api', options _auth [basic_auth, cookie]
- [x] T017 [US3] Add summary() method to StatusController in web/modules/custom/sitepulse/src/Controller/StatusController.php — call StatusCollector::getStatusSummary(), return JsonResponse with SummaryResponse structure (data.overall_severity, data.counts, data.total, meta.generated).
- [x] T018 [US3] Add summary endpoint tests to web/modules/custom/sitepulse/tests/src/Functional/StatusEndpointTest.php — Test: authenticated request returns 200 + JSON with overall_severity string. Test: response includes counts object with ok/info/warning/error integer keys. Test: total equals sum of all counts. Test: overall_severity reflects worst entry (if any error entry exists, overall is 'error'). Test: user without permission gets 403.

**Checkpoint**: All three user stories independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: OpenAPI endpoint, settings form, admin menu, edge case hardening

- [x] T019 [P] Copy OpenAPI spec from specs/001-status-report-api/contracts/openapi.json to web/modules/custom/sitepulse/openapi.json (static file shipped with module)
- [x] T020 [P] Add OpenAPI route to web/modules/custom/sitepulse/sitepulse.routing.yml — sitepulse.openapi: path '/api/sitepulse/v1/openapi.json', defaults _controller '\Drupal\sitepulse\Controller\StatusController::openapi', requirements _access 'TRUE' (public, no auth required)
- [x] T021 Add openapi() method to StatusController in web/modules/custom/sitepulse/src/Controller/StatusController.php — read the static openapi.json file from module directory using \Drupal::service('extension.list.module')->getPath('sitepulse'), return JsonResponse with content-type application/json
- [x] T022 [P] Implement SettingsForm at web/modules/custom/sitepulse/src/Form/SettingsForm.php — extend ConfigFormBase, getEditableConfigNames returns ['sitepulse.settings']. buildForm: textarea for denylist (one machine name per line — join array to newlines for display), number field for cache_ttl. submitForm: split textarea newlines back to array for config storage. Add validation: cache_ttl >= 0, denylist entries are valid machine names (lowercase alphanumeric + underscore).
- [x] T023 [P] Create admin menu link at web/modules/custom/sitepulse/sitepulse.links.menu.yml — sitepulse.settings route under system.admin_config_system, title 'SitePulse', description 'Configure SitePulse API settings'
- [x] T024 [P] Add settings form route to web/modules/custom/sitepulse/sitepulse.routing.yml — sitepulse.settings: path '/admin/config/system/sitepulse', defaults _form '\Drupal\sitepulse\Form\SettingsForm' _title 'SitePulse Settings', requirements _permission 'administer site configuration'
- [x] T025 Add edge case handling to StatusCollector::buildEntries() in web/modules/custom/sitepulse/src/Service/StatusCollector.php — wrap SystemManager::listRequirements() in try/catch: if a checker throws exception, include it as an error-severity entry with machine_name 'sitepulse_collection_error'. Ensure entries with HTML in value/description are stripped to plain text using \Drupal\Component\Utility\Html::decodeEntities() and strip_tags().
- [x] T026 Add edge case and settings form tests — in Kernel test: verify exception during collection produces error entry. In Functional test: verify OpenAPI endpoint returns valid JSON with 200. Verify settings form is accessible and saves config correctly.
- [x] T027 Run quickstart.md validation — enable module on DDEV site, run all curl commands from quickstart.md, verify expected responses match. Run full test suite: `php web/core/scripts/run-tests.sh --module sitepulse`
- [x] T028 [P] Run phpcs coding standards check — execute `phpcs --standard=Drupal,DrupalPractice web/modules/custom/sitepulse/` and fix any violations (constitution IV compliance)
- [x] T029 [P] Basic performance validation — time the /status and /status/summary endpoints with `curl -w '%{time_total}'`. Verify /status < 2s (SC-001) and /status/summary < 500ms (SC-002) on the DDEV site. Log results; no automated enforcement needed.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on T005 (service declaration) — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Phase 2 completion
- **User Story 2 (Phase 4)**: Depends on Phase 3 (extends controller + service from US1)
- **User Story 3 (Phase 5)**: Depends on Phase 2 completion — can run in parallel with US2
- **Polish (Phase 6)**: Depends on all user stories being complete

### User Story Dependencies

- **US1 (P1)**: Requires Phase 2 — no dependency on other stories
- **US2 (P2)**: Requires US1 (extends the status() method and service)
- **US3 (P3)**: Requires Phase 2 — independent of US1/US2 (different route + method)

### Within Each User Story

- Routes before controllers
- Service methods before controller methods that call them
- Implementation before tests
- Commit after each task or logical group

### Parallel Opportunities

- All Phase 1 tasks (T001-T005) can run in parallel
- T010 (US1 route) can run in parallel with Phase 1 tasks
- T016 (US3 route) can run in parallel with US1/US2 implementation
- T019, T022, T023, T024 (Polish) can all run in parallel
- US2 and US3 can partially overlap (US3 doesn't depend on US2)

---

## Parallel Example: Phase 1

```bash
# Launch all setup tasks together:
Task: "T001 Create sitepulse.info.yml"
Task: "T002 Create sitepulse.permissions.yml"
Task: "T003 Create sitepulse.settings.yml"
Task: "T004 Create sitepulse.schema.yml"
Task: "T005 Create sitepulse.services.yml"
```

## Parallel Example: Phase 6

```bash
# Launch independent polish tasks together:
Task: "T019 Copy openapi.json"
Task: "T022 Implement SettingsForm"
Task: "T023 Create menu link"
Task: "T024 Add settings route"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001-T005)
2. Complete Phase 2: Foundational (T006-T009)
3. Complete Phase 3: User Story 1 (T010-T012)
4. **STOP and VALIDATE**: `curl -u admin:password https://site/api/sitepulse/v1/status`
5. Deploy/demo if ready — this is the MVP

### Incremental Delivery

1. Setup + Foundational → Module enabled, service tested
2. Add US1 → Full status endpoint works → **MVP**
3. Add US2 → Severity filtering works
4. Add US3 → Summary endpoint works
5. Polish → OpenAPI, settings form, edge cases, quickstart validation

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story
- Module path: `web/modules/custom/sitepulse/`
- PSR-4 namespace: `Drupal\sitepulse`
- Test namespace: `Drupal\Tests\sitepulse\{Kernel,Functional}`
- Constitution v1.1.0 compliance verified in plan.md
- Total: 29 tasks across 6 phases
