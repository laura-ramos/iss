<?php

namespace Drupal\iss\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides ISS configuration form.
 */
class ISSAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['iss.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iss_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('iss.settings');

    $form['iss_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Iss settings'),
      '#open' => TRUE,
      '#description' => $this->t('You need to first create a Account at @factura_digital',[
        '@factura_digital' => Link::fromTextAndUrl('Factura Digital', Url::fromUri('https://app.facturadigital.com.mx/registro', [
          'attributes' => [
            'onclick' => "target='_blank'",
          ],
        ]))->toString(),
      ]),
    ];
    $form['iss_settings']['api_endpoint'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('api_endpoint'),
      '#title' => 'Endpoint'
    ];
    $form['iss_settings']['api_key'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Api Key'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['iss_settings_invoice'] = [
      '#type' => 'details',
      '#title' => $this->t('Invoicing service data'),
      '#open' => TRUE
    ];
    $form['iss_settings_invoice']['regimen_fiscal'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('regimen_fiscal'),
      '#title' => 'Régimen Fiscal'
    ];
    $form['iss_settings_invoice']['lugar_expedicion'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('lugar_expedicion'),
      '#title' => 'Lugar de expedición'
    ];
    $form['iss_settings_invoice']['serie'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('serie'),
      '#title' => 'Serie'
    ];
    $form['iss_settings_invoice']['folio'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('folio'),
      '#title' => 'Folio'
    ];

    $form['iss_settings_cron'] = [
      '#type' => 'details',
      '#title' => $this->t('Cron settings'),
      '#open' => TRUE
    ];
    $form['iss_settings_cron']['num_rows'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('num_rows'),
      '#title' => 'Número de registros',
      "#description" => 'Número de registros a facturar cada que se ejecuta el cron.'
    ];
    $form['iss_settings_cron']['stamp_date'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('stamp_date'),
      '#title' => 'Fecha para generar facturas',
      "#description" => 'Número de días antes del final del mes para generar las facturas. Ejemplo: - 1 days, - 2 days, - 1 week',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('iss.settings');
    $config
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_endpoint', $form_state->getValue('api_endpoint'))
      ->set('regimen_fiscal', $form_state->getValue('regimen_fiscal'))
      ->set('lugar_expedicion', $form_state->getValue('lugar_expedicion'))
      ->set('serie', $form_state->getValue('serie'))
      ->set('folio', $form_state->getValue('folio'))
      ->set('num_rows', $form_state->getValue('num_rows'))
      ->set('stamp_date', $form_state->getValue('stamp_date'))
      ->save();
    
    $this->messenger()->addMessage($this->t('The configuration options have been saved.'));
    parent::submitForm($form, $form_state);
  }

}