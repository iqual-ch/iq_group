<?php

namespace Drupal\iq_group\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
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
class IqGroupWebformSubmissionHandler extends WebformHandlerBase {

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $send_login_email = NULL;
    $user_data = [];
    $userExists = TRUE;

    $email = '';
    $user = NULL;
    foreach ($form['elements'] as $key => $element) {
      if (empty($element['#field_id'])) {
        continue;
      }
      if ($element['#field_id'] == 'email' && ($email = $form_state->getValue($key))) {
        $user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(
          [
            'mail' => $email,
          ]
        );
        if (count($user) == 0) {
          $userExists = FALSE;

          $user_data['mail'] = $user_data['name'] = $email;
          $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
          $user_data['preferred_langcode'] = $currentLanguage;
          $user_data['langcode'] = $currentLanguage;
        }
        else {
          /** @var \Drupal\user\UserInterface $user */
          $user = reset($user);
          $email = $user->getEmail();
        }
      }
      elseif ($element['#field_id'] == 'preferences' && $form_state->getValue($key)) {
        $user_data['field_iq_group_preferences'] = $element['#field_value'];
        if (!empty($element['#send_login_email'])) {
          $send_login_email = $element['#send_login_email'];
        }
        else {
          $send_login_email = FALSE;
        }
      }
      // Set the branches through the industry content type.
      elseif ($element['#field_id'] == 'branches' && ($industry_id = $form_state->getValue($key))) {
        /** @var \Drupal\taxonomy\TermInterface $industry */
        $industry = \Drupal::entityTypeManager()->getStorage('node')->load($industry_id);
        $branch = $industry->get('field_iq_group_branches')->getValue();
        $user_data['field_iq_group_branches'] = $branch;
      }
      else {
        $user_data['field_iq_user_base_address'][$element['#field_id']] = $form_state->getValue($key);
      }
    }
    // Set the country code to Switzerland as it is required.
    $user_data['field_iq_user_base_address']['country_code'] = 'CH';

    // If the logged in user and the webform submission match.
    if (!empty($user) && $userExists && \Drupal::currentUser()->getEmail() == $email) {
      // Check if the user is on a branch/product page or a entity.
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node instanceof NodeInterface) {

        if ($node->hasField('field_iq_group_branches')) {
          // Add the branches from the entity to the user's.
          $default_branches = [];

          // User branches.
          $selected_branches = $user->get('field_iq_group_branches')->getValue();
          foreach ($selected_branches as $key => $value) {
            $default_branches = [...$default_branches, $value['target_id']];
          }
          // Entity branches.
          $entity_branches = $node->get('field_iq_group_branches')->getValue();
          foreach ($entity_branches as $key => $value) {
            $default_branches = [...$default_branches, $value['target_id']];
          }
          $user->set('field_iq_group_branches', $default_branches);
        }

        if ($node->hasField('field_iq_group_products')) {
          // User products.
          $default_products = [];
          $selected_products = $user->get('field_iq_group_products')->getValue();
          foreach ($selected_products as $key => $value) {
            $default_products = [...$default_products, $value['target_id']];
          }

          // Entity products.
          $entity_products = $node->get('field_iq_group_products')->getValue();
          foreach ($entity_products as $key => $value) {
            $default_products = [...$default_products, $value['target_id']];
          }
          $user->set('field_iq_group_products', $default_products);
        }
        $user->save();
      }

    }
    /*
     * If user exists, but is not logged in,
     * attribute the submission to the user.
     */
    if (!empty($user) && $userExists) {
      $webform_submission->setOwnerId($user->id())->save();

      // Send login email to the user.
      if (\Drupal::currentUser()->getEmail() != $email) {
        if ($send_login_email) {
          if (!empty(\Drupal::config('iq_group.settings')->get('default_redirection'))) {
            $destination = \Drupal::config('iq_group.settings')->get('default_redirection');
          }
          else {
            $destination = '/member-area';
          }
          \Drupal::service('iq_group.user_manager')->sendLoginEmail($user, $destination . '&source_form=' . rawurlencode($webform_submission->getWebform()->id()));
        }
      }
    }
    // If the user does not exists and the user checked the newsletter,
    // Create the user and attribute the submission to the user.
    elseif (!empty($user_data['field_iq_group_preferences'])) {
      // Check if the user is on a branch page.
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node instanceof NodeInterface) {
        // You can get nid and anything else you need from the node object.
        if ($node->hasField('field_iq_group_branches')) {
          $branch = $node->get('field_iq_group_branches')->getValue();
          $user_data['field_iq_group_branches'] = $branch;
        }
        if ($node->hasField('field_iq_group_products')) {
          $product = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(
            [
              'name' => $node->getTitle(),
              'vid' => 'iq_group_products',
            ]
          );
          $user_data['field_iq_group_products'] = $product;
        }
      }
      if (!empty(\Drupal::config('iq_group.settings')->get('default_redirection'))) {
        $destination = \Drupal::config('iq_group.settings')->get('default_redirection');
      }
      else {
        $destination = '/member-area';
      }
      $user = \Drupal::service('iq_group.user_manager')->createMember($user_data, [], $destination . '&source_form=' . rawurlencode($webform_submission->getWebform()->id()));
      $store = \Drupal::service('tempstore.shared')->get('iq_group.user_status');
      $store->set($user->id() . '_pending_activation', TRUE);
      $webform_submission->setOwnerId($user->id())->save();
    }
  }

}
