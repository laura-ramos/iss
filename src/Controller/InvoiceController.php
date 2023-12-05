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
    $user_id = \Drupal::currentUser()->hasPermission('access user profiles') ? \Drupal::routeMatch()->getParameter('user') : $this->currentUser()->id();
    //create table header
    $header_table = array(
      'name' => $this->t('Plan'),
      'platform' => $this->t('Payment type'),
      'date' => $this->t('Date'),
      'status' => $this->t('Status'),
      'details' => $this->t('Options')
    );
    //select records from table ppss_sales
    $query = \Drupal::database()->select('ppss_sales', 's');
    $query->condition('uid', $user_id);
    $query->fields('s', ['id','uid','mail','platform','details', 'created', 'status', 'id_subscription']);
    $results = $query->execute()->fetchAll();

    $rows = array();
    foreach ($results as $data) {
      $sale = json_decode($data->details);
      $payments = Url::fromRoute('iss.show_purchase', ['user' => $user_id, 'id' => $data->id], []);

      if ($data->platform == 'Stripe') {
        $details = Url::fromRoute('stripe_payment.manage_subscription', ['customer' => $sale->customer], []);
      }
      
      //print the data from table
      $rows[] = array(
        'name' => $sale->description,
        'platform' => $data->platform,
        'date' => date('d/m/Y', $data->created),
        'status' => $data->status ? 'Activo' : 'Inactivo',
        'details' => $data->platform == 'Stripe' ? Link::fromTextAndUrl($this->t('Details'), $details) : '',
        'payments' => Link::fromTextAndUrl($this->t('Payments'), $payments),
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

  /**
   *
   * @param $id (ppss_sales_details.id)
   *   create a payment invoice.
   */
  public function createInvoice($id) {
    $config = $this->config('iss.settings');
    $api_key = $config->get('api_key');
    $api_endpoint = $config->get('api_endpoint');
    if (!(empty($api_key) || empty($api_endpoint))) {
      //check if payment exists
      $ppss_sales = \Drupal::database()->select('ppss_sales_details', 'payments');
      $ppss_sales->join('ppss_sales', 'sales', 'payments.sid = sales.id');
      $ppss_sales->condition('payments.id', $id);
      $ppss_sales->condition('uid', $this->currentUser()->id());
      $ppss_sales->fields('payments', ['id', 'created']);
      $payment = $ppss_sales->execute()->fetchAssoc();
      $message = '';
      if ($payment > 0) {
        $query_invoice = \Drupal::database()->select('iss_invoices', 'i')->condition('sid', $id)->fields('i');
        $invoice = $query_invoice->execute()->fetchAssoc();
        //check if an invoice already exists
        if ($invoice > 0) {
          $message = "<h5>Su factura ya fue generada</h5>";
        } else {
          //Validar la fecha de la compra
          $first_day = strtotime(date("Y-m-01"));//first day of the current month 
          $last_day = strtotime(date("Y-m-t 23:59:00"));//last day of the current month
          //validar la fecha del pago recurrente
          if ($payment['created'] >= $first_day && $payment['created'] < $last_day) {
            //validar que exista datos fiscales del usuario
            $query_user = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', $this->currentUser()->id())->fields('i');
            $user = $query_user->execute()->fetchAssoc();
            $url = Url::fromRoute('iss.user_data_form', ['user' => $this->currentUser()->id()]);
            if ($user > 0) {
              //llamar al sevicio para generar la factura
              $invoice = \Drupal::service('iss.api_service')->createInvoice(false, $payment['id']);
              //verificar la respuesta del servicio
              if ($invoice->code ?? false && $invoice->code == '200') {
                //mostrar los datos de la factura
                $pdf =  $invoice->cfdi->PDF;
                $xml =  $invoice->cfdi->XML;
                $UUID =  $invoice->cfdi->UUID;
                $created =  $invoice->cfdi->FechaTimbrado;
                $message = "<h5>Su factura ya fue generada</h5><p>Folio Fiscal UUID: $UUID<br>Fecha: $created <br> 
                <a href='$pdf' target='_blank'>Visualizar PDF</a> <a href='$xml' target='_blank'>Descargar XML</a></p>";
              } else {
                $this->messenger()->addError($invoice);
                $message = "<b>Error al generar factura:</b> Asegúrate de contar con datos fiscales válidos, favor de revisar <a href=".$url->toString().">aquí</a>";
              }
            } else {
              $this->messenger()->addError($this->t('Your billing information is missing'));
              $message = "Favor de registrar tus <a href=".$url->toString().">datos fiscales aquí.</a>";
            }
          } else {
            $this->messenger()->addWarning('No puedes generar facturas de fechas anteriones, favor de comunicarte con el administrador');
          }
        }
      } else {
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

  /**
   * @param $user (user id)
   * @param $id (ppss_sales_details.id)
   *   create a payment receipt.
   */
  public function receipt($user, $id){
    $user_id = \Drupal::currentUser()->hasPermission('access user profiles') ? $user : $this->currentUser()->id();
    //obtener los detalles del pago recurrente
    $ppss_sales = \Drupal::database()->select('ppss_sales_details', 'payments');
    $ppss_sales->join('ppss_sales', 'sales', 'payments.sid = sales.id');
    $ppss_sales->condition('payments.id', $id);
    $ppss_sales->condition('uid', $user_id);
    $ppss_sales->fields('payments', ['id', 'created', 'tax', 'price', 'total']);
    $ppss_sales->fields('sales', ['id', 'mail', 'platform', 'details']);
    $sales = $ppss_sales->execute()->fetchAssoc();

    if ($sales > 0) {
      $details = json_decode($sales['details']);
      if ($sales['platform'] == 'PayPal') {
        $user_name = $details->payer->payer_info->first_name." ".$details->payer->payer_info->last_name;
      } else if ($sales['platform'] == 'Stripe') {
        $user_name = $details->customer_details->name;
      }
      $data = [
        "folio" => $sales['id'],
        "email" => $sales["mail"],
        "platform" => $sales["platform"],
        "created" => date("d/m/Y", $sales["created"]),
        "product" => $details->description,
        "price" => $sales['price'],
        "total" => $sales['total'],
        "iva" => $sales['tax'],
        "user" => $user_name
      ];
      return [
        '#theme' => 'receipt',
        '#sale' => $data,
        '#cache' => ['max-age' => 0],
      ];
    } else {
      return [
        '#type' => 'markup',
        '#markup' => "No hay datos para mostrar"
      ];
    }
  }

  //sales listing
  public function listSales() {
    $form['form'] = \Drupal::formBuilder()->getForm('Drupal\iss\Form\FilterTableForm');
    $start_date = strtotime(\Drupal::request()->query->get('start_date') ?? date('Y-m-01'));
    $end_date = strtotime(\Drupal::request()->query->get('end_date') ?? date('Y-m-t'));
    //create table header
    $header_table = array(
      'folio' => $this->t('Folio'),
      'id' => $this->t('Subscription ID'),
      'name' => $this->t('Plan'),
      'total' => $this->t('Total price'),
      'platform' => $this->t('Payment type'),
      'date' => $this->t('Date'),
      'user' => $this->t('Email'),
      'invoice' => $this->t('Invoice'),
      'type' => $this->t('Type'),
      'event' => $this->t('Event ID'),
    );
    //get all payments from ppss_sales_details
    $query = \Drupal::database()->select('ppss_sales', 'sales');
    $query->join('ppss_sales_details', 'payments', 'sales.id = payments.sid');
    $query->leftJoin('iss_invoices', 'invoices', 'payments.id = invoices.sid');
    $query->leftJoin('iss_user_invoice', 'user_invoice', 'sales.uid = user_invoice.uid');
    $query->condition('payments.created', array($start_date, $end_date), 'BETWEEN');
    $query->fields('sales', ['platform', 'details', 'id_subscription']);
    $query->fields('payments', ['id','total', 'created', 'event_id']);
    $query->fields('invoices', ['uuid','p_general']);
    $query->fields('user_invoice', ['rfc', 'mail']);
    $query->orderBy('id', 'DESC');
    $results = $query->execute()->fetchAll();

    $rows = array();
    foreach ($results as $data) {
      $sale = json_decode($data->details);
      //print the data from table
      $rows[] = array(
        'folio' => $data->id,
        'id' => $data->id_subscription,
        'name' => $sale->description,
        'total' => number_format($data->total, 2, '.', ','),
        'platform' => $data->platform,
        'date' => date('d/m/Y', $data->created),
        'user' => $data->mail,
        'invoice' => $data->uuid ? 'Facturado': 'En espera',
        'type' => $data->p_general ? 'Público en general' : $data->rfc,
        'event' => $data->event_id,
      );
    }
    //display data in site
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header_table,
      '#rows' => $rows,
      '#empty' => 'No hay datos'
    ];
    return $form;
  }

  /**
   * 
   * @param $id
   *   Show puschase details by id sales.
   */
  public function showPurchase($user, $id) {
    $user_id = \Drupal::currentUser()->hasPermission('access user profiles') ? $user : $this->currentUser()->id();
    $ppss_sales = \Drupal::database()->select('ppss_sales', 's')
    ->condition('id', $id)->condition('uid', $user_id)
    ->fields('s', ['id','uid','mail','platform','details', 'created', 'status', 'id_subscription', 'frequency', 'expire']);
    $sales = $ppss_sales->execute()->fetchAssoc();
    //if exist sale
    if ($sales) {
      $details = json_decode($sales['details']);//get details sale
      //get listining recurring payments by id sale
      $payments_query = \Drupal::database()->select('ppss_sales_details', 'payments');
      $payments_query->leftJoin('iss_invoices', 'invoices', 'payments.id = invoices.sid');
      $payments_query->condition('payments.sid', $sales['id']);
      $payments_query->fields('payments', ['id', 'total', 'created', 'tax']);
      $payments_query->fields('invoices', ['uuid', 'p_general']);
      $payments_query->orderBy('payments.id', 'DESC');
      $payments = $payments_query->execute()->fetchAll();
      //cancellation url
      if ($sales['platform'] == 'PayPal') {
        $url_cancel = Url::fromRoute('ppss.cancel_subscription', ['user' => $user, 'id' => $sales['id']], []);
      } elseif ($sales['platform'] == 'Stripe') {
        $url_cancel = Url::fromRoute('stripe_payment.cancel_subscription', ['user' => $user, 'id' => $sales['id']], []);
      }

      $data = [
        "id" => $sales["id"],
        "subscription" => $sales["id_subscription"],
        "email" => $sales["mail"],
        "status" => $sales["status"] ? 'ACTIVE' : 'INACTIVE',
        "platform" => $sales["platform"],
        "created" => date("d/m/Y", $sales["created"]),
        "frequency" => $sales["frequency"],
        "product" => $details->description,
        "total" => $payments[0]->total,
        'payments' => $payments,
        'cancel' => $sales["status"] && $sales["expire"] == null ? Link::fromTextAndUrl($this->t('Cancel'), $url_cancel) : '',
        'last_pay' => $payments[0]->created ?? '',
        'expire' => $sales["expire"],
        'user' => $user_id
      ];
      //show data in template
      return [
        '#theme' => 'purchase-details',
        '#sale' => $data,
        '#cache' => ['max-age' => 0],
      ];
    } else {
      return [
        '#type' => 'markup',
        '#markup' => "No hay datos que mostrar"
      ];
    }
  }

  /**
   * 
   *   Show generated invoice.
   */
  public function showInvoice($user, $id) {
    $query_invoice = \Drupal::database()->select('iss_invoices', 'i')->condition('uuid', $id)->fields('i');
    $invoice = $query_invoice->execute()->fetchAssoc();

    $uuid =  $invoice['uuid'];
    $pdf =  $invoice['pdf'];
    $xml =  $invoice['xml'];
    $created =  $invoice['created'];
    $message = "<h5>Detalles de Facturación</h5><p>Folio Fiscal UUID: $uuid<br>Fecha: $created <br>
    <a href='$pdf' target='_blank'>Visualizar PDF</a> <a href='$xml' target='_blank'>Descargar XML</a></p>";

    return [
      '#type' => 'markup',
      '#markup' => "$message"
    ];
  }
}