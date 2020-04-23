<?php

namespace Drupal\iq_group_sqs_mautic\Form;

use Drupal\Core\Form\FormBase;
use Drupal\user\Entity\User;

class RegisterForm extends FormBase
{

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'iq_group_sqs_mautic_register_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $account = \Drupal::currentUser();
    if ($account->isAnonymous()) {
      $form['mail'] = [
        '#type' => 'email',
        '#title' => $this->t('Email address'),
        '#description' => $this->t('A valid email address. All emails from the system will be sent to this address. The email address is not made public and will only be used if you wish to receive a new password or wish to receive certain news or notifications by email.'),
        '#required' => !$account->getEmail(),
        '#default_value' => $account->getEmail(),
      ];
      $form['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username'),
        '#maxlength' => USERNAME_MAX_LENGTH,
        '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
        '#required' => FALSE,
        '#attributes' => [
          'class' => ['username'],
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
        ],
      ];
    }
    else {
      if(in_array('lead', $account->getRoles()) || in_array('subscriber', $account->getRoles())) {
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
        $user = User::load($account->id());
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
      }
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    if (\Drupal::currentUser()->isAnonymous()) {
      $result = \Drupal::entityQuery('user')
        ->condition('mail', $form_state->getValue('mail'), 'LIKE')
        ->execute();
      // If the user exists, send him an email to login.
      if (count($result) > 0) {
        $user = \Drupal\user\Entity\User::load(reset($result));
        if ($user->field_iq_group_user_token->value == NULL) {
          $user->set('field_iq_group_user_token', md5($user->getEmail()));
          $user->save();
        }
        $url = 'https://' . self::getDomain() . '/group/reset/password/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
        if ($_GET['destination'] != NULL) {
          $url .= "?destination=" . $_GET['destination'];
        }
        $result = mail($user->getEmail(), "SQS Mautic login", "Login through " . $url,
          "From: support@iqual.ch" . "\r\nReply-to: support@iqual.ch" . "\r\nContent-Type: text/html");
      }
      // If the user does not exist
      else {

        if ($form_state->getValue('name') != NULL) {
          $name = $form_state->getValue('name');
        }
        else {
          $name = $form_state->getValue('mail');
        }
        $user = \Drupal\user\Entity\User::create([
          'mail' => $form_state->getValue('mail'),
          'name' => $name,
          'status' => 1,
        ]);
        $user->set('field_iq_group_user_token', md5($user->getEmail()));
        $user->save();
        $url = 'https://' . self::getDomain() . '/group/reset/password/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
        if ($_GET['destination'] != NULL) {
          $url .= "?destination=" . $_GET['destination'];
        }
        $result = mail($user->getEmail(), "SQS Mautic login", "Register through " . $url,
          "From: support@iqual.ch" . "\r\nReply-to: support@iqual.ch" . "\r\nContent-Type: text/html");
      }
      \Drupal::messenger()->addMessage('We have sent you an email.');
    }
    else {
      $user = User::load(\Drupal::currentUser()->id());
      $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
      $user->save();
      // redirect if needed.
    }

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