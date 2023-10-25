<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for the one-time login settings of iq_group module.
 *
 * @package Drupal\iq_group\Form
 */
class OneTimeLoginLinkForm extends FormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * One Time Login Link Form constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    MailManagerInterface $mail_manager
  ) {
    $this->languageManager = $language_manager;
    $this->mailManager = $mail_manager;
  }

  /**
   * Creates a OneTimeLoginLinkForm instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return \Drupal\iq_group\Form\OneTimeLoginLinkForm
   *   An instance of OneTimeLoginLinkForm.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('plugin.manager.mail')
    );
  }

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
    if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/si', (string) $user_email_name_id)) {
      $account = user_load_by_mail($user_email_name_id);
    }
    else {
      $account = user_load_by_name($user_email_name_id);
    }
    if (empty($account)) {
      $form_state->setErrorByName('user_email_name', $this->t('Invalid User!'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $params = [];
    $user_email_name = $form_state->getValue('user_email_name');
    if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/si', (string) $user_email_name)) {
      $user_account = user_load_by_mail($user_email_name);
    }
    else {
      $user_account = user_load_by_name($user_email_name);
    }

    $login_url = user_pass_reset_url($user_account);

    if ($login_url) {
      $this->messenger()->addMessage($user_email_name . ' wurde eine Email gesendet zur Passwortwiederherstellung.');
      $params['subject'] = $this->t('Password reset');
      /*
       * $params['message'] =
       * OfferChecker::getEmailTemplate(t('Passwort wiederherstellen'),
       * t('Passwort wiederherstellen'), '<a href="'. $login_url .'">Hier </a>
       * können Sie Ihr Passwort wiederherstellen.');
       */
      $params['message'] = $this->t('<a href="@login_url">Click here</a> to reset your password', [
        '@login_url' => $login_url,
      ]);
      $to = $user_email_name;
      $module = 'iq_group';
      $key = 'iq_group_password_reset';
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      $send = TRUE;
      $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    }
  }

}
