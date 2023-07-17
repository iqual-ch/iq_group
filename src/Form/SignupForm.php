<?php

namespace Drupal\iq_group\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\iq_group\Service\IqGroupUserManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a signup form for the iq_group module.
 */
class SignupForm extends FormBase {

  /**
   * The entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager = NULL;

  /**
   * Configuration for the iq_group settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Drupal language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Gets the current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Gets the iq group user manager.
   *
   * @var \Drupal\iq_group\Service\IqGroupUserManager
   */
  protected $userManager;

  /**
   * SignupForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   * @param \Drupal\iq_group\Service\IqGroupUserManager $user_manager
   *   The iq group user manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $language_manager,
    AccountProxyInterface $current_user,
    IqGroupUserManager $user_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('iq_group.settings');
    $this->languageManager = $language_manager;
    $this->currentUser = $current_user;
    $this->userManager = $user_manager;
  }

  /**
   * Creates a SignupForm instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return \Drupal\iq_group\Form\SignupForm
   *   An instance of SignupForm.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('iq_group.user_manager')
    );
  }

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
    $account = $this->currentUser;
    $default_preferences = [];
    $group = $this->userManager->getGeneralGroup();
    $group_role_storage = $this->entityTypeManager->getStorage('group_role');
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
      $termsAndConditions = $this->config->get('terms_and_conditions') ?: "";
      $form['data_privacy'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('I have read the <a href="@terms_and_conditions" target="_blank">terms and conditions</a> and data protection regulations and I agree.', ['@terms_and_conditions' => $termsAndConditions]),
        '#default_value' => FALSE,
        '#default_value' => FALSE,
        '#weight' => 100,
        '#required' => TRUE,
      ];
      $language = $this->languageManager->getCurrentLanguage()->getId();
      $destination = \Drupal::service('path.current')->getPath();
      $form['register_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Create an account'),
        '#url' => Url::fromRoute('user.register', [
          'destination' => $destination,
        ]),
        '#weight' => 101,
      ];
      $form['login_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Login'),
        '#url' => Url::fromRoute('user.login', [
          'destination' => $destination,
        ]),
        '#weight' => 101,
      ];

    }
    else {
      if (in_array('subscription-lead', $groupRoles) || in_array('subscription-subscriber', $groupRoles)) {
        $user = $this->entityTypeManager->getStorage('user')->load($account->id());
        $selected_preferences = $user->get('field_iq_group_preferences')->getValue();
        foreach ($selected_preferences as $value) {
          // If it is not the general group, add it.
          if ($value['target_id'] != $this->config->get('general_group_id')) {
            $default_preferences = [...$default_preferences, $value['target_id']];
          }
        }
      }
    }
    $result = $this->entityTypeManager->getStorage('group')->loadMultiple();
    $options = [];
    /**
     * @var  \Drupal\group\Entity\Group $group
     */
    foreach ($result as $group) {
      /*
       * If it is not the general group
       * and it is not configured as hidden, add it.
       */
      $hidden_groups = $this->userManager->getIqGroupSettings()['hidden_groups'];
      $hidden_groups = explode(',', (string) $hidden_groups);

      if ($group->id() != $this->config->get('general_group_id') && !in_array($group->label(), $hidden_groups)) {
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
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
    $term_options = [];
    $language = $this->languageManager->getCurrentLanguage()->getId();
    foreach ($terms as $term) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term->tid);
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
    $example_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    if ($example_user->hasField('field_iq_group_branches')) {
      $default_branches = [];
      if ($account->isAuthenticated()) {
        $user = $this->entityTypeManager->getStorage('user')->load($account->id());
        $selected_branches = $user->get('field_iq_group_branches')
          ->getValue();
        foreach ($selected_branches as $value) {
          $default_branches = [...$default_branches, $value['target_id']];
        }
      }

      $form['branches_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Branches options'),
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
    $iqGroupSettings = $this->userManager->getIqGroupSettings();
    if ($this->currentUser->isAnonymous()) {
      // Try to load by email.
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $form_state->getValue('mail')]);
      if (empty($users)) {
        // No success, try to load by name.
        $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $form_state->getValue('mail')]);
      }
      $user = reset($users);
      // If the user exists, send an email to login.
      if ($user) {
        if ($user->field_iq_group_user_token->value == NULL) {
          $data = time();
          $data .= $user->id();
          $data .= $user->getEmail();
          $hash_token = Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
          $user->set('field_iq_group_user_token', $hash_token);
          $user->save();
        }
        $url = 'https://' . $this->userManager->getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
        if ($form_state->getValue('destination') != "") {
          $destination = $form_state->getValue('destination');
        }
        else {
          // @todo Set a destination if it is a signup form or not?
          if (!empty($iqGroupSettings['redirection_after_signup'])) {
            $destination = Url::fromUserInput($iqGroupSettings['redirection_after_signup'])->toString();
          }
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
          '#EMAIL_FOOTER' => nl2br((string) $iqGroupSettings['project_address']),
        ];
        $rendered = \Drupal::service('renderer')->renderPlain($renderable);
        $mail_subject = $this->t("Sign into your account");
        mb_internal_encoding("UTF-8");
        $mail_subject = mb_encode_mimeheader($mail_subject, 'UTF-8', 'Q');
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'iq_group';
        $key = 'iq_group_login';
        $to = $user->getEmail();
        $langcode = $this->languageManager->getCurrentLanguage()->getId();
        $params['subject'] = $mail_subject;
        $params['message'] = $rendered;
        $send = TRUE;
        $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      }
      // If the user does not exist.
      else {

        if ($form_state->getValue('name') != NULL) {
          $name = $form_state->getValue('name');
        }
        else {
          $name = $form_state->getValue('mail');
        }
        $currentLanguage = $language = $this->languageManager->getCurrentLanguage()->getId();
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
          if (!empty($iqGroupSettings['redirection_after_signup'])) {
            $destination = Url::fromUserInput($iqGroupSettings['redirection_after_signup'])->toString();
          }
        }
        $user = $this->userManager->createMember($user_data, [], $destination);
      }
      $this->messenger()->addMessage($this->t('Thanks for signing up. You will receive an e-mail with further information about the registration.'));
    }
    else {
      $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      if ($form_state->getValue('preferences') != NULL) {
        $user->set('field_iq_group_preferences', $form_state->getValue('preferences'));
      }
      $user->save();
      // Redirect if needed.
    }

  }

}
