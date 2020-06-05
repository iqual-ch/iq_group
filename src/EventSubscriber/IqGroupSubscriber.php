<?php

namespace Drupal\iq_group\EventSubscriber;

use Drupal\iq_group\IqGroupEvents;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to handle iq_group events.
 */
class IqGroupSubscriber implements EventSubscriberInterface {


  /**
   * Function to handle the event that occurred.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The event.
   */
  public function sendData(Event $event) {
    \Drupal::logger('iq_group')->notice('iq_group event triggered');
    // Send data to any API integration module.
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[IqGroupEvents::USER_OPT_IN][] = ['sendData', 256];
    $events[IqGroupEvents::USER_DOWNLOAD_WHITEPAPER][] = ['sendData', 257];
    $events[IqGroupEvents::USER_PROFILE_EDIT][] = ['sendData', 258];
    $events[IqGroupEvents::USER_PROFILE_EDIT][] = ['sendData', 259];
    return $events;
  }

}
