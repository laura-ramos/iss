<?php

namespace Drupal\iss\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
    $config = $this->config('iss.settings');
    $api_key = $config->get('api_key');
    $api_endpoint = $config->get('api_endpoint');
    
    if (!(empty($api_key) || empty($api_endpoint))) {
      //get user data
      $query = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', $this->currentUser()->id())->fields('i');
      $currentUser = $query->execute()->fetchAssoc();
      //get purchase user
      $sale_query = \Drupal::database()->select('ppss_sales', 's')->condition('uid', $this->currentUser()->id())->fields('s', ['id','details'])->orderBy('created', 'DESC');
      $sale_result = $sale_query->execute()->fetchAll();
      $show_block = false;
      $node = \Drupal::routeMatch()->getParameter('node');
      if (!(is_null($node))) {
        $request = \Drupal::request();
        $requestUri = $request->getRequestUri();
        if (strchr($requestUri, '/venta/exitosa')) {
          $show_block = true;
        }
      }
      //mostrar solo el boton de generar factura
      if ($currentUser > 0 && $show_block) {
        $form['description_invoice'] = [
          '#type' => 'markup',
          '#markup' => "Da clic en el link para generar tu factura. "
        ];
        $form['invoice'] = [
          '#type' => 'link',
          '#title' => 'Invoice',
          '#url' => Url::fromRoute('iss.invoice', ['id' => $sale_result[0]->id ?? 0])
        ];
      } else if(($show_block && !$currentUser > 0) or (!$show_block && $currentUser > 0) or (!$show_block && !$currentUser > 0)) {
        if(!$currentUser > 0){
          //si no existe datos del usuario prellenar con los datos de la venta
          if($sale_result[0]->details ?? null) {
            $sale = json_decode($sale_result[0]->details);
            $currentUser = [
              'name' => $sale->payer->payer_info->first_name.' '.$sale->payer->payer_info->last_name,
              'postal_code' => $sale->payer->payer_info->shipping_address->postal_code,
              'address' => $sale->payer->payer_info->shipping_address->line1,
              'suburb' => $sale->payer->payer_info->shipping_address->line2,
              'city' => $sale->payer->payer_info->shipping_address->city,
              'state' => $sale->payer->payer_info->shipping_address->state
            ];
            $form['sale_id'] = [
              '#type' => 'hidden',
              '#required' => FALSE,
              '#default_value' => $show_block ? $sale_result[0]->id ?? 0 : 0,
            ];
          }
        }
        $form['description'] = [
          '#markup' => "<b>Nota:</b> Asegúrate de contar con datos fiscales válidos, los cuales puedes obtener de la constancia de situación fiscal o Cédula de Identificación Fiscal CIF."
        ];
        $form['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Name'),
          '#required' => TRUE,
          '#default_value' => $currentUser['name'] ?? '',
          '#description' => 'Nombre completo de la persona fisica o moral.'
        ];
        $form['rfc'] = [
          '#type' => 'textfield',
          '#title' => $this->t('RFC'),
          '#required' => TRUE,
          '#default_value' => $currentUser['rfc'] ?? '',
        ];
        $form['postal_code'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Postal code'),
          '#required' => true,
          '#default_value' => $currentUser['postal_code'] ?? '',
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
          '#default_value' => $currentUser['regimen_fiscal'] ?? '',
        ];
        $form['cfdi'] = [
          '#type' => 'select',
          '#title' => $this->t('Uso de CFDI'),
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
          '#default_value' => $currentUser['cfdi'] ?? '',
        ];
        $form['email'] = [
          '#type' => 'email',
          '#title' => $this->t('Email'),
          '#required' => TRUE,
          '#default_value' => $currentUser['mail'] ?? $this->currentUser()->getEmail(),
          '#description' => "Email válido para recibir su factura"
        ];
        $form['address'] = [
          '#type' => 'details',
          '#title' => $this->t('Address'),
          '#open' => FALSE,
        ];
        $form['address']['address'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Address'),
          '#required' => FALSE,
          '#default_value' => $currentUser['address'] ?? '',
        ];
        $form['address']['number'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Number'),
          '#required' => FALSE,
          '#default_value' => $currentUser['number'] ?? '',
        ];
        $form['address']['suburb'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Suburb'),
          '#required' => FALSE,
          '#default_value' => $currentUser['suburb'] ?? '',
        ];
        $form['address']['city'] = [
          '#type' => 'textfield',
          '#title' => $this->t('City'),
          '#required' => FALSE,
          '#default_value' => $currentUser['city'] ?? '',
        ];
        $form['address']['state'] = [
          '#type' => 'textfield',
          '#title' => $this->t('State'),
          '#required' => FALSE,
          '#default_value' => $currentUser['state'] ?? '',
        ];
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save'),
        ];
      }
    } else {
      $message = "ISS module don't has configured properly, please review your settings.";
      \Drupal::logger('system')->alert($message);
    }

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
    $id_user = $this->currentUser()->id();
    //address
    $address = $form_state->getValue('address');
    $number = $form_state->getValue('number');
    $suburb = $form_state->getValue('suburb');
    $city = $form_state->getValue('city');
    $state = $form_state->getValue('state');

    $query = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', $this->currentUser()->id())->fields('i');
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
        'number_ext' => $number,
        'suburb' => $suburb,
        'city' => $city,
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
        'number_ext' => $number,
        'suburb' => $suburb,
        'city' => $city,
        'state' => $state,
      ])->execute();

      if($form_state->getValue('sale_id') != 0) {
        //generar factura despues de registrar datos de facturacion
        $url = Url::fromRoute('iss.invoice', ['id' => $form_state->getValue('sale_id') ?? 0]);
        $redirect = new RedirectResponse($url->toString());
        $redirect->send();
      }
      $this->messenger()->addMessage($this->t('Your information have been successfully saved.'));
    }
  }

}