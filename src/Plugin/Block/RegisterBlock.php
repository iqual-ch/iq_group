<?php

namespace Drupal\iq_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Register' Block.
 *
 * @Block(
 *   id = "register_block",
 *   admin_label = @Translation("Register block"),
 *   category = @Translation("Forms"),
 * )
 */
class RegisterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\iq_group\Form\RegisterForm');
  }
}