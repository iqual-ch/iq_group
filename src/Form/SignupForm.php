<?php

namespace Drupal\iq_group\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Site\Settings;
use Drupal\group\Entity\Group;
use Drupal\iq_group\Controller\UserController;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 *
 */
class SignupForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'iq_group_signup_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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
        '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
        '#description' => $this->t("Some special characters are allowed, such as space, dot (.), hyphen (-), apostrophe ('), underscore(_) and the @ character."),
        '#required' => FALSE,
        '#attributes' => [
          'class' => ['username'],
          'autocorrect' => 'off',
          'autocapitalize' => 'off',
          'spellcheck' => 'false',
        ],
      ];
      $termsAndConditions = \Drupal::config('iq_group.settings')->get('terms_and_conditions') ?: "https://www.sqs.ch/de/datenschutzbestimmungen";      $form['data_privacy'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('I have read the <a href="@terms_and_conditions" target="_blank">terms and conditions</a> and data protection regulations and I agree.', ['@terms_and_conditions' => $termsAndConditions]),
        '#default_value' => FALSE,
        '#default_value' => FALSE,
        '#weight' => 100,
        '#required' => TRUE,
      ];
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $destination = \Drupal::service('path.current')->getPath();
      $form['register_link'] = [
        '#type' => 'markup',
        '#markup' => '<a href="/' . $language . '/user/register?destination=' . $destination . '">' . t('Create an account') . '</a> / ',
        '#weight' => 101,
      ];
      $form['login_link'] = [
        '#type' => 'markup',
        '#markup' => '<a href="/' . $language . '/user/login?destination=' . $destination . '">' . t('Login') . '</a>',
        '#weight' => 101,
      ];

    }
    else {
      if (in_array('subscription-lead', $groupRoles) || in_array('subscription-subscriber', $groupRoles)) {
        $user = User::load($account->id());
        $selected_preferences = $user->get('field_iq_group_preferences')->getValue();
        foreach ($selected_preferences as $key => $value) {
          // If it is not the general group, add it.
          if ($value['target_id'] != \Drupal::config('iq_group.settings')->get('general_group_id')) {
            $default_preferences = array_merge($default_preferences, [$value['target_id']]);
          }
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
      // If it is not the general group and it is not configured as hidden, add it.
      $hidden_groups = UserController::getIqGroupSettings()['hidden_groups'];
      $hidden_groups = explode(',', $hidden_groups);

      if ($group->id() != \Drupal::config('iq_group.settings')->get('general_group_id') && !in_array($group->label(), $hidden_groups)) {
        $options[$group->id()] = $group->label();
      }
    }
    $form['preferences'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => $default_preferences,
      '#title' => $this->t('Preferences'),
    ];

    $vid = 'branches';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    $term_options = [];
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    foreach ($terms as $term) {
      $term = Term::load($term->tid);
      if ($term->hasTranslation($language)) {
        $translated_term = \Drupal::service('entity.repository')
          ->getTranslationFromContext($term, $language);
        $term_options[$translated_term->id()] = $translated_term->getName();
      }
      else {
        $term_options[$term->id()] = $term->getName();
      }
    }

    /** @var \Drupal\user\Entity\User $example_user */
    $example_user = User::load(\Drupal::currentUser()->id());

    if ($example_user->hasField('field_iq_group_branches')) {
      $default_branches = [];
      if ($account->isAuthenticated()) {
        $user = User::load($account->id());
        $selected_branches = $user->get('field_iq_group_branches')
          ->getValue();
        foreach ($selected_branches as $key => $value) {
          $default_branches = array_merge($default_branches, [$value['target_id']]);
        }
      }

      $form['branches_settings'] = [
        '#type' => 'details',
        '#title' => t('Branches options'),
        '#open' => FALSE,
        '#optional' => FALSE,
      ];
      $form['branches_settings']['branches'] = [
        '#type' => 'checkboxes',
        '#options' => $term_options,
        '#default_value' => $default_branches,
        '#multiple' => TRUE,
        '#title' => $this->t('Branches'),
        '#group' => 'branches_settings',
      ];
    }
    $form['destination'] = [
      '#type' => 'hidden',
      '#default_value' => '',
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
            'checked' => FALSE,
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $params = [];
    $iqGroupSettings = UserController::getIqGroupSettings();
    if (\Drupal::currentUser()->isAnonymous()) {
      $result = \Drupal::entityQuery('user')
        ->condition('mail', $form_state->getValue('mail'), 'LIKE')
        ->execute();
      // If the user exists, send him an email to login.
      if ((is_countable($result) ? count($result) : 0) > 0) {
        $user = User::load(reset($result));
        if ($user->field_iq_group_user_token->value == NULL) {
          $data = time();
          $data .= $user->id();
          $data .= $user->getEmail();
          $hash_token = Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
          $user->set('field_iq_group_user_token', $hash_token);
          $user->save();
        }
        $url = 'https://' . UserController::getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
        if ($form_state->getValue('destination') != "") {
          $destination = $form_state->getValue('destination');
        }
        else {
          // @todo Set a destination if it is a signup form or not?
          $destination = Url::fromUserInput($iqGroupSettings['redirection_after_signup'])->toString();
        }
        if (isset($destination) && $destination != NULL) {
          $url .= "?destination=" . $destination . "&signup=1";
        }
        $renderable = [
          '#theme' => 'login_template',
          '#EMAIL_TITLE' => $this->t("Sign into your account"),
          '#EMAIL_PREVIEW_TEXT' => $this->t("Sign into your @project_name account", ['@project_name' => $iqGroupSettings['project_name']]),
          '#EMAIL_URL' => $url,
          '#EMAIL_PROJECT_NAME' => $iqGroupSettings['project_name'],
          '#EMAIL_FOOTER' => nl2br($iqGroupSettings['project_address']),
        ];
        $rendered = \Drupal::service('renderer')->renderPlain($renderable);
        $mail_subject = $this->t("Sign into your account");
        mb_internal_encoding("UTF-8");
        $mail_subject = mb_encode_mimeheader($mail_subject, 'UTF-8', 'Q');
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'iq_group';
        $key = 'iq_group_login';
        $to = $user->getEmail();
        $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $params['subject'] = $mail_subject;
        $params['message'] = $rendered;
        $send = TRUE;
        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      }
      // If the user does not exist.
      else {

        if ($form_state->getValue('name') != NULL) {
          $name = $form_state->getValue('name');
        }
        else {
          $name = $form_state->getValue('mail');
        }
        $currentLanguage = $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        ;
        $user_data = [
          'mail' => $form_state->getValue('mail'),
          'name' => $name,
          'status' => 1,
          'preferred_langcode' => $currentLanguage,
          'langcode' => $currentLanguage,
        ];
        if ($form_state->getValue('preferences') != NULL) {
          $user_data['field_iq_group_preferences'] = $form_state->getValue('preferences');
        }
        if ($form_state->getValue('branches') != NULL) {
          $user_data['field_iq_group_branches'] = $form_state->getValue('branches');
        }
        if ($form_state->getValue('destination') != "") {
          $destination = $form_state->getValue('destination');
        }
        else {
          // @todo Set a destination if it is a signup form or not?
          $destination = Url::fromUserInput($iqGroupSettings['redirection_after_signup'])->toString();
        }
        $user = UserController::createMember($user_data, [], $destination);
      }
      \Drupal::messenger()->addMessage($this->t('Thanks for signing up. You will receive an e-mail with further information about the registration.'));
    }
    else {
      $user = User::load(\Drupal::currentUser()->id());
      if ($form_state->getValue('preferences') != NULL) {
        $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
      }
      $user->save();
      // Redirect if needed.
    }

  }

}
