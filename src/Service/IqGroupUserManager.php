<?php

namespace Drupal\iq_group\Service;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Client for All4Schools API.
 */
class IqGroupUserManager {

  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger = NULL;

  /**
   * The entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager = NULL;

  /**
   * Drupal language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request = NULL;

  /**
   * Configuration for the iq_group settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * UserController constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The symfony request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer
  ) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('iq_group.settings');
    $this->renderer = $renderer;
  }

  /**
   * Get configuration or state setting for A4S API.
   *
   * @param string $name
   *   Setting name to get the config or state from the A4S configuration.
   *
   * @return mixed
   *   The config value.
   */
  public function getConfig($name) {

    return $this->config->get($name);
  }

  /**
   * Return entity storage for the given type.
   *
   * @param string $type
   *   The entity type.
   *
   * @return mixed
   *   The corresponding entity storage manager.
   */
  public function getStorage($type) {

    return $this->entityTypeManager->getStorage($type);
  }

  /**
   * Get the general group.
   *
   * @return null|Group
   *   The general group or null.
   */
  public function getGeneralGroup() {
    if ($this->getConfig('general_group_id')) {
      return $this->getStorage('group')->load($this->getConfig('general_group_id'));
    }
    return NULL;
  }

  /**
   * Helper function assign group to a user.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group that is being assigned to the user.
   * @param \Drupal\user\Entity\User $user
   *   The user to whom the group is assigned to.
   * @param string $groupRoleId
   *   The group role id that the user will have in the group.
   */
  public function addGroupRoleToUser(Group $group, User $user, $groupRoleId) {
    // Add the subscriber role to the user in general group.
    $groupRole = $this->getStorage('group_role')->load($groupRoleId);
    if ($group->getMember($user)) {
      $membership = $group->getMember($user)->getGroupContent();
      if ($groupRole != NULL) {
        $membership->group_roles = [$groupRoleId];
        $membership->save();
      }
    }
    else {
      if ($groupRole != NULL) {
        $group->addMember($user, ['group_roles' => [$groupRole->id()]]);
      }
    }
  }

  /**
   * Helper function to get domain of the server.
   *
   * @todo rewrite / replace by core - is it even required?
   *
   * @return string
   *   The full url of the domain.
   */
  public function getDomain() {
    $domain = NULL;
    if (!empty($_SERVER["HTTP_HOST"]) || getenv("VIRTUAL_HOSTS")) {
      $virtual_host = "";
      if (getenv("VIRTUAL_HOSTS")) {
        $virtual_hosts = explode(",", getenv("VIRTUAL_HOSTS"));

        if (count($virtual_hosts) > 1) {
          $virtual_host = $virtual_hosts[1];
        }
        else {
          $virtual_host = $virtual_hosts[0];
        }
      }
      $domain = empty($virtual_host) ? $_SERVER["HTTP_HOST"] : $virtual_host;
      $language = $this->languageManager->getCurrentLanguage()->getId();
      $domain .= '/' . $language;
    }
    return $domain;
  }

  /**
   * Helper function to get the iq_group settings.
   *
   * @return array
   *   An array of settings.
   */
  public function getIqGroupSettings() {
    return [
      'project_name' => $this->getConfig('project_name') != NULL ? $this->getConfig('project_name') : "",
      'default_redirection' => $this->getConfig('default_redirection') != NULL ? $this->getConfig('default_redirection') : "",
      'general_group_id' => $this->getConfig('general_group_id') != NULL ? $this->getConfig('general_group_id') : "",
      'name' => $this->getConfig('name') != NULL ? $this->getConfig('name') : "Iqual",
      'from' => $this->getConfig('from') != NULL ? $this->getConfig('from') : "support@iqual.ch",
      'reply_to' => $this->getConfig('reply_to') != NULL ? $this->getConfig('reply_to') : "support@iqual.ch",
      'login_intro' => $this->getConfig('login_intro') != NULL ? $this->getConfig('login_intro') : "",
      'terms_and_conditions' => $this->getConfig('terms_and_conditions') != NULL ? $this->getConfig('terms_and_conditions') : "",
      'redirection_after_register' => $this->getConfig('redirection_after_register') != NULL ? $this->getConfig('redirection_after_register') : "/user/register",
      'redirection_after_account_delete' => $this->getConfig('redirection_after_account_delete') != NULL ? $this->getConfig('redirection_after_account_delete') : "",
      'redirection_after_signup' => $this->getConfig('redirection_after_signup') != NULL ? $this->getConfig('redirection_after_signup') : "",
      'project_address' => $this->getConfig('project_address') != NULL ? $this->getConfig('project_address') : "",
      'hidden_fields' => $this->getConfig('hidden_fields') != NULL ? $this->getConfig('hidden_fields') : "",
      'hidden_groups' => $this->getConfig('hidden_groups') != NULL ? $this->getConfig('hidden_groups') : "",
    ];
  }

