<?php

namespace Drupal\iss\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides ISS filter form.
 */
class FilterTableform extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iss_filter_form';
  }

  /**
   * {@inheritdoc}
   */
   public function buildForm(array $form, FormStateInterface $form_state) {
    $form['filters'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Filter'),
      '#open'  => true,
    ];
    $form['filters']['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start date'),
      '#default_value' => date('Y-m-01'),
    ];
    $form['filters']['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End date'),
      '#default_value' => date('Y-m-t'),
    ];
    $form['filters']['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Filter')
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field = $form_state->getValues();
	  $start_date = $field["start_date"];
	  $end_date = $field["end_date"];
    $url = Url::fromRoute('iss.list')->setRouteParameters(array('start_date' => $start_date, 'end_date' => $end_date));
    $form_state->setRedirectUrl($url); 
  }

}