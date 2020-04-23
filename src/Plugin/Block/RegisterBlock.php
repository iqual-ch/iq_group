<?php

namespace Drupal\iq_group_sqs_mautic\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Register' Block.
 *
 * @Block(
 *   id = "hello_block",
 *   admin_label = @Translation("Register block"),
 *   category = @Translation("Forms"),
 * )
 */
class RegisterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\iq_group_sqs_mautic\Form\RegisterForm');

  }

}