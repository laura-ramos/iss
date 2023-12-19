<?php

namespace Drupal\iss\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL) {
    $user_id = \Drupal::currentUser()->hasPermission('access user profiles') ? $user : $this->currentUser()->id();
    //get user data
    $query = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', $user_id)->fields('i');
    $currentUser = $query->execute()->fetchAssoc();
    //get purchase user
    $sale_query = \Drupal::database()->select('ppss_sales', 's')->condition('uid', $user_id)->fields('s', ['id','details'])->orderBy('created', 'DESC');
    $sale_result = $sale_query->execute()->fetchAll();
    if(!$currentUser > 0){
      //si no existe datos del usuario llenar los campos con los datos de la venta
      if($sale_result[0]->details ?? null) {
        $sale = json_decode($sale_result[0]->details);
        $currentUser = [
          'name' => strtoupper($sale->payer->payer_info->first_name.' '.$sale->payer->payer_info->last_name),
          'postal_code' => $sale->payer->payer_info->shipping_address->postal_code,
          'address' => $sale->payer->payer_info->shipping_address->line1,
          'suburb' => $sale->payer->payer_info->shipping_address->line2,
          'city' => $sale->payer->payer_info->shipping_address->city,
          'state' => $sale->payer->payer_info->shipping_address->state,
          'mail' => $sale->payer->payer_info->email
        ];
      }
    }
    $form['description'] = [
      '#markup' => "<b>Nota:</b> Asegúrate de contar con datos fiscales válidos, los cuales puedes obtener de la constancia de situación fiscal o Cédula de Identificación Fiscal CIF."
    ];
    $form['id'] = [
      '#type' => 'hidden',
      '#default_value' => $user_id,
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => 'Nombre o Razón Social',
      '#required' => TRUE,
      '#default_value' => $currentUser['name'] ?? '',
      '#description' => 'Tal cual aparece en su constancia de situación fiscal'
    ];
    $form['rfc'] = [
      '#type' => 'textfield',
      '#title' => 'RFC',
      '#required' => TRUE,
      '#default_value' => $currentUser['rfc'] ?? '',
      '#description' => 'Deberá señalar correctamente cada letra o número que conforma su RFC tal cual aparece en su constancia de situación fiscal'
    ];
    $form['regimen_fiscal'] = [
      '#type' => 'select',
      '#title' => 'Régimen Fiscal',
      '#options' => [
        '601' => '601-General de Ley Personas Morales',
        '603' => '603-Personas Morales con Fines no Lucrativos',
        '605' => '605-Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => '606-Arrendamiento',
        '607' => '607-Régimen de Enajenación o Adquisición de Bienes',
        '608' => '608-Demás ingresos',
        '610' => '610-Residentes en el Extranjero sin Establecimiento Permanente en México',
        '611' => '611-Ingresos por Dividendos (socios y accionistas)',
        '612' => '612-Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => '614-Ingresos por intereses',
        '615' => '615-Régimen de los ingresos por obtención de premios',
        '616' => '616-Sin obligaciones fiscales',
        '620' => '620-Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
        '621' => '621-Incorporación Fiscal',
        '622' => '622-Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => '623-Opcional para Grupos de Sociedades',
        '624' => '624-Coordinados',
        '625' => '625-Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        '626' => '626-Régimen Simplificado de Confianza',
      ],
      '#required' => true,
      '#default_value' => $currentUser['regimen_fiscal'] ?? '',
      '#description' => 'Aparece en la constancia de situación fiscal'
    ];
    $form['cfdi'] = [
      '#type' => 'select',
      '#title' => 'Uso de CFDI',
      '#options' => [
        'G01' => 'G01-Adquisición de mercancías',
        'G02' => 'G02-Devoluciones, descuentos o bonificaciones',
        'G03' => 'G03-Gastos en general',
        'I01' => 'I01-Construcciones',
        'I02' => 'I02-Mobiliario y equipo de oficina por inversiones',
        'I03' => 'I03-Equipo de transporte',
        'I04' => 'I04-Equipo de cómputo y accesorios',
        'I05' => 'I05-Dados, troqueles, moldes, matrices y herramental',
        'I06' => 'I06-Comunicaciones telefónicas',
        'I07' => 'I07-Comunicaciones satelitales',
        'I08' => 'I08-Otra maquinaria y equipo',
        'D01' => 'D01-Honorarios médicos, dentales y gastos hospitalarios',
        'D02' => 'D02-Gastos médicos por incapacidad o discapacidad',
        'D03' => 'D03-Gastos funerales',
        'D04' => 'D04-Donativos',
        'D05' => 'D05-Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación)',
        'D06' => 'D06-Aportaciones voluntarias al SAR',
        'D07' => 'D07-Primas por seguros de gastos médicos',
        'D08' => 'D08-Gastos de transportación escolar obligatoria',
        'D09' => 'D09-Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones',
        'D10' => 'D10-Pagos por servicios educativos (colegiaturas)',
        'CP01' => 'CP01-Pagos',
        'CN01' => 'CN01-Nómina',
        'S01' => 'S01-Sin Efectos Fiscales',
      ],
      '#required' => true,
      '#default_value' => $currentUser['cfdi'] ?? 'G03',
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => 'Email',
      '#required' => TRUE,
      '#default_value' => $currentUser['mail'] ?? \Drupal::currentUser()->getEmail(),
      '#description' => "Email válido para recibir su factura"
    ];
    $form['address'] = [
      '#type' => 'details',
      '#title' => 'Datos del domicilio registrado',
      '#open' => TRUE
    ];
    $form['address']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => 'Código Postal',
      '#required' => true,
      '#default_value' => $currentUser['postal_code'] ?? '',
      '#description' => 'Código postal de su domicilio fiscal aparece en la constancia de situación fiscal'
    ];
    $form['address']['address'] = [
      '#type' => 'textfield',
      '#title' => 'Nombre de la Calle',
      '#required' => TRUE,
      '#default_value' => $currentUser['address'] ?? '',
    ];
    $form['address']['number_ext'] = [
      '#type' => 'textfield',
      '#title' => 'Número exterior',
      '#required' => TRUE,
      '#default_value' => $currentUser['number_ext'] ?? '',
    ];
    $form['address']['number_int'] = [
      '#type' => 'textfield',
      '#title' => 'Número interior',
      '#required' => FALSE,
      '#default_value' => $currentUser['number_int'] ?? '',
    ];
    $form['address']['suburb'] = [
      '#type' => 'textfield',
      '#title' => 'Nombre de la Colonia',
      '#required' => FALSE,
      '#default_value' => $currentUser['suburb'] ?? '',
    ];
    $form['address']['city'] = [
      '#type' => 'textfield',
      '#title' => 'Nombre de la Localidad o ciudad',
      '#required' => TRUE,
      '#default_value' => $currentUser['city'] ?? '',
    ];
    $form['address']['town'] = [
      '#type' => 'textfield',
      '#title' => 'Nombre del Municipio',
      '#required' => TRUE,
      '#default_value' => $currentUser['town'] ?? '',
    ];
    $form['address']['state'] = [
      '#type' => 'select',
      '#title' => 'Nombre de la Entidad Federativa',
      '#required' => TRUE,
      '#options' => [
        'AGUASCALIENTES' => 'AGUASCALIENTES',
        'BAJA CALIFORNIA' => 'BAJA CALIFORNIA',
        'BAJA CALIFORNIA SUR' => 'BAJA CALIFORNIA SUR',
        'CAMPECHE' => 'CAMPECHE',
        'COAHUILA' => 'COAHUILA',
        'COLIMA' => 'COLIMA',
        'CHIAPAS' => 'CHIAPAS',
        'CHIHUAHUA' => 'CHIHUAHUA',
        'CIUDAD DE MEXICO' => 'CIUDAD DE MEXICO',
        'DURANGO' => 'DURANGO',
        'GUANAJUATO' => 'GUANAJUATO',
        'GUERRERO' => 'GUERRERO',
        'HIDALGO' => 'HIDALGO',
        'JALISCO' => 'JALISCO',
        'MEXICO' => 'MEXICO',
        'MICHOACAN' => 'MICHOACAN',
        'MORELOS' => 'MORELOS',
        'NAYARIT' => 'NAYARIT',
        'NUEVO LEON' => 'NUEVO LEON',
        'OAXACA' => 'OAXACA',
        'PUEBLA' => 'PUEBLA',
        'QUERETARO' => 'QUERETARO',
        'QUINTANA ROO' => 'QUINTANA ROO',
        'SAN LUIS POTOSI' => 'SAN LUIS POTOSI',
        'SINALOA' => 'SINALOA',
        'SONORA' => 'SONORA',
        'TABASCO' => 'TABASCO',
        'TAMAULIPAS' => 'TAMAULIPAS',
        'TLAXCALA' => 'TLAXCALA',
        'VERACRUZ' => 'VERACRUZ',
        'YUCATAN' => 'YUCATAN',
        'ZACATECAS' => 'ZACATECAS',
      ],
      '#default_value' => $currentUser['state'] ?? '',
    ];
    $form['nota'] = [
      '#type' => 'markup',
      '#markup' => "<b>NOTA:</b> EN CASO DE NO PROPORCIONAR LA INFORMACIÓN NO SE PODRÁ GENERAR LA FACTURA SOLICITADA<br>"
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $rfc = $form_state->getValue('rfc');
    $cp = $form_state->getValue('postal_code');
    $regimen_fiscal = $form_state->getValue('regimen_fiscal');
    $cfdi = $form_state->getValue('cfdi');
    $email = $form_state->getValue('email');
    $id_user = \Drupal::currentUser()->hasPermission('access user profiles') ? $form_state->getValue('id') : $this->currentUser()->id();
    //address
    $address = $form_state->getValue('address');
    $number_ext = $form_state->getValue('number_ext');
    $number_int = $form_state->getValue('number_int');
    $suburb = $form_state->getValue('suburb');
    $city = $form_state->getValue('city');
    $town = $form_state->getValue('town');
    $state = $form_state->getValue('state');

    $query = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', $id_user)->fields('i');
    $num_rows = $query->countQuery()->execute()->fetchField();

    if( $num_rows > 0) {
      //update data
      \Drupal::database()->update('iss_user_invoice')->fields([
        'name' => $name,
        'rfc' => $rfc,
        'postal_code' => $cp,
        'regimen_fiscal' => $regimen_fiscal,
        'cfdi' => $cfdi,
        'mail' => $email,
        'address' => $address,
        'number_ext' => $number_ext,
        'number_int' => $number_int,
        'suburb' => $suburb,
        'city' => $city,
        'town' => $town,
        'state' => $state,
      ])->condition('uid', $id_user, '=')->execute();

      $this->messenger()->addMessage($this->t('Your changes have been successfully saved.'));
    } else {
      //insert data
      \Drupal::database()->insert('iss_user_invoice')->fields([
        'uid' => $id_user,
        'name' => $name,
        'rfc' => $rfc,
        'postal_code' => $cp,
        'regimen_fiscal' => $regimen_fiscal,
        'cfdi' => $cfdi,
        'mail' => $email,
        'address' => $address,
        'number_ext' => $number_ext,
        'number_int' => $number_int,
        'suburb' => $suburb,
        'city' => $city,
        'town' => $town,
        'state' => $state,
      ])->execute();

      $this->messenger()->addMessage($this->t('Your information have been successfully saved.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!preg_match("/^[A-Z0-9ÑÁÉÍÓÚ ]+$/", $form_state->getValue('name'))) {
      $form_state->setErrorByName('name', 'El Nombre o Razón Social debe estar en mayúsculas y no tener caracteres especiales.');
    }
    if (strlen($form_state->getValue('rfc')) < 12 || strlen($form_state->getValue('rfc')) > 13) {
      $form_state->setErrorByName('rfc', 'El RFC debe tener 12 o 13 caracteres.');
    }
    if(strlen($form_state->getValue('postal_code')) != 5) {
      $form_state->setErrorByName('postal_code', 'El código postal debe tener 5 caracteres.');
    }
  }

}