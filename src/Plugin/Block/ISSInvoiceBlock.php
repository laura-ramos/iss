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
      return \Drupal::formBuilder()->getForm('\Drupal\iss\Form\ISSGetInvoiceDataForm');
    } else {
      return [
        '#markup' => $this->t('Para generar tu factura inicia sesión'),
      ];
    }
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

      if (strchr($requestUri, '/prueba/exito')) {
        return AccessResult::allowedIfHasPermission($account, 'view iss block');
      }
      
    }

    return AccessResult::forbidden();
  }

}