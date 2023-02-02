<?php
namespace Drupal\iss\Controller;
use Drupal\Core\Controller\ControllerBase;
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
      'name' => $this->t('Plan'),
      'total' => $this->t('Total price'),
      'platform' => $this->t('Payment type'),
      'date' => $this->t('Date'),
      'status' => $this->t('Status'),
      'receipt' => $this->t('Receipt'),
      'invoice' => $this->t('Invoice')
    );
    //select records from table ppss_sales
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->leftJoin('iss_invoices', 'i', 's.id = i.sid');
    $query->condition('uid', $this->currentUser()->id());
    $query->fields('s', ['id','uid','mail','platform','details', 'created', 'status']);
    $query->fields('i',['uuid','p_general']);
    $results = $query->execute()->fetchAll();

    $rows = array();
    foreach($results as $data){
      $sale = json_decode($data->details);
      $url_invoice = Url::fromRoute('iss.invoice', ['id' => $data->id], []);
      $url_receipt = Url::fromRoute('iss.receipt', ['id' => $data->id], ['attributes' => ['target' => '_blank']]);
      //print the data from table
      $rows[] = array(
        'name' => $sale->description,
        'total' => $sale->plan->payment_definitions[0]->amount->value + $sale->plan->payment_definitions[0]->charge_models[0]->amount->value,
        'platform' => $data->platform,
        'date' => date('d-m-Y', $data->created),
        'status' => $data->status ? 'Activo' : 'Inactivo',
        'receipt' => Link::fromTextAndUrl($this->t('Receipt'), $url_receipt),
        'invoice' => $data->p_general ? '' : Link::fromTextAndUrl($this->t('Invoice'), $url_invoice)
      );
    }
    //display data in site
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header_table,
      '#rows' => $rows,
      '#empty' => 'No hay compras',
      '#attributes' => [
        'class' => [
          'nvi-table-invoice',
        ],
      ]
    ];
    return $form;
  }

  //create purchase invoice
  public function createInvoice($id){
    $config = $this->config('iss.settings');
    $api_key = $config->get('api_key');
    $api_endpoint = $config->get('api_endpoint');
    if (!(empty($api_key) || empty($api_endpoint))) {
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
          //Validar la fecha de la compra
          $first_day = strtotime(date("Y-m-01"));//first day of the current month 
          $last_day = strtotime(date("Y-m-t"));//last day of the current month 
          if($sales['created'] >= $first_day && $sales['created'] < $last_day) {
            $query_user = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', $this->currentUser()->id())->fields('i');
            $user = $query_user->execute()->fetchAssoc();
            $url = Url::fromRoute('iss.user_data_form', ['user' => $this->currentUser()->id()]);
            if($user > 0) {
              $invoice = \Drupal::service('iss.api_service')->createInvoice(false, $sales['id']);
              if($invoice->code ?? false && $invoice->code == '200') {
                $pdf =  $invoice->cfdi->PDF;
                $xml =  $invoice->cfdi->XML;
                $UUID =  $invoice->cfdi->UUID;
                $created =  $invoice->cfdi->FechaTimbrado;
                $message = "<h5>Su factura ya fue generada</h5><p>Folio Fiscal UUID: $UUID<br>Fecha: $created <br> 
                <a href='$pdf' target='_blank'>Visualizar PDF</a> <a href='$xml' target='_blank'>Descargar XML</a></p>";
              } else {
                $this->messenger()->addError($invoice);
                $message = "<b>Error al generar factura:</b> Asegúrate de contar con datos fiscales válidos <a href=".$url->toString().">Datos fiscales</a>";
              }
            } else {
              $this->messenger()->addError($this->t('Your billing information is missing'));
              $message = "Favor de registrar tus datos fiscales en <a href=".$url->toString().">Datos fiscales</a>";
            }
          } else {
            $this->messenger()->addWarning('No puedes generar facturas de fechas anteriones, favor de comunicarte con el administrador');
          }
        }
      } else  {
        $this->messenger()->addWarning($this->t('Access denied'));
        $message = "<p>You are not authorized to access this page.</p>";
      }
    } else {
      $message = "<b>Error: </b>ISS module don't has configured properly, please review your settings.";
      \Drupal::logger('system')->alert($message);
    }
    return [
      '#type' => 'markup',
      '#markup' => "$message"
    ];
  }

  public function receipt($id){
    $ppss_sales = \Drupal::database()->select('ppss_sales', 's')->condition('id', $id)->condition('uid', $this->currentUser()->id())->fields('s');
    $sales = $ppss_sales->execute()->fetchAssoc();
    $details = json_decode($sales['details']);
    $data = [
      "email" => $sales["mail"],
      "platform" => $sales["platform"],
      "created" => date("d/m/Y",$sales["created"]),
      "product" => $details->description,
      "total" => $details->plan->payment_definitions[0]->amount->value,
      "iva" => $details->plan->payment_definitions[0]->charge_models[0]->amount->value,
      "details" => $details->plan->payment_definitions[0],
      "user" => $details->payer->payer_info->first_name." ".$details->payer->payer_info->last_name
    ];
    return [
      '#theme' => 'receipt',
      '#sale' => $data,
    ];
  }
}