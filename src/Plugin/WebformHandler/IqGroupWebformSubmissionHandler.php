<?php

namespace Drupal\iq_group\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\iq_group\Controller\UserController;
use Drupal\user\Entity\User;
use Drupal\webform\WebformSubmissionInterface;

/**
 * IQ Group Webform submission handler.
 *
 * @WebformHandler(
 *     id = "iq_group_submission_handler",
 *     label = @Translation("IQ Group Submission Handler"),
 *     category = @Translation("Form Handler"),
 *     description = @Translation("Creates and updates users on submissions"),
 *     cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *     results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 * @package Drupal\iq_group\Plugin\WebformHandler
 */
class IqGroupWebformSubmissionHandler extends \Drupal\webform\Plugin\WebformHandlerBase {

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $user_data = [];
    $userExists = TRUE;
    $values = $webform_submission->getData();

    $email = '';
    $user = NULL;
    foreach ($form['elements'] as $key => $element) {
      if ($element['#field_id'] == 'email') {
        $user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(
          [
            'mail' => $form_state->getValue($key)
          ]
        );
        if (count($user) == 0){
          $userExists = FALSE;

          $user_data['name'] = $form_state->getValue($key);
          $user_data['mail'] = $user_data['name'];
          $currentLanguage = $language = \Drupal::languageManager()->getCurrentLanguage()->getId();;
          $user_data['preferred_langcode'] = $currentLanguage;
          $user_data['langcode'] = $currentLanguage;
        }
        else {
          $user = reset($user);
          $email = $user->getEmail();
        }
      }
      else if ($form_state->getValue($key) && $element['#field_id'] == 'preferences') {
        $user_data['field_iq_group_preferences'] = $element['#field_value'];
      }
      // Set the branches through the industry content type.
      else if ($form_state->getValue($key) && $element['#field_id'] == 'branches') {
        $industry_id = $form_state->getValue($key);
        $industry = \Drupal::entityTypeManager()->getStorage('node')->load($industry_id);
        $branch = $industry->get('field_iq_group_branches')->getValue();
        $user_data['field_iq_group_branches'] = $branch;
      }
      else if (isset($element['#field_id']) && !empty($element['#field_id'])) {
        $user_data['field_iq_user_base_address'][$element['#field_id']] = $form_state->getValue($key);
      }
    }
    // Set the country code to Switzerland as it is required.
    $user_data['field_iq_user_base_address']['country_code'] = 'CH';

    // If the logged in user and the webform submission match.
    if (!empty($user) && $userExists && \Drupal::currentUser()->getEmail() == $email) {
      // Check if the user is on a branch/product page or a entity.
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node instanceof \Drupal\node\NodeInterface) {

        if ($node->hasField('field_iq_group_branches')) {
          // Add the branches from the entity to the user's.
          $default_branches = [];

          // User branches.
          $selected_branches = $user->get('field_iq_group_branches')->getValue();
          foreach ($selected_branches as $key => $value) {
            $default_branches = array_merge($default_branches, [$value['target_id']]);
          }
          // Entity branches.
          $entity_branches = $node->get('field_iq_group_branches')->getValue();
          foreach ($entity_branches as $key => $value) {
            $default_branches = array_merge($default_branches, [$value['target_id']]);
          }
          $user->set('field_iq_group_branches', $default_branches);
        }

        if ($node->hasField('field_iq_group_products')) {
          // User products.
          $default_products = [];
          $selected_products = $user->get('field_iq_group_products')->getValue();
          foreach ($selected_products as $key => $value) {
            $default_products = array_merge($default_products, [$value['target_id']]);
          }

          // Entity products.
          $entity_products = $node->get('field_iq_group_products')->getValue();
          foreach ($entity_products as $key => $value) {
            $default_products = array_merge($default_products, [$value['target_id']]);
          }
          $user->set('field_iq_group_products', $default_products);
        }
        $user->save();
      }

    }
    // If user exists, attribute the submission to the user.
    if (!empty($user) && $userExists) {
        $webform_submission->setOwnerId($user->id())->save();
    }
    // If the user does not exists and the user checked the newsletter,
    // Create the user and attribute the submission to the user.
    else if (!empty($user_data['field_iq_group_preferences'])) {
      // Check if the user is on a branch page.
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node instanceof \Drupal\node\NodeInterface) {
        // You can get nid and anything else you need from the node object.
        if ($node->hasField('field_iq_group_branches')) {
          $branch = $node->get('field_iq_group_branches')->getValue();
          $user_data['field_iq_group_branches'] = $branch;
        }
        if ($node->hasField('field_iq_group_products')) {
          $product = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $node->getTitle(), 'vid' => 'iq_group_products']);
          $user_data['field_iq_group_products'] = $product;
        }
      }
      if (!empty(\Drupal::config('iq_group.settings')->get('default_redirection'))) {
        $destination = \Drupal::config('iq_group.settings')->get('default_redirection');
      }
      else {
        $destination = '/member-area';
      }
      $user = UserController::createMember($user_data, [], $destination . '&source_form=' . rawurlencode($webform_submission->getWebform()->id()));
      $store = \Drupal::service('user.shared_tempstore')->get('iq_group.user_status');
      $store->set($user->id().'_pending_activation', true);
      $webform_submission->setOwnerId($user->id())->save();
    }
  }
}
