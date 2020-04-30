<?php

namespace Drupal\iq_group_sqs_mautic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\GroupRoleSynchronizer;
use Drupal\iq_group_sqs_mautic\Form\RegisterForm;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * IQ Group SQS Mautic controller.
 */
class UserController extends ControllerBase {

  function resetPassword($user_id, $token) {
    $user = User::load($user_id);
    // is the token valid for that user
    if ($token == $user->field_iq_group_user_token->value) {

      // if user ->id is same with the logged in user (check cookies)
      if (\Drupal::currentUser()->isAuthenticated()) {
        if ($user->id() == \Drupal::currentUser()->id()) {
          // is user opt-ed in (is user subscriber or lead)  if ($user->hasRole('subscriber'))
          // If there is a destination in the URL.
          if (isset($_GET['destination']) && $_GET['destination'] != NULL) {
            return new RedirectResponse(Url::fromUserInput($_GET['destination'])->toString());
          }
          else {
            return new RedirectResponse(Url::fromUserInput('/node/78')->toString());
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
      $group = Group::load('5');
      $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
      $groupRoles = $group_role_storage->loadByUserAndGroup($user, $group);
      $groupRoles = array_keys($groupRoles);


      if (!in_array('subscription-subscriber', $groupRoles)) {
        // if he is not, only change him to subscriber
        // Add the role in newsletter (id=5) group.
        $groupRole = GroupRole::load('subscription-subscriber');

        $group = Group::load('5');
        if ($group->getMember($user)) {
          $membership = $group->getMember($user)->getGroupContent();
          if ($groupRole != NULL && $groupRole->label() == 'Subscriber' && !in_array('subscription-subscriber', $membership->group_roles)) {
            $membership->group_roles[] = 'subscription-subscriber';
            $membership->save();
          }
        }else {
          if ($groupRole != NULL && $groupRole->label() == 'Subscriber') {
            $group->addMember($user, ['group_roles' => [$groupRole->id()]]);
          }
        }
      }
      $destination = "";
      if (isset($_GET['destination']) && $_GET['destination'] != NULL) {
        $destination = $_GET['destination'];
      }

      if (in_array('subscription-lead', $groupRoles)) {
        // Redirect him to the login page with the destination.
        $resetURL = 'https://' . RegisterForm::getDomain() . '/user/login';
        // @todo if there is a destination, attach it to the url
        /*if ($destination != "") {
          $resetURL .= "?destination=" . $destination;
        }*/
      }
      else {
        // instead of redirecting the user to the one-time-login, log him in.
        user_login_finalize($user);
        // it doesnt go here, because the login hook is triggered
        return new RedirectResponse($destination);

        //return new RedirectResponse(Url::fromUri('internal:/node/78')->toString());
        //$resetURL = user_pass_reset_url($user);
      }
//      return new RedirectResponse($resetURL, 302);
      return new RedirectResponse(Url::fromRoute('user.login', [], ['absolute' => TRUE])->toString());

    }
    else {
      // Redirect the user to the resource & the private resource says like u are invalid.
    }

    //if ($user->getPassword() != NULL) {
      \Drupal::messenger()->addMessage('good job you have a pwd');
      // go to login
    //}
//    else {
      \Drupal::messenger()
        ->addMessage('you have to set a pwd, cuz u dont have 1');
      // go to user edit
  //  }
  //}
  }

}
