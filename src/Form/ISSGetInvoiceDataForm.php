<?php

namespace Drupal\iss\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Database\Database;

/**
 * Provides ISS get invoice data form.
 */
class ISSGetInvoiceDataForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iss_invoice_data_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $conn = Database::getConnection();
    $record = array();
    $query = $conn->select('iss_user_invoice', 'i')->condition('uid', $this->currentUser()->id())->fields('i');
    $record = $query->execute()->fetchAssoc();

    $form['nombre'] = [
      '#type' => 'textfield',
      '#title' => t('Nombre o Razòn social'),
      '#required' => TRUE,
      '#default_value' => $record['name'] ?? ''
    ];

    $form['rfc'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RFC'),
      '#required' => TRUE,
      '#default_value' => isset($record['rfc'])  ? $record['rfc'] : '',
    ];
    
    $form['codigo_postal'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Código postal'),
      '#required' => true,
      '#default_value' => $record['cp'] ?? '',
    ];
    $form['regimen_fiscal'] = [
      '#type' => 'select',
      '#title' => $this->t('Régimen Fiscal'),
      '#options' => [
        '601' => $this->t('General de Ley Personas Morales'),
        '603' => $this->t('Personas Morales con Fines no Lucrativos'),
        '605' => $this->t('Sueldos y Salarios e Ingresos Asimilados a Salarios'),
        '606' => $this->t('Arrendamiento'),
        '607' => $this->t('Régimen de Enajenación o Adquisición de Bienes'),
        '608' => $this->t('Demás ingresos'),
        '610' => $this->t('Residentes en el Extranjero sin Establecimiento Permanente en México'),
        '611' => $this->t('Ingresos por Dividendos (socios y accionistas)'),
        '612' => $this->t('Personas Físicas con Actividades Empresariales y Profesionales'),
        '614' => $this->t('Ingresos por intereses'),
        '615' => $this->t('Régimen de los ingresos por obtención de premios'),
        '616' => $this->t('Sin obligaciones fiscales'),
        '620' => $this->t('Sociedades Cooperativas de Producción que optan por diferir sus ingresos'),
        '621' => $this->t('Incorporación Fiscal'),
        '622' => $this->t('Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras'),
        '623' => $this->t('Opcional para Grupos de Sociedades'),
        '624' => $this->t('Coordinados'),
        '625' => $this->t('Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas'),
        '626' => $this->t('Régimen Simplificado de Confianza'),
      ],
      '#required' => true,
      '#default_value' => $record['regimen_fiscal'] ?? '',
    ];

    $form['cfdi'] = [
      '#type' => 'select',
      '#title' => $this->t('CFDI'),
      '#options' => [
        'G01' => $this->t('G01-Adquisición de mercancías'),
        'G02' => $this->t('G02-Devoluciones, descuentos o bonificaciones'),
        'G03' => $this->t('G03-Gastos en general'),
        'I01' => $this->t('I01-Construcciones'),
        'I02' => $this->t('I02-Mobiliario y equipo de oficina por inversiones'),
        'I03' => $this->t('I03-Equipo de transporte'),
        'I04' => $this->t('I04-Equipo de cómputo y accesorios'),
        'I05' => $this->t('I05-Dados, troqueles, moldes, matrices y herramental'),
        'I06' => $this->t('I06-Comunicaciones telefónicas'),
        'I07' => $this->t('I07-Comunicaciones satelitales'),
        'I08' => $this->t('I08-Otra maquinaria y equipo'),
        'D01' => $this->t('D01-Honorarios médicos, dentales y gastos hospitalarios'),
        'D02' => $this->t('D02-Gastos médicos por incapacidad o discapacidad'),
        'D03' => $this->t('D03-Gastos funerales'),
        'D04' => $this->t('D04-Donativos'),
        'D05' => $this->t('D05-Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación)'),
        'D06' => $this->t('D06-Aportaciones voluntarias al SAR'),
        'D07' => $this->t('D07-Primas por seguros de gastos médicos'),
        'D08' => $this->t('D08-Gastos de transportación escolar obligatoria'),
        'D09' => $this->t('D09-Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones'),
        'D10' => $this->t('D10-Pagos por servicios educativos (colegiaturas)'),
        'CP01' => $this->t('CP01-Pagos'),
        'CN01' => $this->t('CN01-Nómina'),
        'S01' => $this->t('S01-Sin Efectos Fiscales'),
      ],
      '#required' => true,
      '#default_value' => $record['cfdi'] ?? '',
    ];


    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Facturar'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //guardar los datos de facturacion del usuario
    $connection = \Drupal::Database();
    $result = $connection->insert('iss_user_invoice')->fields([
      'uid' => $this->currentUser()->id(),
      'name' => $form_state->getValue('nombre'),
      'rfc' => $form_state->getValue('rfc'),
      'cp' => $form_state->getValue('codigo_postal'),
      'regimen_fiscal' => $form_state->getValue('regimen_fiscal'),
      'cfdi' => $form_state->getValue('cfdi'),
    ])->execute();
    $this->messenger()->addMessage('Datos guardados correctamente');
  } 
}