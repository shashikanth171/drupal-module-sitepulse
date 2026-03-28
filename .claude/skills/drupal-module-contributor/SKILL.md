---
name: drupal-module-contributor
description: >
  Expert Drupal 10/11 module development workflow for building, debugging, and extending
  custom Drupal modules using Claude Code. Use this skill whenever the user is working on
  a Drupal custom module — including creating config entities, writing Drupal services,
  building Drush commands, generating SQL views/reporting datasets, exposing data to Drupal
  Views, fixing PHP/Drupal API bugs, or scaffolding module structure. Trigger this skill for
  any task involving Drupal module code, .info.yml, .services.yml, .routing.yml, entity
  annotations, form classes, hook_views_data(), multilingual content handling, or Drupal-
  specific patterns like dependency injection via create(), EntityForm, ConfigEntityBase,
  FieldManager, or plugin-based architectures. Also trigger when the user asks to "build a
  Drupal service", "add a Drush command", "create a config entity", "fix a Drupal error",
  "generate SQL for Drupal content types", "expose to Views", "release on Drupal.org", or
  "prepare a contrib module".
---

# Drupal Module Contributor Skill

A skill for building production-quality Drupal 10/11 custom modules with Claude Code,
following Drupal coding standards, best practices, and common architectural patterns.

---

## Phase 1 — Orient Before Coding

Before writing any code, always orient yourself in the project:

```bash
# Locate the module
ls docroot/modules/custom/   # or web/modules/custom/
ls docroot/web/modules/custom/

# Read existing module files
cat <module>.info.yml
cat <module>.services.yml
cat <module>.routing.yml
```

**Read ALL existing files** in the module before suggesting new code. Use `Task/Explore`
or sequential `Read` calls to build a complete mental model. Identify:

- Config entity classes and their interfaces
- Registered services and their constructor dependencies
- Existing Drush commands
- Form classes and their DI patterns
- Any naming conflicts with core Drupal interfaces

> **Critical:** Never assume a module is empty. Check first — the module is often
> already substantially built and needs fixing or extension, not rewriting.

---

## Phase 2 — Bug Detection Checklist

Run this checklist on every entity/service/form before writing new code:

### Config Entity (`ConfigEntityBase` subclass)
- [ ] Does the entity define custom methods that **conflict** with core `EntityInterface`
  or `EntityBase`? Key conflicts to watch for:
  - `getEntityType()` — conflicts with `EntityInterface::getEntityType(): EntityTypeInterface`
  - `getBundle()` — conflicts with `EntityBase::getBundle(): string`
  - `id()`, `label()`, `uuid()` — already defined by core
- [ ] Rename conflicting methods using the domain prefix pattern:
  - `getEntityType()` → `getSourceEntityTypeId(): string`
  - `getBundle()` → `getSourceBundle(): string`
  - `getOutputType()` → `getOutputMode(): string`

### Form Classes (`EntityForm`, `FormBase`, `ConfigFormBase`)
- [ ] Properties injected via DI must **NOT** be declared `readonly` if the form may
  be serialized. PHP fatal: *"Cannot initialize readonly property from scope FormBase
  during unserialize"*. Use standard (non-readonly) typed properties.
- [ ] `create()` factory must use `$container->get()` for every injected service.
- [ ] `__construct()` must call `parent::__construct()` where applicable.

### Services (`services.yml`)
- [ ] Every service argument (`@service.id`) must match a real registered service ID.
- [ ] Injecting `@entity_field.manager`, `@entity_type.bundle.info`, `@database` are
  common in field-heavy modules — verify exact IDs.

### Interface Compliance
- [ ] After renaming entity methods, `grep` all callers across the module for the old
  method names and update them all in one pass.

---

## Phase 3 — Standard Module Architecture

### Recommended directory structure

