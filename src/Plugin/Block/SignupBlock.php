<?php

namespace Drupal\iq_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\iq_group\Form\SignupForm;

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
    return \Drupal::formBuilder()->getForm(SignupForm::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'iq_group:signup_block';
    return $cache_tags;
  }

}
