<?php

namespace Drupal\iq_group_sqs_mautic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
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
          // is he opt-ed in (is he subscriber or lead)
          if ($user->hasRole('subscriber')) {
            return new RedirectResponse(Url::fromUserInput('/node/78')->toString());
          }
        }
        else {
          // Log out the user and continue.
          user_logout();
        }
      }
      // If he is anonymous
      else {

      }

      if (!in_array('subscriber', $user->getRoles())) {
        // if he is not, only change him to subscriber
        $user->addRole('subscriber');
        $user->save();
      }

      if (in_array('lead', $user->getRoles())) {
        // Redirect him to the login page with the destination.
        $resetURL = Url::fromRoute('user.login',['mail' => $user->getEmail()]);
        // show msg  " u are already a lead, login "
      }
      else {
        // instead of redirecting the user to the one-time-login, log him in.
        user_login_finalize($user);
        \Drupal::messenger()->addMessage('u logged in from tha link');
        return new RedirectResponse(Url::fromUserInput('/node/78')->toString());

        //return new RedirectResponse(Url::fromUri('internal:/node/78')->toString());
        //$resetURL = user_pass_reset_url($user);
      }

      if ($_GET['destination'] != NULL) {
        $resetURL .= "?destination=" . $_GET['destination'];
      }
      return new RedirectResponse($resetURL, 302);
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
