<?php

namespace Drupal\iq_group\Event;

use Drupal\user\UserInterface;
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
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   */
  public function __construct(UserInterface &$user) {
    $this->user = &$user;
  }

  /**
   * Returns the event data for the user.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  public function &getUser() {
    return $this->user;
  }

}
