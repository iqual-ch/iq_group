<?php

namespace Drupal\iq_group_sqs_mautic\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

class UserEditForm extends FormBase
{

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'iq_group_sqs_mautic_user_edit_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = User::load(\Drupal::currentUser()->id());
    $default_name = $user->getAccountName();
    $form['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#maxlength' => USERNAME_MAX_LENGTH,
        '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
        '#required' => FALSE,
        '#default_value' => $default_name,
        '#attributes' => [
          'class' => ['username'],
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
        ],
      ];
    // show him the link to the resource
    $result = \Drupal::entityTypeManager()->getStorage('group')->loadMultiple();
    $options = [];
    /**
     * @var  int $key
     * @var  \Drupal\group\Entity\Group $group
     */
    foreach ($result as $key => $group) {
      $options[$group->id()] = $group->label();
    }
    $selected_preferences = $user->get('field_iq_group_preferences')->getValue();
    $default_value = [];
    foreach ($selected_preferences as $key => $value) {
      $default_value = array_merge($default_value, [$value['target_id']]);
    }
    $form['preferences'] = [
      '#type' => 'select',
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => $default_value,
      '#title' => $this->t('Preferences')
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password')
    ];
    $form['password_confirm'] = [
      '#type' => 'password',
      '#title' => $this->t('Confirm password')
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('password') != $form_state->getValue('password_confirm')) {
      $form_state->setError($form['password'], t('The specified passwords do not match.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    if ($form_state->getValue('name') != NULL) {
      $name = $form_state->getValue('name');
    }
    $user->set('name', $name);
    if ($form_state->getValue('password') != NULL) {
      $user->setPassword($form_state->getValue('password'));
      $user->addRole('lead');
    }
    $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
    $user->save();
    // Redirect after saving
    // It would be on the same page as the private resource, so no redirect.
  }
  public static function getDomain() {
    if (!empty($_SERVER["HTTP_HOST"]) || getenv("VIRTUAL_HOSTS")) {
      $virtual_host = "";
      if (getenv("VIRTUAL_HOSTS")) {
        $virtual_hosts = explode(",", getenv("VIRTUAL_HOSTS"));

        if (count($virtual_hosts) > 1) {
          $virtual_host = $virtual_hosts[1];
        } else {
          $virtual_host = $virtual_hosts[0];
        }
      }
      $domain = empty($virtual_host) ? $_SERVER["HTTP_HOST"] : $virtual_host;
    }
    return $domain;
  }
}