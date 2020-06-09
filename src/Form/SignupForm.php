<?php

namespace Drupal\iq_group\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Site\Settings;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;

class SignupForm extends FormBase
{

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'iq_group_signup_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $account = \Drupal::currentUser();
    $default_preferences = [];
    $group = Group::load(\Drupal::config('iq_group.settings')->get('general_group_id'));
    $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
    $groupRoles = $group_role_storage->loadByUserAndGroup($account, $group);
    $groupRoles = array_keys($groupRoles);
    if ($account->isAnonymous()) {
      $form['mail'] = [
        '#type' => 'email',
        '#title' => $this->t('Email address'),
        '#required' => !$account->getEmail(),
        '#default_value' => $account->getEmail(),
      ];
      $form['name'] = [
        '#type' => 'hidden',
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
      $termsAndConditions = \Drupal::config('iq_group.settings')->get('terms_and_conditions') ? \Drupal::config('iq_group.settings')->get('terms_and_conditions') : "https://www.sqs.ch/de/datenschutzbestimmungen";      $form['data_privacy'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('I have read the <a href="@terms_and_conditions" target="_blank">terms and conditions</a> and data protection regulations and I agree.', ['@terms_and_conditions' => $termsAndConditions]),        '#default_value' => false,
        '#default_value' => false,
        '#weight' => 100,
        '#required' => true,
      ];
      $destination = \Drupal::service('path.current')->getPath();
      $form['register_link'] = [
        '#type' => 'markup',
        '#markup' => '<a href="/user/register?destination=' . $destination . '">' . t('Register') . '</a> / ',
        '#weight' => 101
      ];
      $form['login_link'] = [
        '#type' => 'markup',
        '#markup' => '<a href="/user/login?destination=' . $destination . '">' . t('Login') . '</a>',
        '#weight' => 101
      ];

    }
    else {
      if(in_array('subscription-lead', $groupRoles) || in_array('subscription-subscriber', $groupRoles)) {
        $user = User::load($account->id());
        $selected_preferences = $user->get('field_iq_group_preferences')->getValue();
        foreach ($selected_preferences as $key => $value) {
          // If it is not the general group, add it.
          if ($value['target_id'] != \Drupal::config('iq_group.settings')->get('general_group_id'))
            $default_preferences = array_merge($default_preferences, [$value['target_id']]);
        }
      }
    }
    $result = \Drupal::entityTypeManager()->getStorage('group')->loadMultiple();
    $options = [];
    /**
     * @var  int $key
     * @var  \Drupal\group\Entity\Group $group
     */
    foreach ($result as $key => $group) {
      // If it is not the general group, add it.
      if ($group->id()!=\Drupal::config('iq_group.settings')->get('general_group_id'))
        $options[$group->id()] = $group->label();
    }
    $form['preferences'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => $default_preferences,
      '#title' => $this->t('Preferences')
    ];
    $form['destination'] = [
      '#type' => 'hidden',
      '#default_value' => ''
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sign up'),
      '#button_type' => 'primary',
    ];

    if (isset($form['data_privacy'])) {
      $form['actions']['submit']['#states'] = [
        'disabled' => [
          ':input[name="data_privacy"]' => [
            'checked' => false,
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $iqGroupSettingsConfig = \Drupal::config('iq_group.settings');
    $email_name = $iqGroupSettingsConfig->get('name') != NULL ? $iqGroupSettingsConfig->get('name') : 'Iqual';
    $email_from = $iqGroupSettingsConfig->get('from') != NULL ? $iqGroupSettingsConfig->get('from') : 'support@iqual.ch';
    $email_reply_to = $iqGroupSettingsConfig->get('reply_to') != NULL ? $iqGroupSettingsConfig->get('reply_to') : 'support@iqual.ch';
    $project_name = $iqGroupSettingsConfig->get('project_name') != NULL ? $iqGroupSettingsConfig->get('project_name') : "";

    if (\Drupal::currentUser()->isAnonymous()) {
      $result = \Drupal::entityQuery('user')
        ->condition('mail', $form_state->getValue('mail'), 'LIKE')
        ->execute();
      // If the user exists, send him an email to login.
      if (count($result) > 0) {
        $user = \Drupal\user\Entity\User::load(reset($result));
        if ($user->field_iq_group_user_token->value == NULL) {
          $data = time();
          $data .= $user->id();
          $data .= $user->getEmail();
          $hash_token =  Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
          $user->set('field_iq_group_user_token', $hash_token);
          $user->save();
        }
        $url = 'https://' . self::getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
        if ($form_state->getValue('destination') != "")  {
          $destination = $form_state->getValue('destination');
        }
        else {
          // @todo Set a destination if it is a signup form or not?
          //$destination = \Drupal\Core\Url::fromRoute('<current>')->toString();
        }
        if (isset($destination) && $destination != NULL) {
          $url .= "?destination=" . $destination;
        }
        $renderable = [
          '#theme' => 'login_template',
          '#EMAIL_TITLE' => 'Registrierung Benutzerkonto ' . $project_name ,
          '#EMAIL_PREVIEW_TEXT' => 'Registrierung Benutzerkonto ' . $project_name,
          '#EMAIL_URL' => $url,
          '#EMAIL_PROJECT_NAME' => $project_name
        ];
        $rendered = \Drupal::service('renderer')->renderPlain($renderable);
        $mail_subject = $this->t("Registrierung Benutzerkonto " . $project_name);
        mb_internal_encoding("UTF-8");
        $mail_subject  = mb_encode_mimeheader($mail_subject,'UTF-8','Q');
        $result = mail($user->getEmail(), $mail_subject , $rendered,
          "From: ".$email_name ." <". $email_from .">". "\r\nReply-to: ". $email_reply_to . "\r\nContent-Type: text/html");
      }
      // If the user does not exist
      else {

        if ($form_state->getValue('name') != NULL) {
          $name = $form_state->getValue('name');
        }
        else {
          $name = $form_state->getValue('mail');
        }
        $currentLanguage = $language = \Drupal::languageManager()->getCurrentLanguage()->getId();;
        $user = \Drupal\user\Entity\User::create([
          'mail' => $form_state->getValue('mail'),
          'name' => $name,
          'status' => 1,
          'preferred_langcode' => $currentLanguage,
        ]);
        $user->save();
        $data = time();
        $data .= $user->id();
        $data .= $user->getEmail();
        $hash_token =  Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
        $user->set('field_iq_group_user_token', $hash_token);
        if ($form_state->getValue('preferences') != NULL) {
          $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
        }
        $user->save();
        $url = 'https://' . self::getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
        if ($form_state->getValue('destination') != "")  {
          $destination = $form_state->getValue('destination');
        }
        else {
          // @todo Set a destination if it is a signup form or not?
          //$destination = \Drupal\Core\Url::fromRoute('<current>')->toString();
        }
        if (isset($destination) && $destination != NULL) {
          $url .= "?destination=" . $destination;
        }
        $renderable = [
          '#theme' => 'signup_template',
          '#EMAIL_TITLE' => 'Anmelde­bestätigung Newsletter',
          '#EMAIL_PREVIEW_TEXT' => 'Anmelde­bestätigung Newsletter',
          '#USER_PREFERENCES' => $user->field_iq_group_preferences->value,
          '#EMAIL_URL' => $url,
          '#EMAIL_PROJECT_NAME' => $project_name,
        ];
        $mail_subject = $this->t("Anmelde­bestätigung Newsletter");
        mb_internal_encoding("UTF-8");
        $mail_subject  = mb_encode_mimeheader($mail_subject,'UTF-8','Q');
        $rendered = \Drupal::service('renderer')->renderPlain($renderable);
        $result = mail($user->getEmail(), $mail_subject , $rendered,
          "From: ".$email_name ." <". $email_from .">". "\r\nReply-to: ". $email_reply_to . "\r\nContent-Type: text/html");
      }
      \Drupal::messenger()->addMessage($this->t('Thanks for signing up. You will get an email with more information to login.'));
    }
    else {
      $user = User::load(\Drupal::currentUser()->id());
      if ($form_state->getValue('preferences') != NULL) {
        $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
      }
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