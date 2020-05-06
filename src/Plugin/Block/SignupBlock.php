<?php

namespace Drupal\iq_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Signup' Block.
 *
 * @Block(
 *   id = "signup_block",
 *   admin_label = @Translation("Signup block"),
 *   category = @Translation("Forms"),
 * )
 */
class SignupBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\iq_group\Form\SignupForm');
  }
}