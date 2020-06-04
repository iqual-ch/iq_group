<?php

namespace Drupal\iq_group\Plugin\Block;

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
    $currentPath =  \Drupal::service('path.current')->getPath();
    $user_id = \Drupal::currentUser()->id();
    if ($currentPath == '/user/'. $user_id) {
      $form['full_profile_edit'] = [
        '#type' => 'markup',
        '#markup' => '<div class="iqbm-button iqbm-text btn btn-cta"><a href="/user/' . $user_id . '/edit">' . t('Edit profile') . '</a></div>'
      ];
      return $form;
    }
    return \Drupal::formBuilder()->getForm('Drupal\iq_group\Form\UserEditForm');
  }

}