```
<module_name>/
├── <module_name>.info.yml
├── <module_name>.module
├── <module_name>.services.yml
├── <module_name>.routing.yml
├── <module_name>.links.menu.yml
├── <module_name>.permissions.yml
├── drush.services.yml
├── config/
│   └── schema/<module_name>.schema.yml
└── src/
    ├── Entity/
    │   ├── MyEntity.php              # extends ConfigEntityBase
    │   └── MyEntityInterface.php     # extends ConfigEntityInterface
    ├── Form/
    │   ├── MyEntityForm.php          # extends EntityForm
    │   └── MyEntityDeleteForm.php
    ├── MyEntityListBuilder.php       # extends ConfigEntityListBuilder
    ├── Service/
    │   ├── MyService.php
    │   └── MyServiceInterface.php
    └── Commands/
        └── MyModuleCommands.php      # Drush commands
```

---

## Phase 4 — Config Entity Pattern

### Interface

```php
<?php
namespace Drupal\my_module\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface MyEntityInterface extends ConfigEntityInterface {
  // Use domain-prefixed names — never shadow core interface methods
  public function getSourceEntityTypeId(): string;
  public function getSourceBundle(): string;
  public function getOutputMode(): string;
}
```

### Entity class

```php
<?php
namespace Drupal\my_module\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ConfigEntityType(
  id: 'my_entity',
  label: new TranslatableMarkup('My Entity'),
  entity_keys: ['id' => 'id', 'label' => 'label'],
  handlers: [
    'list_builder' => MyEntityListBuilder::class,
    'form' => ['add' => MyEntityForm::class, 'edit' => MyEntityForm::class, 'delete' => MyEntityDeleteForm::class],
    'route_provider' => ['html' => 'Drupal\Core\Entity\Routing\AdminHtmlRouteProvider'],
  ],
  links: [
    'collection'  => '/admin/config/my-module/entities',
    'add-form'    => '/admin/config/my-module/entities/add',
    'edit-form'   => '/admin/config/my-module/entities/{my_entity}/edit',
    'delete-form' => '/admin/config/my-module/entities/{my_entity}/delete',
  ],
  config_prefix: 'my_entity',
  admin_permission: 'administer my_module',
)]
class MyEntity extends ConfigEntityBase implements MyEntityInterface {
  protected string $id;
  protected string $label;
  protected string $source_entity_type = '';
  protected string $source_bundle = '';
  protected string $output_mode = 'view';

  public function getSourceEntityTypeId(): string { return $this->source_entity_type; }
  public function getSourceBundle(): string { return $this->source_bundle; }
  public function getOutputMode(): string { return $this->output_mode; }
}
```

---

## Phase 5 — Service Pattern

### Interface

```php
interface MyServiceInterface {
  public function doThing(MyEntityInterface $entity): string;
}
```

### Class with DI

```php
class MyService implements MyServiceInterface {
  public function __construct(
    private readonly Connection $database,
    private readonly EntityFieldManagerInterface $fieldManager,
  ) {}

  // Implement interface methods...
}
```

### services.yml registration

```yaml
services:
  my_module.my_service:
    class: Drupal\my_module\Service\MyService
    arguments:
      - '@database'
      - '@entity_field.manager'
```

---

## Phase 6 — SQL View / Reporting Patterns for Drupal

When generating SQL for Drupal content (node-based reporting, flattened datasets):

### Base query shape

```sql
SELECT
  n.nid,
  n.uuid       AS node_uuid,
  n.title      AS node_title,
  n.status     AS node_status,
  n.langcode,
  n.created,
  n.changed,
  f_country.field_country_value AS field_country
FROM node_field_data n
LEFT JOIN node__field_country f_country
  ON f_country.entity_id = n.nid
  AND f_country.langcode  = n.langcode
  AND f_country.deleted   = 0
WHERE n.type = 'article'
  AND n.default_langcode = 1
```

### Three-tier paragraph JOIN chain

