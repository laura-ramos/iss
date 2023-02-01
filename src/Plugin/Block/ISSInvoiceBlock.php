<?php

namespace Drupal\iss\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Provides a invoicing block with all needed fields.
 *
 * @Block(
 *   id = "invoice_block",
 *   admin_label = @Translation("ISS Invoicing Block"),
 * )
 */
class ISSInvoiceBlock extends BlockBase {

  /**
  * {@inheritdoc}
  */
  public function build() {
    if (\Drupal::currentUser()->isAuthenticated()) {
      //return \Drupal::formBuilder()->getForm('\Drupal\iss\Form\ISSGetInvoiceDataForm');
      $query = \Drupal::database()->select('iss_user_invoice', 'i')->condition('uid', \Drupal::currentUser()->id())->fields('i');
      $currentUser = $query->execute()->fetchAssoc();
      if ($currentUser > 0) {
        $sale_query = \Drupal::database()->select('ppss_sales', 's')->condition('uid', \Drupal::currentUser()->id())->fields('s', ['id','details'])->orderBy('created', 'DESC');
        $sale_result = $sale_query->execute()->fetchAll();
        $url = Url::fromRoute('iss.invoice', ["id" => $sale_result[0]->id]);
        $description = "<br>Da click en el botón para generar tu factura.<br><br>
        <b>Nota:</b> En caso de no facturar al momento de la compra, tienes hasta el penúltimo día del mes para generarla, de lo contrario, se emite como factura para Público en General.";
      } else {
        $url = Url::fromRoute('iss.user_data_form', ["user" => \Drupal::currentUser()->id()]);
        $description = "<br>Favor de proporcionar sus datos fiscales tal como aparece en su <a href='https://www.sat.gob.mx/aplicacion/53027/genera-tu-constancia-de-situacion-fiscal' target='_blank'>Constancia de Situación Fiscal</a> para generarla correctamente.<br><br>
        <b>Nota:</b> En caso de no facturar al momento de la compra, tienes hasta el penúltimo día del mes para generarla, de lo contrario, se emite como factura para Público en General.";
      }
      $data['title'] = [
        '#type' => 'markup',
        '#markup' => "<h3>Información sobre Facturación</h3>"
      ];
      $data['url'] = [
        '#type' => 'link',
        '#title' => $currentUser > 0 ? "Generar Factura" : "Capturar Datos Fiscales",
        '#url' => $url,
        '#attributes' => [
          'class' => [
            'button',
          ],
        ]
      ];
      $data['description'] = [
        '#type' => 'markup',
        '#markup' => $description
      ];
      return $data;
    } else {
      return [
        '#markup' => '<h3>Información sobre Facturación</h3>Para generar tu factura es necesario <a href="/user/login">iniciar sesión</a> y en la opción de facturación  favor de proporcionar tus datos fiscales  tal como aparece en tu Constancia de Situación Fiscal para generarla correctamente.<br><br>
        <b>Nota:</b> En caso de no facturar al momento de la compra, tienes hasta el penúltimo día del mes para generarla, de lo contrario, se emite como factura para Público en General.',
      ];
    }
  }

  /**
   * @return int
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account)
  {
    // If viewing a node, get the fully loaded node object.
    $node = \Drupal::routeMatch()->getParameter('node');

    if (!(is_null($node))) {
      $request = \Drupal::request();
      $requestUri = $request->getRequestUri();

      if (strchr($requestUri, \Drupal::config('ppss.settings')->get('success_url'))) {
        return AccessResult::allowedIfHasPermission($account, 'view iss block');
      }
      
    }

    return AccessResult::forbidden();
  }
  
}