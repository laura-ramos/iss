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
        $description = "Da clic para generar su factura<br>";
      } else {
        $url = Url::fromRoute('iss.user_data_form', ["user" => \Drupal::currentUser()->id()]);
        $description = "Para generar su factura debe registrar sus datos fiscales<br>";
      }
      $data['description'] = [
        '#type' => 'markup',
        '#markup' => $description
      ];
      $data['url'] = [
        '#type' => 'link',
        '#title' => 'Generar Factura',
        '#url' => $url,
      ];
      return $data;
    } else {
      return [
        '#markup' => 'Para generar tu factura inicia sesión',
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