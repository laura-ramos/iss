<?php

namespace Drupal\iss\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

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
    return \Drupal::formBuilder()->getForm('\Drupal\iss\Form\ISSGetInvoiceDataForm');
  }


}