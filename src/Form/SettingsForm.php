<?php

declare(strict_types=1);

namespace Drupal\sitepulse\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for SitePulse settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sitepulse_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['sitepulse.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('sitepulse.settings');

    $form['cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache TTL (seconds)'),
      '#description' => $this->t('How long to cache status data. Set to 0 to disable caching.'),
      '#default_value' => $config->get('cache_ttl') ?? 60,
      '#min' => 0,
      '#required' => TRUE,
    ];

    $denylist = $config->get('denylist') ?? [];
    $form['denylist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Denylist'),
      '#description' => $this->t('Machine names of status entries to exclude from API responses. One per line.'),
      '#default_value' => implode("\n", $denylist),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $denylistRaw = $form_state->getValue('denylist');
    if (!empty($denylistRaw)) {
      $lines = array_filter(array_map('trim', explode("\n", $denylistRaw)));
      foreach ($lines as $line) {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $line)) {
          $form_state->setErrorByName('denylist', $this->t('Invalid machine name: @name. Use lowercase letters, numbers, and underscores.', ['@name' => $line]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $denylistRaw = $form_state->getValue('denylist');
    $denylist = array_values(array_filter(array_map('trim', explode("\n", $denylistRaw))));

    $this->config('sitepulse.settings')
      ->set('cache_ttl', (int) $form_state->getValue('cache_ttl'))
      ->set('denylist', $denylist)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
