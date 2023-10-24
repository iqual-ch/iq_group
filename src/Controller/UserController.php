<?php

namespace Drupal\iq_group\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
use Drupal\iq_group\Service\IqGroupUserManager;
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
   * Gets the iq group user manager.
   *
   * @var \Drupal\iq_group\Service\IqGroupUserManager
   */
  protected $userManager;

  /**
   * The temp store factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Kill Switch for page caching.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * UserController constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher to dispatch events.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The symfony request stack.
   * @param \Drupal\iq_group\Service\IqGroupUserManager $user_manager
   *   The iq group user manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $kill_switch
   *   The page cache kill switch.
   */
  public function __construct(
    EventDispatcherInterface $event_dispatcher,
    MessengerInterface $messenger,
    RequestStack $request_stack,
    IqGroupUserManager $user_manager,
    SharedTempStoreFactory $temp_store_factory,
    KillSwitch $kill_switch
  ) {
    $this->eventDispatcher = $event_dispatcher;
    $this->messenger = $messenger;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $this->config('iq_group.settings');
    $this->userManager = $user_manager;
    $this->tempStoreFactory = $temp_store_factory;
    $this->killSwitch = $kill_switch;
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
      $container->get('request_stack'),
      $container->get('iq_group.user_manager'),
      $container->get('tempstore.shared'),
      $container->get('page_cache_kill_switch'),
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
    $store = $this->tempStoreFactory->get('iq_group.user_status');

    $this->killSwitch->trigger();
    $user = $this->entityTypeManager()->getStorage('user')->load($user_id);

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
    if ($user && !empty($token) && $token === $user->field_iq_group_user_token->value) {
      if (!empty($this->request->query->get('signup'))) {
        $this->messenger->addMessage($this->t('Thank you very much for registration to the newsletter.'));
      }

      // If user ->id is same with the logged in user (check cookies)
      $current_user = $this->currentUser();
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
      $group = $this->userManager->getGeneralGroup();
      $group_role_storage = $this->entityTypeManager()->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
      $groupRoles = array_keys($groupRoles);

      // If the user is not opted-in (not a subscriber nor a lead).
      if (!in_array('subscription-subscriber', $groupRoles) && !in_array('subscription-lead', $groupRoles)) {
        $this->userManager->addGroupRoleToUser($group, $user, 'subscription-subscriber');
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
            $this->userManager->addGroupRoleToUser($otherGroup, $user, 'subscription-subscriber');
          }
        }
      }

      $destination = "";
      if (!empty($this->request->get('destination'))) {
        $destination = $this->request->get('destination');
      }

      if (in_array('subscription-lead', $groupRoles)) {

        // Redirect the user to the login page with the destination.
        $resetURL = 'https://' . $this->userManager->getDomain() . '/user/login';
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
          if ($this->userManager->getConfig('default_redirection')) {
            $destination = $this->userManager->getConfig('default_redirection');
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

}
