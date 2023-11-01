<?php

namespace Drupal\iq_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\iq_group\Form\UserEditForm;

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
      $form['full_profile_edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit profile'),
        '#url' => Url::fromRoute('entity.user.edit_form', ['user' => $user_id]),
      ];
      $form['full_profile_edit']['#attributes']['class'][] = 'iqbm-button iqbm-text btn btn-cta';
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
