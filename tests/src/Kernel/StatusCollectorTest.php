<?php

declare(strict_types=1);

namespace Drupal\Tests\sitepulse\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\sitepulse\Service\StatusCollector;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the StatusCollector service.
 */
#[Group('sitepulse')]
class StatusCollectorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'sitepulse',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['sitepulse']);
  }

  /**
   * Tests that buildEntries() returns expected structure.
   */
  public function testBuildEntriesReturnsExpectedStructure(): void {
    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);
    $entries = $collector->buildEntries();

    $this->assertIsArray($entries);
    // Drupal's system module should provide at least some status entries.
    if (!empty($entries)) {
      $first = reset($entries);
      $this->assertArrayHasKey('machine_name', $first);
      $this->assertArrayHasKey('title', $first);
      $this->assertArrayHasKey('value', $first);
      $this->assertArrayHasKey('description', $first);
      $this->assertArrayHasKey('severity', $first);
      $this->assertIsString($first['machine_name']);
      $this->assertIsString($first['title']);
      $this->assertIsString($first['value']);
      $this->assertContains($first['severity'], ['ok', 'info', 'warning', 'error']);
    }
  }

  /**
   * Tests that denylist filtering excludes entries.
   */
  public function testDenylistFilteringExcludesEntries(): void {
    // Set a denylist that includes a known entry.
    $config = $this->config('sitepulse.settings');
    $config->set('denylist', ['php'])->save();

    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);
    $entries = $collector->buildEntries();

    $machineNames = array_column($entries, 'machine_name');
    $this->assertNotContains('php', $machineNames, 'Denylisted entry "php" should be excluded.');
  }

  /**
   * Tests severity mapping from RequirementSeverity enum.
   */
  public function testSeverityMapping(): void {
    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);
    $entries = $collector->buildEntries();

    foreach ($entries as $entry) {
      $this->assertContains(
        $entry['severity'],
        ['ok', 'info', 'warning', 'error'],
        sprintf('Entry "%s" has invalid severity: %s', $entry['machine_name'], $entry['severity'])
      );
    }
  }

  /**
   * Tests that HTML is stripped from value and description.
   */
  public function testHtmlStrippingInValues(): void {
    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);
    $entries = $collector->buildEntries();

    foreach ($entries as $entry) {
      if (!empty($entry['value'])) {
        $this->assertStringNotContainsString('<', $entry['value'],
          sprintf('Entry "%s" value contains HTML.', $entry['machine_name']));
      }
      if (!empty($entry['description'])) {
        $this->assertStringNotContainsString('<', $entry['description'],
          sprintf('Entry "%s" description contains HTML.', $entry['machine_name']));
      }
    }
  }

  /**
   * Tests that getStatusEntries returns envelope structure.
   */
  public function testGetStatusEntriesEnvelopeStructure(): void {
    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);
    $result = $collector->getStatusEntries();

    $this->assertArrayHasKey('data', $result);
    $this->assertArrayHasKey('meta', $result);
    $this->assertIsArray($result['data']);
    $this->assertArrayHasKey('count', $result['meta']);
    $this->assertArrayHasKey('generated', $result['meta']);
    $this->assertIsInt($result['meta']['count']);
    $this->assertEquals(count($result['data']), $result['meta']['count']);
  }

  /**
   * Tests that getStatusSummary returns correct structure.
   */
  public function testGetStatusSummaryStructure(): void {
    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);
    $result = $collector->getStatusSummary();

    $this->assertArrayHasKey('data', $result);
    $this->assertArrayHasKey('meta', $result);
    $this->assertArrayHasKey('overall_severity', $result['data']);
    $this->assertArrayHasKey('counts', $result['data']);
    $this->assertArrayHasKey('total', $result['data']);
    $this->assertContains($result['data']['overall_severity'], ['ok', 'info', 'warning', 'error']);
    $this->assertArrayHasKey('ok', $result['data']['counts']);
    $this->assertArrayHasKey('info', $result['data']['counts']);
    $this->assertArrayHasKey('warning', $result['data']['counts']);
    $this->assertArrayHasKey('error', $result['data']['counts']);

    // Total should equal sum of counts.
    $sumCounts = array_sum($result['data']['counts']);
    $this->assertEquals($sumCounts, $result['data']['total']);
  }

  /**
   * Tests caching behavior.
   */
  public function testCacheTtlIsApplied(): void {
    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);

    // First call populates cache.
    $collector->buildEntries();

    // Verify cache exists.
    $cached = $this->container->get('cache.default')->get('sitepulse:status');
    $this->assertNotFalse($cached, 'Status entries should be cached after first call.');
    $this->assertIsArray($cached->data);
  }

  /**
   * Tests that cache TTL of 0 disables caching.
   */
  public function testCacheDisabledWhenTtlZero(): void {
    $config = $this->config('sitepulse.settings');
    $config->set('cache_ttl', 0)->save();

    $collector = $this->container->get('sitepulse.status_collector');
    assert($collector instanceof StatusCollector);
    $collector->buildEntries();

    $cached = $this->container->get('cache.default')->get('sitepulse:status');
    $this->assertFalse($cached, 'Status entries should not be cached when TTL is 0.');
  }

}
