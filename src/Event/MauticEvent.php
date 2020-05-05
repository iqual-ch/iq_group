<?php

namespace Drupal\iq_group_sqs_mautic\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user is handled.
 */
class MauticEvent extends Event {

  /**
   * The event data for the user.
   *
   * @var mixed
   */
  protected $user = NULL;

  /**
   * Constructs the object.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user data.
   */
  public function __construct(&$user) {
    $this->user = &$user;
  }

  /**
   * Returns the event data for the user.
   *
   * @return mixed
   */
  public function &getUser() {
    return $this->user;
  }

}
