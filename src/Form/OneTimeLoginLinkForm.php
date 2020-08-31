<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\iq_group\Controller\UserController;

/**
 * Class OneTimeLoginLinkForm.
 *
 * @package Drupal\iq_group\Form
 */
class OneTimeLoginLinkForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iq_group_onetime_loginlink_form';
  }

  /**
   * To Create one time link for login.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['user_email_name'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('E-mail address'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user_email_name_id = $form_state->getValue('user_email_name');
    if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/si', $user_email_name_id)) {
      $account = user_load_by_mail($user_email_name_id);
    }
    else {
      $account = user_load_by_name($user_email_name_id);
    }
    if(empty($account))
    {
      $form_state->setErrorByName('user_email_name', $this->t('Invalid User!'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_email_name = $form_state->getValue('user_email_name');
    if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/si', $user_email_name)) {
      $user_account = user_load_by_mail($user_email_name);
    }
    else {
      $user_account = user_load_by_name($user_email_name);
    }

    $login_url = user_pass_reset_url($user_account);

    if ($login_url) {
      $iqGroupSettings = UserController::getIqGroupSettings();
      \Drupal::messenger()->addMessage($user_email_name . ' wurde eine Email gesendet zur Passwortwiederherstellung.');
      $params['subject'] = $this->t('Password reset');
      //$params['message'] = OfferChecker::getEmailTemplate(t('Passwort wiederherstellen'), t('Passwort wiederherstellen'), '<a href="'. $login_url .'">Hier </a> können Sie Ihr Passwort wiederherstellen.');
      $params['message'] = '<a href="'. $login_url .'">'. $this->t('Click here') .'</a> ' . $this->t(' to reset your password');
      $result = mail($user_email_name, $params["subject"], $params['message'],
        "From: ".$iqGroupSettings['name'] ." <". $iqGroupSettings['from'] .">". "\r\nReply-to: ". $iqGroupSettings['reply_to'] . "\r\nContent-Type: text/html");

    }
  }

}
