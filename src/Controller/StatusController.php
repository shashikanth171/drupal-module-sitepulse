<?php

declare(strict_types=1);

namespace Drupal\sitepulse\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\sitepulse\Service\StatusCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for SitePulse API endpoints.
 */
class StatusController implements ContainerInjectionInterface {

  /**
   * Constructs a StatusController.
   */
  public function __construct(
    protected readonly StatusCollector $statusCollector,
    protected readonly ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('sitepulse.status_collector'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Valid severity values for filtering.
   */
  protected const VALID_SEVERITIES = ['ok', 'warning', 'error', 'info'];

  /**
   * Returns the full status report as JSON.
   */
  public function status(Request $request): JsonResponse {
    $severityParam = $request->query->get('severity');
    $severityFilter = NULL;

    if ($severityParam !== NULL && $severityParam !== '') {
      $values = array_map('trim', explode(',', (string) $severityParam));
      $invalid = array_diff($values, self::VALID_SEVERITIES);
      if (!empty($invalid)) {
        return new JsonResponse([
          'error' => [
            'message' => sprintf(
              "Invalid severity filter: '%s'. Valid values: %s.",
              implode("', '", $invalid),
              implode(', ', self::VALID_SEVERITIES)
            ),
            'code' => 400,
          ],
        ], 400);
      }
      $severityFilter = $values;
    }

    $result = $this->statusCollector->getStatusEntries($severityFilter);
    return new JsonResponse($result);
  }

  /**
   * Returns the summary health check as JSON.
   */
  public function summary(): JsonResponse {
    $result = $this->statusCollector->getStatusSummary();
    return new JsonResponse($result);
  }

  /**
   * Returns the OpenAPI specification document.
   */
  public function openapi(): JsonResponse {
    $modulePath = $this->moduleExtensionList->getPath('sitepulse');
    $filePath = DRUPAL_ROOT . '/' . $modulePath . '/openapi.json';
    $content = file_get_contents($filePath);
    return new JsonResponse($content, 200, ['Content-Type' => 'application/json'], TRUE);
  }

}
