<?php

namespace Drupal\iss;

use Drupal\Core\Database\Connection;
use GuzzleHttp\Exception\RequestException;

/**
 * Class ApiService.
 */
class IssApiService {

  /**
   * Database connection.
   *
   * Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new IssApiService object.
   */
  public function __construct(Connection $connection) {
    $this->database = $connection;
  }

  public function createInvoice($p_general, $id_sale) {
    $config =  \Drupal::config('iss.settings');
    $client = \Drupal::httpClient();
    $datosFactura = [];

    $ppss_sales = $this->database->select('ppss_sales', 's')->condition('id', $id_sale)->fields('s');
    $sales = $ppss_sales->execute()->fetchAssoc();
    $sale = json_decode($sales['details']);

    $query_user = $this->database->select('iss_user_invoice', 'i')->condition('uid', $sales['uid'])->fields('i');
    $user = $query_user->execute()->fetchAssoc();

    $query_folio = $this->database->query("SELECT max(folio) as folio from {iss_invoices}");
    $folio = $query_folio->fetchAssoc();
    try {
      $conceptos = array();
      $unconcepto = array();
      $base = 0;
      $importe = 0;
      $impuesto = 0;
      foreach($sale->plan->payment_definitions as $item) {
        $base += $item->amount->value;
        $importe += $item->amount->value + $item->charge_models[0]->amount->value;
        $impuesto += $item->charge_models[0]->amount->value;
        $unconcepto = [
          'ObjetoImp' => '02',
          'ClaveProdServ' => '82101600',
          'NoIdentificacion' => '01',
          'Cantidad' => 1,
          'ClaveUnidad' => 'E48',
          'Descripcion' => $sale->description,
          'ValorUnitario' => $item->amount->value,
          'Importe' => $item->amount->value,
          'Descuento' => 0
        ];
        $impuestosTraslados = array(
          'Base' => $item->amount->value,
          'Impuesto' => '002',
          'TipoFactor' => 'Tasa',
          'TasaOCuota' => '0.160000',
          'Importe' => $item->charge_models[0]->amount->value
        );
        $unconcepto['Impuestos']['Traslados'][0] = $impuestosTraslados;
        $conceptos[] = $unconcepto;
      }

      $datosFactura['Version'] = '4.0';
      $datosFactura['Exportacion'] = '01';
      $datosFactura['Serie'] = $config->get('serie');
      $datosFactura['Folio'] = $folio['folio'] ? $folio['folio'] + 1 : $config->get('folio');
      $datosFactura['Fecha'] = 'AUTO';
      $datosFactura['FormaPago'] = "06";//definir bien
      $datosFactura['CondicionesDePago'] = "";
      $datosFactura['SubTotal'] = $base;
      $datosFactura['Descuento'] = null;
      $datosFactura['Moneda'] = $sale->plan->payment_definitions[0]->amount->currency;
      $datosFactura['TipoCambio'] = 1;
      $datosFactura['Total'] = $importe;
      $datosFactura['TipoDeComprobante'] = 'I';
      $datosFactura['MetodoPago'] = "PUE";
      $datosFactura['LugarExpedicion'] = $config->get('lugar_expedicion');
      # opciones de personalización (opcionales)
      $datosFactura['LeyendaFolio'] = "FACTURA"; # leyenda opcional para poner a lado del folio: FACTURA, RECIBO, NOTA DE CREDITO, ETC.
      # Regimen fiscal del emisor ligado al tipo de operaciones que representa este CFDI
      $datosFactura['Emisor']['RegimenFiscal'] = $config->get('regimen_fiscal');

      if($p_general) {
        # Datos del receptor obligatorios
        $datosFactura['Receptor']['Rfc'] = 'XAXX010101000';
        $datosFactura['Receptor']['Nombre'] = 'PUBLICO EN GENERAL';
        $datosFactura['Receptor']['UsoCFDI'] = 'S01';//sin efectos fiscales
        $datosFactura["Receptor"]["DomicilioFiscalReceptor"] = $config->get('lugar_expedicion');//debe ser el mismo que LugarExpedición
        $datosFactura["Receptor"]["RegimenFiscalReceptor"] = '616';//sin obligaciones fiscales

        $datosFactura["InformacionGlobal"]["Periodicidad"] = '01';
        $datosFactura["InformacionGlobal"]["Meses"] = date('m');
        $datosFactura["InformacionGlobal"]["Año"] = date('Y');

      } else {
        # Datos del receptor obligatorios
        $datosFactura['Receptor']['Rfc'] = $user['rfc'];
        $datosFactura['Receptor']['Nombre'] = $user['name'];
        $datosFactura['Receptor']['UsoCFDI'] = $user['cfdi'];
        $datosFactura["Receptor"]["DomicilioFiscalReceptor"] = $user['postal_code'];
        $datosFactura["Receptor"]["RegimenFiscalReceptor"] = $user['regimen_fiscal'];

        # Datos del receptor opcionales
        $datosFactura["Receptor"]["Calle"] = $user['address'];
        $datosFactura["Receptor"]["NoExt"] = $user['number_ext'];
        $datosFactura["Receptor"]["NoInt"] = $user['number_int'];
        $datosFactura["Receptor"]["Colonia"] = $user['suburb'];
        //$datosFactura["Receptor"]["Loacalidad"] = null;
        //$datosFactura["Receptor"]["Referencia"] = null;
        $datosFactura["Receptor"]["Municipio"] = $user['city'];
        $datosFactura["Receptor"]["Estado"] = $user['state'];
        $datosFactura["Receptor"]["Pais"] = 'MEXICO';
        $datosFactura["Receptor"]["CodigoPostal"] = $user['postal_code'];
      }

      $datosFactura['Conceptos'] = $conceptos;
      $datosFactura['Impuestos']['TotalImpuestosTrasladados'] = $impuesto;
      $datosFactura['Impuestos']['Traslados'][0]['Base'] = $base;
      $datosFactura['Impuestos']['Traslados'][0]['Impuesto'] = '002'; //002 = IVA, 003 = IEPS
      $datosFactura['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa'; //Tasa, Cuota, Exento
      $datosFactura['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
      $datosFactura['Impuestos']['Traslados'][0]['Importe'] = $impuesto;
//return $datosFactura;
      //conectar con el servicio
      $request = $client->post($config->get('api_endpoint').'/api/v5/invoice/create', [
        'headers' => ['X-Api-Key' => $config->get('api_key')],
        'form_params' => [ 'json' => json_encode($datosFactura)]
      ]);

      $response_body = $request->getBody();
      $data  = json_decode($response_body->getContents());
      if($data->code == '200') {
        // Save all transaction data in DB for future reference.
        $this->database->insert('iss_invoices')->fields([
          'sid' => $sales['id'],
          'folio' => $folio['folio'] ? $folio['folio'] + 1 : $config->get('folio'),
          'uuid' => $data->cfdi->UUID,
          'created' => $data->cfdi->FechaTimbrado,
          'pdf' => $data->cfdi->PDF,
          'xml' => $data->cfdi->XML,
        ])->execute();
        if(!$p_general){
          $this->sendInvoice($data->cfdi->UUID, $user['mail']);
        }
        return $data;
      } else {
        return $data->message ?? 'Ha ocurrido un error';
      }
    } catch (RequestException $e) {
      $response = json_decode($e->getResponse()->getBody()->getContents());
      return $response->message ?? 'Error al generar factura';
    }
  }

  //send invoice
  function sendInvoice($uuid, $email){
    $client = \Drupal::httpClient();
    $config =  \Drupal::config('iss.settings');
    try {
      //enviar la factura por correo
      $request = $client->post($config->get('api_endpoint').'/api/v5/invoice/send', [
        'headers' => [
          'X-Api-Key' => $config->get('api_key'),
          'uuid' => $uuid,
          'recipient' => $email,
          'bbc' => '',
          'message' => 'Comprobante Fiscal Digital',
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

  public function globalInvoice() {
    $first_day = strtotime(date("Y-m-01"));
    $today = date("Y-m-d");
    $last_day = strtotime(date("Y-m-t")."- 1 days");
    //if($today == date('Y-m-d', $last_day)) {
    if($today == '2022-12-18') {
      //obtener ppss_sales que no han sido facturados
      $query = $this->database->select('ppss_sales', 's');
      $query->leftJoin('iss_invoices', 'i', 's.id = i.sid');
      $query->condition('s.created', array($first_day, $last_day), 'BETWEEN');
      //$query->condition('s.id', 5, '=');
      $query->fields('s', ['id','uid','mail']);
      $query->fields('i',['uuid']);
      $query->range(0, 50);
      $index = 0;
      $results = $query->execute()->fetchAll();
      //return $results;
      foreach($results as $result) {
        if(!$result->uuid) {
          $index = $index + 1;
          //generar facturas
          $invoice = $this->createInvoice(true, $result->id);
          if($invoice->code ?? false && $invoice->code == '200') {
            $index = $index + 1;
          } else {
            \Drupal::logger('ISS')->error('Error al generar factura de la venta '.$result->id.'-'.$invoice);
          }
         //\Drupal::logger('ISS')->error('Generar facturas de publico en general'); 
        }
      }
      \Drupal::logger('ISS')->info('Se generaron '.$index.' facturas');
    }
  }
}