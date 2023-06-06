<?php

namespace Drupal\iq_group\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * IQ Group member controller.
 */
class UserController extends ControllerBase {

  /**
   * The Event dispatcher.
   *
   * @var Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher = NULL;

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
   * UserController constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher to dispatch events.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The symfony request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    EventDispatcherInterface $event_dispatcher,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entityTypeManager,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory
  ) {
    $this->eventDispatcher = $event_dispatcher;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('iq_group.settings');
  }

  /**
   * Creates a new UserController object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\iq_group\Controller\UserController
   *   An instance of UserController or ControllerBase
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('messenger'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('tempstore.shared'),

    );
  }

  /**
   * Handle the password reset for a group user.
   *
   * @param int $user_id
   *   The id of the user.
   * @param string $token
   *   The authentication token.
   */
  public function resetPassword(int $user_id, $token) {
    /** @var \Drupal\user\SharedTempStore $store */
    $store = \Drupal::service('tempstore.shared')->get('iq_group.user_status');

    \Drupal::service('page_cache_kill_switch')->trigger();
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);

    if (!empty($store->get($user_id . '_pending_activation')) && !empty($user)) {
      $user->set('status', 1);
      $user->save();
      $store->delete($user_id . '_pending_activation');
    }
    elseif ((!empty($user) && $user->status->value == 0)) {
      $this->messenger->addMessage($this->t('Your account has been blocked.'), 'error');
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Is the token valid for that user.
    if (!empty($token) && $token === $user->field_iq_group_user_token->value) {
      if (!empty($this->request->query->get('signup'))) {
        $this->messenger->addMessage($this->t('Thank you very much for registration to the newsletter.'));
      }

      // If user ->id is same with the logged in user (check cookies)
      $current_user = \Drupal::currentUser();
      if ($current_user->isAuthenticated()) {
        if ($user->id() == $current_user->id()) {
          // If there is a destination in the URL.
          if (!empty($this->request->get('destination'))) {
            $destination = $this->request->get('destination');
          }
          else {
            $destination = $this->config->get('default_redirection');
          }
          /* If there are additional parameters (if the user was signed up
           * through webform), attach them to the redirect.
           */
          if (!empty($this->request->get('source_form'))) {
            $destination = Url::fromUserInput($destination, ['query' => ['source_form' => $this->request->get('source_form')]])->toString();
          }
          $response = new RedirectResponse($destination);
          return $response;

        }
        else {
          // Log out the user and continue.
          user_logout();
        }
      }
      else {
        // If there is anything to do when user is anonymous.
      }
      $group = Group::load($this->config->get('general_group_id'));
      $group_role_storage = $this->entityTypeManager->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
      $groupRoles = array_keys($groupRoles);

      // If the user is not opted-in (not a subscriber nor a lead).
      if (!in_array('subscription-subscriber', $groupRoles) && !in_array('subscription-lead', $groupRoles)) {
        self::addGroupRoleToUser($group, $user, 'subscription-subscriber');
        $this->eventDispatcher->dispatch(IqGroupEvents::USER_OPT_IN, new IqGroupEvent($user));
      }

      /*
       * Add member to the other groups that the user has selected in the
       * preferences field.
       */
      if (!in_array('subscription-lead', $groupRoles)) {
        $groups = $user->get('field_iq_group_preferences')->getValue();
        foreach ($groups as $otherGroup) {
          $otherGroup = Group::load($otherGroup['target_id']);
          if ($otherGroup != NULL) {
            self::addGroupRoleToUser($otherGroup, $user, 'subscription-subscriber');
          }
        }
      }

      $destination = "";
      if (!empty($this->request->get('destination'))) {
        $destination = $this->request->get('destination');
      }

      if (in_array('subscription-lead', $groupRoles)) {

        // Redirect the user to the login page with the destination.
        $resetURL = 'https://' . UserController::getDomain() . '/user/login';
        // @todo if there is a destination, attach it to the url
        if (empty($destination)) {
          if ($this->config->get('default_redirection')) {
            $destination = $this->config->get('default_redirection');
          }
        }
        if ($destination != "") {
          if (!empty($this->request->get('source_form'))) {
            $destination .= '&source_form=' . $this->request->get('source_form');
          }
          $resetURL .= "?destination=" . $destination;
        }
        $this->messenger->addMessage($this->t('Your account is now protected with password. You can login.'));
        // Return new RedirectResponse($resetURL);
        $response = new RedirectResponse($resetURL, 302);
        return $response;
      }
      else {
        /*
         * Instead of redirecting the user to the one-time-login,
         * log the user in.
         */
        user_login_finalize($user);
        // It doesnt go here, because the login hook is triggered.
        if (empty($destination)) {
          if ($this->config->get('default_redirection')) {
            $destination = $this->config->get('default_redirection');
          }
          else {
            $destination = "/homepage";
          }
        }

        if (!empty($this->request->get('source_form'))) {
          $destination = Url::fromUserInput($destination,
            [
              'query' => [
                'source_form' => $this->request->get('source_form'),
              ],
            ]
          )->toString();
        }
        $response = new RedirectResponse($destination);
        return $response;
      }
    }
    /*
     * Redirect the user to the resource &
     * the private resource says like u are invalid.
     */
    $this->messenger->addMessage(
      $this->t('This link is invalid or has expired.'),
      'error'
    );
    return new RedirectResponse(Url::fromRoute('user.register')->toString());
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
  public static function addGroupRoleToUser(Group $group, User $user, $groupRoleId) {
    // Add the subscriber role to the user in general (id=5) group.
    $groupRole = GroupRole::load($groupRoleId);

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
   */
  public static function getDomain() {
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
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $domain .= '/' . $language;
    }
    return $domain;
  }

  /**
   * Helper function to get the iq_group settings.
   */
  public static function getIqGroupSettings() {
    $iqGroupSettingsConfig = $this->config;
    return [
      'project_name' => $iqGroupSettingsConfig->get('project_name') != NULL ? $iqGroupSettingsConfig->get('project_name') : "",
      'default_redirection' => $iqGroupSettingsConfig->get('default_redirection') != NULL ? $iqGroupSettingsConfig->get('default_redirection') : "",
      'general_group_id' => $iqGroupSettingsConfig->get('general_group_id') != NULL ? $iqGroupSettingsConfig->get('general_group_id') : "",
      'name' => $iqGroupSettingsConfig->get('name') != NULL ? $iqGroupSettingsConfig->get('name') : "Iqual",
      'from' => $iqGroupSettingsConfig->get('from') != NULL ? $iqGroupSettingsConfig->get('from') : "support@iqual.ch",
      'reply_to' => $iqGroupSettingsConfig->get('reply_to') != NULL ? $iqGroupSettingsConfig->get('reply_to') : "support@iqual.ch",
      'login_intro' => $iqGroupSettingsConfig->get('login_intro') != NULL ? $iqGroupSettingsConfig->get('login_intro') : "",
      'terms_and_conditions' => $iqGroupSettingsConfig->get('terms_and_conditions') != NULL ? $iqGroupSettingsConfig->get('terms_and_conditions') : "",
      'redirection_after_register' => $iqGroupSettingsConfig->get('redirection_after_register') != NULL ? $iqGroupSettingsConfig->get('redirection_after_register') : "/user/register",
      'redirection_after_account_delete' => $iqGroupSettingsConfig->get('redirection_after_account_delete') != NULL ? $iqGroupSettingsConfig->get('redirection_after_account_delete') : "",
      'redirection_after_signup' => $iqGroupSettingsConfig->get('redirection_after_signup') != NULL ? $iqGroupSettingsConfig->get('redirection_after_signup') : "",
      'project_address' => $iqGroupSettingsConfig->get('project_address') != NULL ? $iqGroupSettingsConfig->get('project_address') : "",
      'hidden_fields' => $iqGroupSettingsConfig->get('hidden_fields') != NULL ? $iqGroupSettingsConfig->get('hidden_fields') : "",
      'hidden_groups' => $iqGroupSettingsConfig->get('hidden_groups') != NULL ? $iqGroupSettingsConfig->get('hidden_groups') : "",
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
  public static function createMember(array $user_data, array $renderable = [], $destination = NULL, $user_create = TRUE) {
    $iqGroupSettings = UserController::getIqGroupSettings();
    if ($user_create) {
      $user = $this->entityTypeManager->getStorage('user')->create($user_data);
      $user->save();
    }
    else {
      $user = $this->entityTypeManager->getStorage('user')->load($user_data['id']);
    }
    $data = time();
    $data .= $user->id();
    $data .= $user->getEmail();
    $hash_token = Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
    $user->set('field_iq_group_user_token', $hash_token);
    $user->save();
    $url = 'https://' . UserController::getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
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
        '#EMAIL_TITLE' => t("Confirm subscription"),
        '#EMAIL_PREVIEW_TEXT' => t("Please confirm subscription"),
        '#USER_PREFERENCES' => [],
        '#EMAIL_URL' => $url,
        '#EMAIL_PROJECT_NAME' => $iqGroupSettings['project_name'],
        '#EMAIL_FOOTER' => nl2br($iqGroupSettings['project_address']),
      ];
    }
    else {
      $renderable['#EMAIL_URL'] = $url;
    }
    // Make array of user preference ids available to template.
    if (!$user->get('field_iq_group_preferences')->isEmpty()) {
      $renderable["#USER_PREFERENCES"] = array_filter(array_column($user->field_iq_group_preferences->getValue(), 'target_id'));
    }

    $mail_subject = t('Confirm subscription');
    mb_internal_encoding("UTF-8");
    $mail_subject = mb_encode_mimeheader($mail_subject, 'UTF-8', 'Q');
    $rendered = \Drupal::service('renderer')->renderPlain($renderable);
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'iq_group';
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $key = 'iq_group_create_member';
    $to = $user->getEmail();
    $params = [];
    $params['subject'] = $mail_subject;
    $params['message'] = $rendered;
    $send = TRUE;

    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    /*$result = mail($user->getEmail(), $mail_subject , $rendered,
    "From: ".$iqGroupSettings['name'] ." <". $iqGroupSettings['from'] .">". "\r\nReply-to: ". $iqGroupSettings['reply_to'] . "\r\nContent-Type: text/html");*/
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
  public static function sendLoginEmail(User $user, $destination = NULL) {
    $params = [];
    $iqGroupSettings = UserController::getIqGroupSettings();
    if (empty($destination)) {
      if (!empty(\Drupal::config('iq_group.settings')->get('default_redirection'))) {
        $destination = $iqGroupSettings['default_redirection'];
      }
      else {
        $destination = '/member-area';
      }
    }
    $url = 'https://' . UserController::getDomain() . '/auth/' . $user->id() . '/' . $user->field_iq_group_user_token->value;
    if (isset($destination) && $destination != NULL) {
      $url .= "?destination=" . $destination;
    }
    $renderable = [
      '#theme' => 'login_template',
      '#EMAIL_TITLE' => t("Sign into your account"),
      '#EMAIL_PREVIEW_TEXT' => t("Sign into your @project_name account", ['@project_name' => $iqGroupSettings['project_name']]),
      '#EMAIL_URL' => $url,
      '#EMAIL_PROJECT_NAME' => $iqGroupSettings['project_name'],
      '#EMAIL_FOOTER' => nl2br($iqGroupSettings['project_address']),
    ];
    $rendered = \Drupal::service('renderer')->renderPlain($renderable);
    $mail_subject = t("Sign into your account");
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
    if ($result['result'] !== TRUE) {
      $this->messenger->addMessage(t('There was an error while sending login link to your email.'), 'error');
    }
    else {
      $this->messenger->addMessage(t('An e-mail has been sent with a login link to your account.'));
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
  public static function setUserReferenceField(array &$user_data, UserInterface &$user, $option, array $entity_ids, $import_key, $field_key, bool $found_user) {

    // If the preferences do not need to be overidden, just return.
    if ($option == 'not_override_preferences' && $found_user) {
      $existing_entities = $user->get($field_key)->getValue();
      $existing_entities = array_filter(array_column($existing_entities, 'target_id'));
      unset($user_data[$import_key]);
      return $existing_entities;
    }
    $ids = [];
    $user_data[$import_key] = explode(',', $user_data[$import_key]);
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
      $ids = array_merge($existing_entities, $ids);
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
    return array_unique($ids);
  }

  /**
   * Prepares an array of keys to be imported.
   *
   * @return array
   *   An array of fields to be imported.
   */
  public static function userImportKeyOptions() {
    $user_import_key_options = [];
    $user_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user');
    foreach ($user_fields as $user_field) {
      $field_name = $user_field->getName();
      $field_label = $user_field->getLabel();
      if (substr($field_name, -3) == '_id' || $field_name == 'mail') {
        $user_import_key_options[$field_name] = $field_label;
      }
      if ($field_name == 'uid') {
        $user_import_key_options[$field_name] = t('Drupal ID');
      }
    }
    return $user_import_key_options;
  }

}
