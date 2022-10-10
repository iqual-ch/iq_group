<?php

namespace Drupal\iq_group\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is fired when a user is handled.
 */
class IqGroupEvent extends Event {

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