```sql
-- Tier 1: node → paragraph host reference (delta=0 → one row per node)
LEFT JOIN node__field_content hp_field_content
  ON  hp_field_content.entity_id = n.nid
  AND hp_field_content.delta     = 0
  AND hp_field_content.langcode  = n.langcode
  AND hp_field_content.deleted   = 0

-- Tier 2: paragraph revision tracking
LEFT JOIN paragraphs_item_field_data pid_field_content
  ON  pid_field_content.id       = hp_field_content.field_content_target_id
  AND pid_field_content.revision_id = hp_field_content.field_content_target_revision_id
  AND pid_field_content.status   = 1

-- Tier 3: paragraph subfield
LEFT JOIN paragraph__field_body pf_field_content_field_body
  ON  pf_field_content_field_body.entity_id    = pid_field_content.id
  AND pf_field_content_field_body.revision_id  = pid_field_content.revision_id
  AND pf_field_content_field_body.deleted      = 0
```

**Rules:**
- Always `delta = 0` on the host reference join to guarantee one row per node
- Always join `paragraphs_item_field_data` (Tier 2) — needed for `status = 1` filter
  and revision-safe join
- Alias columns unambiguously: `{paragraph_field}_{sub_field}`
- Never join the same table twice — track joined aliases

### Multilingual datasets

When the site uses content translation, the base query returns one row per language
by default. To control this:

```sql
-- Default language only (one row per node)
WHERE n.default_langcode = 1

-- Specific language
WHERE n.langcode = 'fr'

-- All translations (one row per node per language)
-- Omit the langcode filter; group by n.nid, n.langcode in aggregations
```

Always propagate `n.langcode` into field table joins:
```sql
LEFT JOIN node__field_title_local f_title
  ON f_title.entity_id = n.nid
  AND f_title.langcode  = n.langcode   -- ← critical for translated field values
  AND f_title.deleted   = 0
```

Expose `langcode` as a column in the dataset so BI tools can filter by language.

---

## Phase 7 — Drush Commands Pattern

```php
<?php
namespace Drupal\my_module\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class MyModuleCommands extends DrushCommands {

  public function __construct(
    private readonly MyServiceInterface $myService,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'mymodule:list', aliases: ['mml'])]
  #[CLI\FieldLabels(labels: ['id' => 'ID', 'label' => 'Label', 'status' => 'Status'])]
  #[CLI\DefaultTableFields(fields: ['id', 'label', 'status'])]
  public function listEntities(array $options = ['format' => 'table']): RowsOfFields {
    $entities = $this->entityTypeManager->getStorage('my_entity')->loadMultiple();
    $rows = [];
    foreach ($entities as $entity) {
      $rows[$entity->id()] = [
        'id'     => $entity->id(),
        'label'  => $entity->label(),
        'status' => $entity->status() ? 'enabled' : 'disabled',
      ];
    }
    return new RowsOfFields($rows);
  }

  #[CLI\Command(name: 'mymodule:rebuild', aliases: ['mmr'])]
  #[CLI\Argument(name: 'entity_id', description: 'Entity machine name')]
  #[CLI\Option(name: 'dry-run', description: 'Preview without executing')]
  public function rebuild(string $entity_id, array $options = ['dry-run' => false]): void {
    $entity = $this->entityTypeManager->getStorage('my_entity')->load($entity_id);
    if (!$entity) {
      throw new \InvalidArgumentException("Entity '$entity_id' not found.");
    }
    if ($options['dry-run']) {
      $this->io()->note('Dry-run mode — no changes made.');
      return;
    }
    $this->myService->rebuild($entity);
    $this->io()->success("Rebuilt '$entity_id'.");
  }
}
```

### drush.services.yml

```yaml
services:
  my_module.commands:
    class: Drupal\my_module\Commands\MyModuleCommands
    arguments:
      - '@my_module.my_service'
      - '@entity_type.manager'
    tags:
      - { name: drush.command }
```

---

## Phase 8 — Field Discovery (FieldDetectorService pattern)

When auto-detecting Drupal fields for a bundle:

