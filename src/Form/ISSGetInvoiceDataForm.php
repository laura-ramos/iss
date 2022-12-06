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

  //funcion para generar factura
  function generarFactura(){
    $config = $this->config('iss.settings');
    $client = \Drupal::httpClient();
    try {
      //obtener los datos del usuario
      $conn = Database::getConnection();
      $user = array();
      $query = $conn->select('iss_user_invoice', 'i')->condition('uid', $this->currentUser()->id())->fields('i');
      $user = $query->execute()->fetchAssoc();

      //obtener los datos de la venta

      # datos basicos SAT
      $datosFactura = [];
      $datosFactura['Version'] = '4.0';
      $datosFactura['Exportacion'] = '01';
      //$datosFactura['Serie'] = 'A';
      //$datosFactura['Folio'] = '50';
      $datosFactura['Fecha'] = 'AUTO';
      $datosFactura['FormaPago'] = "99";
      $datosFactura['CondicionesDePago'] = "";
      $datosFactura['SubTotal'] = "200.00";
      $datosFactura['Descuento'] = null;
      $datosFactura['Moneda'] = 'MXN';
      $datosFactura['TipoCambio'] = 1;
      $datosFactura['Total'] = "232.00";
      $datosFactura['TipoDeComprobante'] = 'I';
      $datosFactura['MetodoPago'] = "PUE";
      $datosFactura['LugarExpedicion'] = $config->get('lugar_expedicion');
      # opciones de personalización (opcionales)
      $datosFactura['LeyendaFolio'] = "FACTURA"; # leyenda opcional para poner a lado del folio: FACTURA, RECIBO, NOTA DE CREDITO, ETC.
      # Regimen fiscal del emisor ligado al tipo de operaciones que representa este CFDI
      $datosFactura['Emisor']['RegimenFiscal'] = $config->get('regimen_fiscal');
      //$datosFactura['Emisor']['Rfc'] = 'EGP050812MV4';
      //$datosFactura['Emisor']['Nombre'] = 'EDITORIAL GOLFO PACIFICO';

      # Datos del receptor obligatorios
      $datosFactura['Receptor']['Rfc'] = $user['rfc'];
      $datosFactura['Receptor']['Nombre'] = $user['name'];
      if($rfc == 'XAXX010101000') {
        //para publico en general
        $datosFactura['Receptor']['UsoCFDI'] = 'S01';//sin efectos fiscales
        $datosFactura["Receptor"]["DomicilioFiscalReceptor"] = $config->get('lugar_expedicion');//debe ser el mismo que LugarExpedición
        $datosFactura["Receptor"]["RegimenFiscalReceptor"] = '616';//sin obligaciones fiscales
      } else {
        $datosFactura['Receptor']['UsoCFDI'] = $user['cfdi'];
        $datosFactura["Receptor"]["DomicilioFiscalReceptor"] = $user['cp'];
        $datosFactura["Receptor"]["RegimenFiscalReceptor"] = $user['regimen_fiscal'];
      }
      
      # Datos del receptor opcionales
      $datosFactura['Receptor']['ResidenciaFiscal'] = null;
      $datosFactura['Receptor']['NumRegIdTrib'] = null;
      $datosFactura["Receptor"]["Calle"] = null;
      $datosFactura["Receptor"]["NoExt"] = null;
      $datosFactura["Receptor"]["NoInt"] = null;
      $datosFactura["Receptor"]["Colonia"] = null;
      $datosFactura["Receptor"]["Loacalidad"] = null;
      $datosFactura["Receptor"]["Referencia"] = null;
      $datosFactura["Receptor"]["Municipio"] = null;
      $datosFactura["Receptor"]["Estado"] = null;
      $datosFactura["Receptor"]["Pais"] = null;
      $datosFactura["Receptor"]["CodigoPostal"] = null;

      //si es publico en general incluir el nodo InformacionGlobal
      if($rfc == 'XAXX010101000'){
        $datosFactura["InformacionGlobal"]["Periodicidad"] = '01';
        $datosFactura["InformacionGlobal"]["Meses"] = '12';
        $datosFactura["InformacionGlobal"]["Año"] = '2022';
      }

      //conceptos
      $datosFactura['Conceptos'][0]['ObjetoImp'] = '02';
      $datosFactura['Conceptos'][0]['ClaveProdServ'] = '01010101';
      //$datosFactura['Conceptos'][0]['NoIdentificacion'] = '01';
      $datosFactura['Conceptos'][0]['Cantidad'] = 1;
      $datosFactura['Conceptos'][0]['ClaveUnidad'] = 'ZZ';
      //$datosFactura['Conceptos'][0]['Unidad'] = 'Pieza';
      $datosFactura['Conceptos'][0]['Descripcion'] = 'Producto de prueba';
      $datosFactura['Conceptos'][0]['ValorUnitario'] = '200.00';
      $datosFactura['Conceptos'][0]['Importe'] = '200.00';
      //$datosFactura['Conceptos'][0]['Descuento'] = null;

      //impuestos
      $datosFactura['Conceptos'][0]['Impuestos']['Traslados'][0]['Base'] = '200.00';
      $datosFactura['Conceptos'][0]['Impuestos']['Traslados'][0]['Impuesto'] = '002'; //002 = IVA, 003 = IEPS
      $datosFactura['Conceptos'][0]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa'; //Tasa, Cuota, Exento
      $datosFactura['Conceptos'][0]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
      $datosFactura['Conceptos'][0]['Impuestos']['Traslados'][0]['Importe'] = '32.00';

      $datosFactura['Impuestos']['TotalImpuestosTrasladados'] = '32.00';
      $datosFactura['Impuestos']['Traslados'][0]['Base'] = '200.00';
      $datosFactura['Impuestos']['Traslados'][0]['Impuesto'] = '002'; //002 = IVA, 003 = IEPS
      $datosFactura['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa'; //Tasa, Cuota, Exento
      $datosFactura['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
      $datosFactura['Impuestos']['Traslados'][0]['Importe'] = '32.00';

      //conectar con el servicio
      $request = $client->post($config->get('api_endpoint').'/api/v5/invoice/create', [
        'headers' => ['X-Api-Key' => $config->get('api_key')],
        'form_params' => [ 'json' => json_encode($datosFactura)]
      ]);

      $response_body = $request->getBody();
      $data  = json_decode($response_body->getContents());
      if($data->code == '200') {
        //guardar los datos de la factura
        $connection = \Drupal::Database();
        $result = $connection->insert('iss_invoices')->fields([
          'uid' => $this->currentUser()->id(),
          'no_certificado' => $data->cfdi->NoCertificado,
          'uuid' => $data->cfdi->UUID,
          'rfc_prov_certif' => $data->cfdi->RfcProvCertif,
          'created' => $data->cfdi->FechaTimbrado,
          'pdf' => $data->cfdi->PDF,
          'xml' => $data->cfdi->XML,
        ])->execute();

        //enviar la factura por email
        return $this->enviarCFDI($data->message);
      } else {
        return $data->message ?? 'Ha ocurrido un error';
      }
    } catch (RequestException $e) {
      $response = json_decode($e->getResponse()->getBody()->getContents());
      return $response->message ?? 'Error al generar factura';
    }
  }

  //funcion para generar factura por email
  function enviarCFDI($uuid){
    $client = \Drupal::httpClient();
    $config = $this->config('iss.settings');
    try {
      //enviar la factura por correo
      $request = $client->post($config->get('api_endpoint').'/api/v5/invoice/send', [
        'headers' => [
          'X-Api-Key' => $config->get('api_key'),
          'uuid' => $uuid,
          'recipient' => 'lramos@noticiasnet.mx',
          'bbc' => '',
          'message' => 'Enviar factura por correo',
        ],
      ]);
      $response_body = $request->getBody();
      $data  = json_decode($response_body->getContents());
      return $data->message;
    } catch (RequestException $e) {
      $response = json_decode($e->getResponse()->getBody()->getContents());
      return $response->message ?? 'Error al enviar factura';
    }
  }
}