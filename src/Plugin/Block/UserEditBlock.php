<?php

namespace Drupal\iq_group\Plugin\Block;

use Drupal\iq_group\Form\UserEditForm;
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
    $form = [];
    $currentPath = \Drupal::service('path.current')->getPath();
    $user_id = \Drupal::currentUser()->id();
    if ($currentPath == '/user/' . $user_id) {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $form['full_profile_edit'] = [
        '#type' => 'markup',
        '#markup' => '<div class="iqbm-button iqbm-text btn btn-cta"><a href="/' . $language . '/user/' . $user_id . '/edit">' . $this->t('Edit profile') . '</a></div>',
      ];
      return $form;
    }
    return \Drupal::formBuilder()->getForm(UserEditForm::class);
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
