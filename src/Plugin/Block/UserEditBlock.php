<?php

namespace Drupal\iq_group_sqs_mautic\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'User edit' Block.
 *
 * @Block(
 *   id = "user_edit_block",
 *   admin_label = @Translation("User block"),
 *   category = @Translation("Forms"),
 * )
 */
class UserEditBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\iq_group_sqs_mautic\Form\UserEditForm');
  }

}