  /**
   * Helper function to sign up a member and send a confirmation email.
   *
   * @param array $user_data
   *   The user data.
   * @param array $renderable
   *   The email renderable array.
   * @param string $destination
   *   The URL to redirect the user to.
   * @param bool $user_create
   *   Whether to create the user entity or not.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity.
   */
  public function createMember(array $user_data, array $renderable = [], $destination = NULL, $user_create = TRUE) {
    $iqGroupSettings = $this->getIqGroupSettings();
    if ($user_create) {
      $user = $this->getStorage('user')->create($user_data);
      $user->save();
    }
    else {
      $user = $this->getStorage('user')->load($user_data['id']);
    }
    $data = time();
    $data .= $user->id();
    $data .= $user->getEmail();
    $hash_token = Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
    $user->set('field_iq_group_user_token', $hash_token);
    $user->save();
    $url = 'https://' . $this->getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
    if (isset($destination) && $destination != NULL) {
      $url .= "?destination=" . $destination;
      if ($user_create) {
        $url .= "&signup=1";
      }
    }
    if (empty($renderable)) {
      // Default to signup template.
      $renderable = [
        '#theme' => 'signup_template',
        '#EMAIL_TITLE' => $this->t("Confirm subscription"),
        '#EMAIL_PREVIEW_TEXT' => $this->t("Please confirm subscription"),
        '#USER_PREFERENCES' => [],
        '#EMAIL_URL' => $url,
        '#EMAIL_PROJECT_NAME' => $iqGroupSettings['project_name'],
        '#EMAIL_FOOTER' => nl2br((string) $iqGroupSettings['project_address']),
      ];
    }
    else {
      $renderable['#EMAIL_URL'] = $url;
    }
    // Make array of user preference ids available to template.
    if (!$user->get('field_iq_group_preferences')->isEmpty()) {
      $renderable["#USER_PREFERENCES"] = array_filter(array_column($user->field_iq_group_preferences->getValue(), 'target_id'));
    }

    $mail_subject = $this->t('Confirm subscription');
    mb_internal_encoding("UTF-8");
    $mail_subject = mb_encode_mimeheader($mail_subject, 'UTF-8', 'Q');
    $rendered = $this->renderer->renderPlain($renderable);
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'iq_group';
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $key = 'iq_group_create_member';
    $to = $user->getEmail();
    $params = [];
    $params['subject'] = $mail_subject;
    $params['message'] = $rendered;
    $send = TRUE;

    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    if (empty($result)) {
      \Drupal::logger('iq_group')->notice('Error while sending email');
      return NULL;
    }
    return $user;
  }

