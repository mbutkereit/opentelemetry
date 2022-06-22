<?php

namespace Drupal\opentelemetry\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure opentelemetry settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opentelemetry_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['opentelemetry.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['enabled'] = [
      '#type' => 'select',
      '#options' => ['NO', 'YES'],
      '#required' => TRUE,
      '#title' => $this->t('Enable Tracing:'),
      '#default_value' => $this->config('opentelemetry.settings')
        ->get('enabled') ?? 1,
    ];

    // @todo define all types.
    $types = ['jager_udp' => 'Jaeger UDP', 'jaeger_http' => 'Jaeger HTTP'];

    $form['type'] = [
      '#type' => 'select',
      '#options' => $types,
      '#required' => TRUE,
      '#title' => $this->t('Enable Tracing:'),
      '#default_value' => $this->config('opentelemetry.settings')
        ->get('type') ?? 'jaeger_http',
    ];

    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenTelemetry endpoint'),
      '#description' => $this->t('URL to opentelemetry endpoint.<br/>Example for local Grafana Tempo instance: <code>http://localhost:9411/api/v2/spans</code>'),
      '#default_value' => $this->config('opentelemetry.settings')->get('endpoint'),
    ];

    $form['service_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The service name'),
      '#description' => $this->t('The service name.'),
      '#default_value' => $this->config('opentelemetry.settings')->get('service_name', 'Drupal'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('opentelemetry.settings')
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('service_name', $form_state->getValue('service_name'))
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('type', $form_state->getValue('type'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
