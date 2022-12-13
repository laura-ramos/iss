<?php
namespace Drupal\iss\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Link;
use GuzzleHttp\Exception\RequestException;

/**
 * Class InvoiceController.
 *
 * @package Drupal\iss\Controller
 */
class InvoiceController extends ControllerBase {

  /**
   * purchaseHistory.
   *
   * @return string
   *   Return purchase history by user.
   */
  public function purchaseHistory() {
    //create table header
    $header_table = array(
      'id'=>    $this->t('ID'),
      'name' => $this->t('Name'),
      'total' => $this->t('Total price'),
      'platform' => $this->t('Payment type'),
      'date' => $this->t('Date'),
      'invoice' => $this->t('Invoice')
    );
    //select records from table ppss_sales
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->condition('uid', $this->currentUser()->id());
    $query->fields('s', ['id','uid','mail','platform','details', 'created']);
    $results = $query->execute()->fetchAll();

    $rows = array();
    foreach($results as $data){
      $sale = json_decode($data->details);
      $url_view = Url::fromRoute('iss.invoice', ['id' => $data->id], []);
      $invoice = Link::fromTextAndUrl('Invoice', $url_view);
      //print the data from table
      $rows[] = array(
        'id' =>$data->id,
        'name' => $sale->transactions[0]->item_list->items[0]->name,
        'total' => $sale->transactions[0]->amount->total,
        'platform' => $data->platform,
        'date' => date($data->created),
        'invoice' => $invoice,
      );
    }
    //display data in site
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header_table,
      '#rows' => $rows,
      '#empty' => $this->t('No hay compras'),
    ];
    return $form;
  }

  //create purchase invoice
  public function createInvoice($id){
    //validate user purchase
    $ppss_sales = \Drupal::database()->select('ppss_sales', 's')->condition('id', $id)->condition('uid', $this->currentUser()->id())->fields('s');
    $sales = $ppss_sales->execute()->fetchAssoc();
    $message = '';
    if($sales > 0) {
      $query_invoice = \Drupal::database()->select('iss_invoices', 'i')->condition('sid', $id)->fields('i');
      $invoice = $query_invoice->execute()->fetchAssoc();
      //check if an invoice already exists
      if($invoice > 0) {
        $uuid =  $invoice['uuid'];
        $pdf =  $invoice['pdf'];
        $xml =  $invoice['xml'];
        $created =  $invoice['created'];
        $message = "<h5>Su factura ya fue generada</h5>
          <p>Folio Fiscal UUID: $uuid<br>Fecha: $created <br> <a href='$pdf' target='_blank'>Visualizar PDF</a> <a href='$xml' target='_blank'>Descargar XML</a></p>";
      } else {
        if(date("m", $sales['created']) == date("m")) {
          $config = $this->config('iss.settings');
          $client = \Drupal::httpClient();
          $datosFactura = [];
          try {
            $query_user = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', $this->currentUser()->id())->fields('i');
            $user = $query_user->execute()->fetchAssoc();
            //select max folio
            $query_folio =\Drupal::database()->query("SELECT max(id) as folio from {iss_invoices}");
            $folio = $query_folio->fetchAssoc();

            if($user > 0) {
              $sale = json_decode($sales['details']);
              # datos basicos SAT
              $datosFactura['Version'] = '4.0';
              $datosFactura['Exportacion'] = '01';
              $datosFactura['Serie'] = $config->get('serie');
              $datosFactura['Folio'] = $folio['folio'] ? $folio['folio'] + 1 : $config->get('folio');
              $datosFactura['Fecha'] = 'AUTO';
              $datosFactura['FormaPago'] = "99";
              $datosFactura['CondicionesDePago'] = "";
              $datosFactura['SubTotal'] = $sale->transactions[0]->amount->details->subtotal;
              $datosFactura['Descuento'] = null;
              $datosFactura['Moneda'] = $sale->transactions[0]->amount->currency;
              $datosFactura['TipoCambio'] = 1;
              $datosFactura['Total'] = $sale->transactions[0]->amount->total;
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
              if($user['rfc'] == 'XAXX010101000') {
                //para publico en general
                $datosFactura['Receptor']['UsoCFDI'] = 'S01';//sin efectos fiscales
                $datosFactura["Receptor"]["DomicilioFiscalReceptor"] = $config->get('lugar_expedicion');//debe ser el mismo que LugarExpedición
                $datosFactura["Receptor"]["RegimenFiscalReceptor"] = '616';//sin obligaciones fiscales
              } else {
                $datosFactura['Receptor']['UsoCFDI'] = $user['cfdi'];
                $datosFactura["Receptor"]["DomicilioFiscalReceptor"] = $user['postal_code'];
                $datosFactura["Receptor"]["RegimenFiscalReceptor"] = $user['regimen_fiscal'];
              }
              if($user['rfc'] == 'XAXX010101000'){
                $datosFactura["InformacionGlobal"]["Periodicidad"] = '01';
                $datosFactura["InformacionGlobal"]["Meses"] = '12';
                $datosFactura["InformacionGlobal"]["Año"] = '2022';
              }

              # Datos del receptor opcionales
              $datosFactura["Receptor"]["Calle"] = $user['address'];
              $datosFactura["Receptor"]["NoExt"] = $user['number_ext'];
              //$datosFactura["Receptor"]["NoInt"] = null;
              $datosFactura["Receptor"]["Colonia"] = $user['suburb'];
              //$datosFactura["Receptor"]["Loacalidad"] = null;
              //$datosFactura["Receptor"]["Referencia"] = null;
              $datosFactura["Receptor"]["Municipio"] = $user['city'];
              $datosFactura["Receptor"]["Estado"] = $user['state'];
              //$datosFactura["Receptor"]["Pais"] = null;
              $datosFactura["Receptor"]["CodigoPostal"] = $user['postal_code'];
        
              $conceptos = array();
              $unconcepto = array();
              foreach($sale->transactions[0]->item_list->items as $item) {
                $unconcepto = [
                  'ObjetoImp' => '02',
                  'ClaveProdServ' => '82101600',
                  'NoIdentificacion' => '01',
                  'Cantidad' => 1,
                  'ClaveUnidad' => 'E48',
                  'Descripcion' => $item->name,
                  'ValorUnitario' => $item->price,
                  'Importe' => $item->price,
                  'Descuento' => 0
                ];
                $impuestosTraslados = array(
                  'Base' => $item->price,
                  'Impuesto' => '002',
                  'TipoFactor' => 'Tasa',
                  'TasaOCuota' => '0.160000',
                  'Importe' => $sale->transactions[0]->amount->details->tax
                );
                $unconcepto['Impuestos']['Traslados'][0] = $impuestosTraslados;
                $conceptos[] = $unconcepto;
              }
              $datosFactura['Conceptos'] = $conceptos;
              $datosFactura['Impuestos']['TotalImpuestosTrasladados'] = $sale->transactions[0]->amount->details->tax;
              $datosFactura['Impuestos']['Traslados'][0]['Base'] = $sale->transactions[0]->amount->details->subtotal;
              $datosFactura['Impuestos']['Traslados'][0]['Impuesto'] = '002'; //002 = IVA, 003 = IEPS
              $datosFactura['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa'; //Tasa, Cuota, Exento
              $datosFactura['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
              $datosFactura['Impuestos']['Traslados'][0]['Importe'] = $sale->transactions[0]->amount->details->tax;

              //conectar con el servicio
              $request = $client->post($config->get('api_endpoint').'/api/v5/invoice/create', [
                'headers' => ['X-Api-Key' => $config->get('api_key')],
                'form_params' => [ 'json' => json_encode($datosFactura)]
              ]);

              $response_body = $request->getBody();
              $data  = json_decode($response_body->getContents());
              if($data->code == '200') {
                // Save all transaction data in DB for future reference.
                $connection = \Drupal::Database();
                $pdf =  $data->cfdi->PDF;
                $xml =  $data->cfdi->XML;
                $UUID =  $data->cfdi->UUID;
                $connection->insert('iss_invoices')->fields([
                  'sid' => $sales['id'],
                  'folio' => $folio['folio'] ? $folio['folio'] + 1 : $config->get('folio'),
                  'uuid' => $data->cfdi->UUID,
                  'created' => $data->cfdi->FechaTimbrado,
                  'pdf' => $data->cfdi->PDF,
                  'xml' => $data->cfdi->XML,
                  ])->execute();

                //enviar la factura por email
                $this->messenger()->addMessage($this->sendInvoice($data->cfdi->UUID, $user['mail']));
                $message = "<h5>Su factura ya fue generada</h5>
                  <p>Folio Fiscal UUID: $UUID<br>Fecha:  <br> <a href='$pdf' target='_blank'>Visualizar PDF</a> <a href='$xml' target='_blank'>Descargar XML</a></p>";
              
              } else {
                $this->messenger()->addMessage($data->message ?? 'Ha ocurrido un error');
              }
            } else {
              $this->messenger()->addMessage($this->t('Your billing information is missing'));
              $message = "Favor de registrar tus datos fiscales";
            }
          } catch (RequestException $e) {
            $response = json_decode($e->getResponse()->getBody()->getContents());
            $this->messenger()->addMessage($response->message ?? 'Error al generar factura');
            $message = "<b>Error:</b> Asegúrate de contar con datos fiscales válidos, los cuales puedes obtener de la constancia de situación fiscal o Cédula de Identificación Fiscal CIF";
          }
        } else {
          $this->messenger()->addMessage('No puedes generar facturas de fechas anteriones, favor de comunicarte con el administraador');
        }
      }
    } else  {
      $this->messenger()->addMessage($this->t('Access denied'));
      $message = "<p>You are not authorized to access this page.</p>";
    }
    return [
      '#type' => 'markup',
      '#markup' => "$message"
    ];
  }

  //send invoice
  function sendInvoice($uuid, $email){
    $client = \Drupal::httpClient();
    $config = $this->config('iss.settings');
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
}