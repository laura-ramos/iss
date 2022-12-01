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
    $form['description'] = [
      '#markup' => $this->t('Hola mundo @link.', [
        '@link' => Link::fromTextAndUrl($this->t('dashboard'), Url::fromUri('https://app.mailgun.com/app/dashboard', [
          'attributes' => [
            'onclick' => "target='_blank'",
          ],
        ]))->toString(),
      ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}