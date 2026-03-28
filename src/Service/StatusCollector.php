<?php

declare(strict_types=1);

namespace Drupal\sitepulse\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\system\SystemManager;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Collects, filters, caches, and structures status report data.
 */
class StatusCollector {

  /**
   * Severity weight mapping for summary computation.
   *
   * Higher weight = worse severity.
   */
  protected const SEVERITY_WEIGHTS = [
    'ok' => 0,
    'info' => 1,
    'warning' => 2,
    'error' => 3,
  ];

  /**
   * Constructs a StatusCollector.
   */
  public function __construct(
    protected readonly SystemManager $systemManager,
    protected readonly CacheBackendInterface $cache,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Builds and returns filtered status entries.
   *
   * Results are cached with a configurable TTL.
   *
   * @return array
   *   Array of status entry arrays, each with keys: machine_name, title,
   *   value, description, severity.
   */
  public function buildEntries(): array {
    $cached = $this->cache->get('sitepulse:status');
    if ($cached) {
      return $cached->data;
    }

    $config = $this->configFactory->get('sitepulse.settings');
    $denylist = $config->get('denylist') ?? [];

    try {
      $requirements = $this->systemManager->listRequirements();
    }
    catch (\Exception $e) {
      return [
        [
          'machine_name' => 'sitepulse_collection_error',
          'title' => 'SitePulse Collection Error',
          'value' => $e->getMessage(),
          'description' => NULL,
          'severity' => 'error',
        ],
      ];
    }

    $entries = [];
    foreach ($requirements as $key => $requirement) {
      if (in_array($key, $denylist, TRUE)) {
        continue;
      }

      $entries[] = [
        'machine_name' => (string) $key,
        'title' => $this->toString($requirement['title'] ?? ''),
        'value' => $this->toPlainText($requirement['value'] ?? ''),
        'description' => isset($requirement['description']) ? $this->toPlainText($requirement['description']) : NULL,
        'severity' => $this->mapSeverity($requirement['severity'] ?? NULL),
      ];
    }

    $ttl = (int) ($config->get('cache_ttl') ?? 60);
    if ($ttl > 0) {
      $expire = $this->time->getRequestTime() + $ttl;
      $this->cache->set('sitepulse:status', $entries, $expire);
    }

    return $entries;
  }

  /**
   * Returns status entries wrapped in an envelope structure.
   *
   * @param array|null $severityFilter
   *   Optional array of severity strings to filter by.
   *
   * @return array
   *   Envelope with 'data' and 'meta' keys.
   */
  public function getStatusEntries(?array $severityFilter = NULL): array {
    $entries = $this->buildEntries();

    if ($severityFilter !== NULL) {
      $entries = array_values(array_filter($entries, function (array $entry) use ($severityFilter): bool {
        return in_array($entry['severity'], $severityFilter, TRUE);
      }));
    }

    return [
      'data' => $entries,
      'meta' => [
        'count' => count($entries),
        'generated' => gmdate('Y-m-d\TH:i:s+00:00', $this->time->getRequestTime()),
      ],
    ];
  }

  /**
   * Returns aggregate severity summary.
   *
   * @return array
   *   Envelope with 'data' (overall_severity, counts, total) and 'meta'.
   */
  public function getStatusSummary(): array {
    $entries = $this->buildEntries();

    $counts = ['ok' => 0, 'info' => 0, 'warning' => 0, 'error' => 0];
    $worstWeight = -1;
    $overallSeverity = 'ok';

    foreach ($entries as $entry) {
      $severity = $entry['severity'];
      if (isset($counts[$severity])) {
        $counts[$severity]++;
      }
      $weight = self::SEVERITY_WEIGHTS[$severity] ?? 0;
      if ($weight > $worstWeight) {
        $worstWeight = $weight;
        $overallSeverity = $severity;
      }
    }

    return [
      'data' => [
        'overall_severity' => $overallSeverity,
        'counts' => $counts,
        'total' => count($entries),
      ],
      'meta' => [
        'generated' => gmdate('Y-m-d\TH:i:s+00:00', $this->time->getRequestTime()),
      ],
    ];
  }

  /**
   * Maps RequirementSeverity enum to API string.
   */
  protected function mapSeverity(mixed $severity): string {
    if ($severity instanceof RequirementSeverity) {
      return match ($severity) {
        RequirementSeverity::OK => 'ok',
        RequirementSeverity::Info => 'info',
        RequirementSeverity::Warning => 'warning',
        RequirementSeverity::Error => 'error',
      };
    }

    // Handle legacy integer values.
    if (is_int($severity)) {
      return match ($severity) {
        0 => 'ok',
        -1 => 'info',
        1 => 'warning',
        2 => 'error',
        default => 'info',
      };
    }

    return 'info';
  }

  /**
   * Converts a value to a plain string.
   */
  protected function toString(mixed $value): string {
    if ($value instanceof TranslatableMarkup || $value instanceof MarkupInterface) {
      return (string) $value;
    }
    return (string) $value;
  }

  /**
   * Converts a value to plain text, stripping HTML.
   */
  protected function toPlainText(mixed $value): string {
    $text = $this->toString($value);
    $text = Html::decodeEntities(strip_tags($text));
    return trim($text);
  }

}