```php
public function getFieldsForBundle(string $entityTypeId, string $bundle): array {
  $fields = [];
  $fieldDefs = $this->fieldManager->getFieldDefinitions($entityTypeId, $bundle);

  foreach ($fieldDefs as $fieldName => $def) {
    $storageType = $def->getType();
    $isBaseField  = $def instanceof BaseFieldDefinition;

    // Resolve storage table
    $storageTable = $isBaseField
      ? "{$entityTypeId}_field_data"   // e.g. node_field_data
      : "{$entityTypeId}__{$fieldName}"; // e.g. node__field_body

    $entry = [
      'label'              => (string) $def->getLabel(),
      'field_type'         => $storageType,
      'storage_table'      => $storageTable,
      'is_base_field'      => $isBaseField,
      'is_required'        => $def->isRequired(),
      'is_reference'       => $def instanceof FieldDefinitionInterface
                               && str_contains($storageType, 'entity_reference'),
      'target_entity_type' => null,
      'target_bundles'     => [],
      'is_paragraph_host'  => false,
    ];

    if ($entry['is_reference']) {
      $settings = $def->getSettings();
      $entry['target_entity_type'] = $settings['target_type'] ?? null;
      $entry['target_bundles']     = array_keys($settings['handler_settings']['target_bundles'] ?? []);
      $entry['is_paragraph_host']  = ($entry['target_entity_type'] === 'paragraph');
    }

    $fields[$fieldName] = $entry;
  }

  return $fields;
}
```

