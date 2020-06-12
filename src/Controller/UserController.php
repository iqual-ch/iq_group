<?php

namespace Drupal\iq_group\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\iq_group\Event\IqGroupEvent;
use Drupal\iq_group\IqGroupEvents;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * IQ Group controller.
 */
class UserController extends ControllerBase {

  /**
   * The Event dispatcher.
   *
   * @var Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher = NULL;

  /**
   * UserController constructor.
   *
   * @param Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher to dispatch events.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\iq_group\Controller\UserController
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher')
    );
  }

  function resetPassword($user_id, $token) {
    /** @var \Drupal\user\SharedTempStore $store */
    $store = \Drupal::service('user.shared_tempstore')->get('iq_group.user_status');

    \Drupal::service('page_cache_kill_switch')->trigger();
    $user = User::load($user_id);

    if (!empty($store->get($user_id . '_pending_activation'))) {
      $user->set('status', 1);
      $user->save();
      $store->delete($user_id . '_pending_activation');
    }
    else if ((!empty($user) && $user->status->value == 0)) {
      \Drupal::messenger()->addMessage($this->t('Your account has been blocked.'), 'error');
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    
    // is the token valid for that user
    if (!empty($token) && $token === $user->field_iq_group_user_token->value) {
      if (!empty($_GET['signup'])) {
        \Drupal::messenger()->addMessage(t('Thank you for signing up for newsletter.'));
      }

      // if user ->id is same with the logged in user (check cookies)
      if (\Drupal::currentUser()->isAuthenticated()) {
        if ($user->id() == \Drupal::currentUser()->id()) {
          // is user opt-ed in (is user subscriber or lead)  if ($user->hasRole('subscriber'))
          // If there is a destination in the URL.
          if (isset($_GET['destination']) && $_GET['destination'] != NULL) {
            return new RedirectResponse(Url::fromUserInput($_GET['destination'])->toString());
          }
          else {
            return new RedirectResponse(Url::fromUserInput(\Drupal::config('iq_group.settings')->get('default_redirection'))->toString());
          }

        }
        else {
          // Log out the user and continue.
          user_logout();
        }
      }
      // If user is anonymous
      else {
        // If there is anything to do when he is anonymous.
      }
      // Load General group (id=5) and get the roles for the user.
      $group = Group::load(\Drupal::config('iq_group.settings')->get('general_group_id'));
      $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
      $groupRoles = array_keys($groupRoles);

      // If he is not opted-in (not a subscriber nor a lead).
      if (!in_array('subscription-subscriber', $groupRoles) && !in_array('subscription-lead', $groupRoles)) {
        self::addGroupRoleToUser($group, $user, 'subscription-subscriber');
        $this->eventDispatcher->dispatch(IqGroupEvents::USER_OPT_IN, new IqGroupEvent( $user));
      }
      // Add member to the other groups that the user has selected in the
      // preferences field.
      $groups = $user->get('field_iq_group_preferences')->getValue();
      foreach ($groups as $key => $otherGroup) {
        $otherGroup = Group::load($otherGroup['target_id']);

        if ($otherGroup != NULL)
          self::addGroupRoleToUser($otherGroup, $user, 'subscription-subscriber');
      }


      $destination = "";
      if (isset($_GET['destination']) && $_GET['destination'] != NULL) {
        $destination = $_GET['destination'];
      }

      if (in_array('subscription-lead', $groupRoles)) {

        // Redirect him to the login page with the destination.
        $resetURL = 'https://' . UserController::getDomain() . '/user/login';
        // @todo if there is a destination, attach it to the url
        if (empty($destination)) {
          if (\Drupal::config('iq_group.settings')->get('default_redirection')) {
            $destination = \Drupal::config('iq_group.settings')->get('default_redirection');
          }
        }
        if ($destination != "") {
          $resetURL .= "?destination=" . $destination;
        }
        \Drupal::messenger()->addMessage('Ihr Konto ist Passwortgeschützt. Melden Sie sich an.');
        //return new RedirectResponse($resetURL);
         $response = new RedirectResponse($resetURL, 302);
         $response->send();
         return;
      }
      else {
        // instead of redirecting the user to the one-time-login, log him in.
        user_login_finalize($user);
        // it doesnt go here, because the login hook is triggered
        if (empty($destination)) {
          if (\Drupal::config('iq_group.settings')->get('default_redirection')) {
            $destination = \Drupal::config('iq_group.settings')->get('default_redirection');
          }
          else {
            $destination ="/homepage";
          }
        }

        return new RedirectResponse($destination);

        //return new RedirectResponse(Url::fromUri('internal:/node/78')->toString());
        //$resetURL = user_pass_reset_url($user);
      }
//      return new RedirectResponse($resetURL, 302);

    }
    else {
      // Redirect the user to the resource & the private resource says like u are invalid.
      \Drupal::messenger()->addMessage($this->t('This link is invalid or has expired.'), 'error');
      return new RedirectResponse(Url::fromRoute('user.register')->toString());
    }
  }

  public static function addGroupRoleToUser($group,$user, $groupRoleId) {
    // Add the subscriber role to the user in general (id=5) group.
    $groupRole = GroupRole::load($groupRoleId);

    if ($group->getMember($user)) {
      $membership = $group->getMember($user)->getGroupContent();
      if ($groupRole != NULL) {
        $membership->group_roles = [$groupRoleId];
        $membership->save();
      }
    }else {
      if ($groupRole != NULL) {
        $group->addMember($user, ['group_roles' => [$groupRole->id()]]);
      }
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
