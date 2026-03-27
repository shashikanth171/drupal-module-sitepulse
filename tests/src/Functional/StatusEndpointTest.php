<?php

declare(strict_types=1);

namespace Drupal\Tests\sitepulse\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SitePulse API endpoints.
 */
#[Group('sitepulse')]
class StatusEndpointTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'basic_auth',
    'sitepulse',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with the API permission.
   */
  protected User $authorizedUser;

  /**
   * A user without the API permission.
   */
  protected User $unauthorizedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->authorizedUser = $this->drupalCreateUser(['access sitepulse api']);
    $this->unauthorizedUser = $this->drupalCreateUser([]);
  }

  /**
   * Tests authenticated user with permission gets 200 + JSON envelope.
   */
  public function testAuthorizedUserGetsStatusData(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status');
    $this->assertSession()->statusCodeEquals(200);

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($response);
    $this->assertArrayHasKey('data', $response);
    $this->assertArrayHasKey('meta', $response);
    $this->assertIsArray($response['data']);
    $this->assertArrayHasKey('count', $response['meta']);
    $this->assertArrayHasKey('generated', $response['meta']);
    $this->assertIsInt($response['meta']['count']);
    $this->assertEquals(count($response['data']), $response['meta']['count']);
  }

  /**
   * Tests response entries contain expected fields.
   */
  public function testStatusEntriesHaveRequiredFields(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status');
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $this->assertNotEmpty($response['data'], 'Status report should have entries.');
    foreach ($response['data'] as $entry) {
      $this->assertArrayHasKey('machine_name', $entry);
      $this->assertArrayHasKey('title', $entry);
      $this->assertArrayHasKey('value', $entry);
      $this->assertArrayHasKey('description', $entry);
      $this->assertArrayHasKey('severity', $entry);
      $this->assertContains($entry['severity'], ['ok', 'info', 'warning', 'error'],
        sprintf('Entry "%s" has invalid severity: %s', $entry['machine_name'], $entry['severity']));
    }
  }

  /**
   * Tests user without permission gets 403.
   */
  public function testUnauthorizedUserGets403(): void {
    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests basic_auth authentication works (FR-009).
   */
  public function testBasicAuthWorks(): void {
    $password = 'test_password_123';
    $user = $this->drupalCreateUser(['access sitepulse api'], 'basicauthuser', FALSE, ['pass' => $password]);

    $url = $this->getAbsoluteUrl('/api/sitepulse/v1/status');
    $credentials = base64_encode($user->getAccountName() . ':' . $password);

    // Use Guzzle client for basic auth test.
    $client = \Drupal::httpClient();
    $response = $client->get($url, [
      'headers' => [
        'Authorization' => 'Basic ' . $credentials,
      ],
      'http_errors' => FALSE,
    ]);

    $this->assertEquals(200, $response->getStatusCode(), 'Basic auth should return 200 for authorized user.');
    $body = json_decode((string) $response->getBody(), TRUE);
    $this->assertArrayHasKey('data', $body);
  }

  /**
   * Tests meta.generated is a valid ISO 8601 string.
   */
  public function testMetaGeneratedIsIso8601(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status');
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $generated = $response['meta']['generated'];
    $this->assertIsString($generated);
    $parsed = \DateTime::createFromFormat('Y-m-d\TH:i:s+00:00', $generated);
    $this->assertNotFalse($parsed, 'meta.generated should be valid ISO 8601.');
  }

  /**
   * Tests severity filter returns only matching entries.
   */
  public function testSeverityFilterReturnsMatchingEntries(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status', ['query' => ['severity' => 'error']]);
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    foreach ($response['data'] as $entry) {
      $this->assertEquals('error', $entry['severity'],
        sprintf('Entry "%s" should have error severity when filtering by error.', $entry['machine_name']));
    }
  }

  /**
   * Tests multiple severity values filter correctly.
   */
  public function testMultipleSeverityFilterWorks(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status', ['query' => ['severity' => 'error,warning']]);
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    foreach ($response['data'] as $entry) {
      $this->assertContains($entry['severity'], ['error', 'warning'],
        sprintf('Entry "%s" should have error or warning severity.', $entry['machine_name']));
    }
  }

  /**
   * Tests invalid severity value returns 400.
   */
  public function testInvalidSeverityReturns400(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status', ['query' => ['severity' => 'critical']]);
    $this->assertSession()->statusCodeEquals(400);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertArrayHasKey('error', $response);
    $this->assertArrayHasKey('message', $response['error']);
    $this->assertStringContainsString('critical', $response['error']['message']);
  }

  /**
   * Tests empty severity parameter returns all entries.
   */
  public function testEmptySeverityReturnsAll(): void {
    $this->drupalLogin($this->authorizedUser);

    // Get unfiltered count.
    $this->drupalGet('/api/sitepulse/v1/status');
    $allResponse = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $allCount = $allResponse['meta']['count'];

    // Get with empty severity param.
    $this->drupalGet('/api/sitepulse/v1/status', ['query' => ['severity' => '']]);
    $this->assertSession()->statusCodeEquals(200);
    $filteredResponse = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals($allCount, $filteredResponse['meta']['count']);
  }

  /**
   * Tests filtered response meta.count matches data array length.
   */
  public function testFilteredCountMatchesDataLength(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status', ['query' => ['severity' => 'ok']]);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals(count($response['data']), $response['meta']['count']);
  }

  /**
   * Tests summary endpoint returns 200 with correct structure.
   */
  public function testSummaryEndpointReturnsCorrectStructure(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status/summary');
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $this->assertArrayHasKey('data', $response);
    $this->assertArrayHasKey('meta', $response);
    $this->assertArrayHasKey('overall_severity', $response['data']);
    $this->assertArrayHasKey('counts', $response['data']);
    $this->assertArrayHasKey('total', $response['data']);
    $this->assertContains($response['data']['overall_severity'], ['ok', 'info', 'warning', 'error']);
  }

  /**
   * Tests summary counts have all severity keys.
   */
  public function testSummaryCountsHaveAllSeverityKeys(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status/summary');
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $counts = $response['data']['counts'];
    $this->assertArrayHasKey('ok', $counts);
    $this->assertArrayHasKey('info', $counts);
    $this->assertArrayHasKey('warning', $counts);
    $this->assertArrayHasKey('error', $counts);
    foreach ($counts as $key => $value) {
      $this->assertIsInt($value, sprintf('Count for "%s" should be an integer.', $key));
    }
  }

  /**
   * Tests summary total equals sum of counts.
   */
  public function testSummaryTotalEqualsSumOfCounts(): void {
    $this->drupalLogin($this->authorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status/summary');
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);

    $total = $response['data']['total'];
    $sumCounts = array_sum($response['data']['counts']);
    $this->assertEquals($sumCounts, $total, 'Total should equal sum of all counts.');
  }

  /**
   * Tests summary endpoint requires permission (403).
   */
  public function testSummaryUnauthorizedGets403(): void {
    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('/api/sitepulse/v1/status/summary');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests OpenAPI endpoint returns valid JSON with 200.
   */
  public function testOpenApiEndpointReturnsJson(): void {
    // OpenAPI endpoint is public — no auth required.
    $this->drupalGet('/api/sitepulse/v1/openapi.json');
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($response);
    $this->assertArrayHasKey('openapi', $response);
    $this->assertArrayHasKey('info', $response);
    $this->assertArrayHasKey('paths', $response);
  }

  /**
   * Tests settings form is accessible and saves config.
   */
  public function testSettingsFormSavesConfig(): void {
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/config/system/sitepulse');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('cache_ttl');
    $this->assertSession()->fieldExists('denylist');

    // Submit with new values.
    $this->submitForm([
      'cache_ttl' => 120,
      'denylist' => "php\ncron",
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $config = $this->config('sitepulse.settings');
    $this->assertEquals(120, $config->get('cache_ttl'));
    $this->assertEquals(['php', 'cron'], $config->get('denylist'));
  }

}