  /**
   * Helper function to send a login link.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user to whom a login link is sent.
   * @param string $destination
   *   The destination to redirect when the login link is used.
   */
  public function sendLoginEmail(User $user, $destination = NULL) {
    $params = [];
    $iqGroupSettings = $this->getIqGroupSettings();
    if (empty($destination)) {
      if (!empty($iqGroupSettings['default_redirection'])) {
        $destination = $iqGroupSettings['default_redirection'];
      }
      else {
        $destination = '/member-area';
      }
    }
    $url = 'https://' . $this->getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
    if (isset($destination) && $destination != NULL) {
      $url .= "?destination=" . $destination;
    }
    $renderable = [
      '#theme' => 'login_template',
      '#EMAIL_TITLE' => $this->t("Sign into your account"),
      '#EMAIL_PREVIEW_TEXT' => $this->t("Sign into your @project_name account", ['@project_name' => $iqGroupSettings['project_name']]),
      '#EMAIL_URL' => $url,
      '#EMAIL_PROJECT_NAME' => $iqGroupSettings['project_name'],
      '#EMAIL_FOOTER' => nl2br((string) $iqGroupSettings['project_address']),
    ];
    $rendered = $this->renderer->renderPlain($renderable);
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
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    if ($result['result'] !== TRUE) {
      $this->messenger->addMessage($this->t('There was an error while sending login link to your email.'), 'error');
    }
    else {
      $this->messenger->addMessage($this->t('An e-mail has been sent with a login link to your account.'));
    }
  }

  /**
   * Helper function to set the reference fields when importing users.
   *
   * @param array $user_data
   *   The user data.
   * @param \Drupal\user\Entity\UserInterface $user
   *   The user entity.
   * @param string $option
   *   The reference field option.
   * @param array $entity_ids
   *   An array of entity ids.
   * @param string $import_key
   *   A string specifying the current import key (field).
   * @param string $field_key
   *   A string specifying the corresponding field name.
   * @param bool $found_user
   *   Whether a matching user was found or not.
   */
  public function setUserReferenceField(array &$user_data, UserInterface &$user, $option, array $entity_ids, $import_key, $field_key, bool $found_user) {

    // If the preferences do not need to be overidden, just return.
    if ($option == 'not_override_preferences' && $found_user) {
      $existing_entities = $user->get($field_key)->getValue();
      $existing_entities = array_filter(array_column($existing_entities, 'target_id'));
      unset($user_data[$import_key]);
      return $existing_entities;
    }
    $ids = [];
    $user_data[$import_key] = explode(',', (string) $user_data[$import_key]);
    foreach ($user_data[$import_key] as $entity) {
      if (in_array(trim($entity), $entity_ids)) {
        $ids[] = ['target_id' => (string) array_search(trim($entity), $entity_ids)];
      }
    }
    // Set preferences based on the preference override option.
    if ($option == 'override_preferences') {
      $ids = array_filter(array_column($ids, 'target_id'));
      $user_data[$import_key] = $ids;
    }
    elseif ($option == 'add_preferences') {
      $existing_entities = $user->get($field_key)->getValue();
      $existing_entities = array_filter(array_column($existing_entities, 'target_id'));
      $ids = array_filter(array_column($ids, 'target_id'));
      $ids = [...$existing_entities, ...$ids];
    }
    elseif ($option == 'remove_preferences') {
      $existing_entities = $user->get($field_key)->getValue();
      $existing_entities = array_filter(array_column($existing_entities, 'target_id'));
      $ids = array_filter(array_column($ids, 'target_id'));
      foreach ($ids as $delete_id) {
        unset($existing_entities[array_search($delete_id, $existing_entities)]);
      }
      $ids = $existing_entities;
    }
    unset($user_data[$import_key]);
    return array_unique($ids, SORT_REGULAR);
  }

  /**
   * Prepares an array of keys to be imported.
   *
   * @return array
   *   An array of fields to be imported.
   */
  public function userImportKeyOptions() {
    $user_import_key_options = [];
    $user_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user');
    foreach ($user_fields as $user_field) {
      $field_name = $user_field->getName();
      $field_label = $user_field->getLabel();
      if (str_ends_with($field_name, '_id') || $field_name == 'mail') {
        $user_import_key_options[$field_name] = $field_label;
      }
      if ($field_name == 'uid') {
        $user_import_key_options[$field_name] = $this->t('Drupal ID');
      }
    }
    return $user_import_key_options;
  }

}