**Key rules:**
- Skip base fields from the configurable UI (they're always included in SELECT)
- `is_paragraph_host = true` triggers the three-tier JOIN chain (Phase 6)
- `target_entity_type` drives nested field discovery for paragraph subfields

---

## Phase 9 — Drupal Views Integration

To expose a custom reporting table or SQL view to Drupal Views (enabling CSV export,
filtered dashboards, and Views-based reporting):

### hook_views_data()

```php
// In <module>.views.inc or <module>.module
function my_module_views_data(): array {
  $data = [];

  $data['reporting_dataset_my_report']['table']['group'] = t('My Report Dataset');
  $data['reporting_dataset_my_report']['table']['base'] = [
    'field' => 'nid',
    'title' => t('My Report Dataset'),
    'help'  => t('Flattened reporting dataset for My Report.'),
  ];

  // Expose individual columns as Views fields
  $data['reporting_dataset_my_report']['nid'] = [
    'title' => t('Node ID'),
    'help'  => t('The node ID.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort'   => ['id' => 'standard'],
  ];

  $data['reporting_dataset_my_report']['node_title'] = [
    'title' => t('Title'),
    'help'  => t('Node title.'),
    'field'  => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort'   => ['id' => 'standard'],
  ];

  $data['reporting_dataset_my_report']['langcode'] = [
    'title' => t('Language'),
    'help'  => t('Content language.'),
    'field'  => ['id' => 'standard'],
    'filter' => ['id' => 'language'],
  ];

  return $data;
}
```

**Key rules:**
- Register `hook_views_data()` in `<module>.views.inc` and declare it in `<module>.module`
  via `function my_module_views_data() { return my_module_views_data_alter(); }` — or
  simply implement it directly in `.module`
- The `table.base.field` must be the primary key of your table/view (`nid` typically)
- Each column needs at minimum a `field` handler; add `filter` and `sort` for usability
- After implementing, run `drush cr` and check **Admin → Structure → Views → Add view**
  to confirm the base table appears in the "Show" dropdown
- For CSV/data exports: Views Data Export module handles this automatically once the
  base table is registered — no extra code needed

---

## Phase 10 — Drupal.org Contrib Module Workflow

When preparing a module for open-source release on Drupal.org:

### README.md structure
```markdown
# Module Name

One-sentence description.

## Requirements
- Drupal 10 or 11
- [dependency module] (drupal.org/project/...)

## Installation
1. `composer require drupal/module_name`
2. Enable: `drush en module_name`
3. Configure at: Admin → Config → ...

## Usage
Brief usage instructions.

## Drush Commands
| Command | Description |
|---------|-------------|
| `drush mymodule:list` | List all configured entities |
| `drush mymodule:rebuild {id}` | Rebuild dataset |

## Architecture
Brief note on key services/classes for contributors.

## Roadmap
- [ ] Feature planned

## Maintainers
- username (drupal.org/u/username)
```

### Release tagging conventions
```bash
# Alpha — API not stable
git tag 1.0.0-alpha1
git push origin 1.0.0-alpha1

# Beta — feature complete, API stabilizing
git tag 1.0.0-beta1

# Release candidate
git tag 1.0.0-rc1

# Stable
git tag 1.0.0
```

### config/schema/<module>.schema.yml (required for contrib)
Every config entity must have a schema — otherwise `drush cex` produces warnings and
config import may fail on other sites:

```yaml
my_module.my_entity.*:
  type: config_entity
  label: 'My Entity'
  mapping:
    id:
      type: string
      label: 'Machine name'
    label:
      type: label
      label: 'Label'
    source_entity_type:
      type: string
      label: 'Source entity type'
    output_mode:
      type: string
      label: 'Output mode'
```

**Key rules:**
- Schema file is mandatory for Drupal.org project approval
- Run `drush config:status` on a clean install to verify no schema warnings
- Add `package: Reporting` (or relevant category) to `.info.yml` for discoverability

---



| Need | Service / Method |
|------|-----------------|
| Load entities | `\Drupal::entityTypeManager()->getStorage('node')->load($id)` |
| Field definitions | `\Drupal::service('entity_field.manager')->getFieldDefinitions($type, $bundle)` |
| Bundle info | `\Drupal::service('entity_type.bundle.info')->getBundleInfo('node')` |
| Raw DB query | `\Drupal::database()->query('SELECT ...')` |
| Config entity storage | `\Drupal::entityTypeManager()->getStorage('my_entity')->loadMultiple()` |
| Cache invalidate | `\Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list'])` |
| Module path | `\Drupal::service('extension.list.module')->getPath('my_module')` |

---

## Phase 11 — Common Drupal API Quick Reference

| Need | Service / Method |
|------|-----------------|
| Load entities | `\Drupal::entityTypeManager()->getStorage('node')->load($id)` |
| Field definitions | `\Drupal::service('entity_field.manager')->getFieldDefinitions($type, $bundle)` |
| Bundle info | `\Drupal::service('entity_type.bundle.info')->getBundleInfo('node')` |
| Raw DB query | `\Drupal::database()->query('SELECT ...')` |
| Config entity storage | `\Drupal::entityTypeManager()->getStorage('my_entity')->loadMultiple()` |
| Cache invalidate | `\Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list'])` |
| Module path | `\Drupal::service('extension.list.module')->getPath('my_module')` |

---

## Phase 12 — Final Verification Workflow

After any set of edits:

```bash
# 1. Check for stale method name references
grep -r "oldMethodName\(\)" docroot/modules/custom/<module>/ --include="*.php"

# 2. Confirm interface implementations
grep -r "implements " docroot/modules/custom/<module>/src --include="*.php"

# 3. Check services.yml is consistent
cat docroot/modules/custom/<module>/<module>.services.yml

# 4. PHP syntax check (if php available)
find docroot/modules/custom/<module>/src -name "*.php" \
  -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

# 5. Rebuild Drupal caches after structural changes
# (run inside Drupal environment)
# drush cr
```

---

## Patterns to Avoid

- ❌ Declaring entity methods that shadow `EntityInterface` or `EntityBase` methods
- ❌ `readonly` properties in forms that get serialized (use regular typed properties)
- ❌ Calling `getOutputType()` / `getEntityType()` on your custom entity without verifying
  these aren't core interface methods
- ❌ Joining the same DB table alias twice in a SQL query
- ❌ Skipping `paragraphs_item_field_data` in paragraph joins (breaks revision safety)
- ❌ Writing a whole new service when one already exists — always read first
- ❌ Adding `use` imports in the same namespace (PHP doesn't need them)
