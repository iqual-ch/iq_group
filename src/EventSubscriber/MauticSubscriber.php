<?php

namespace Drupal\iq_group\EventSubscriber;

use Drupal\iq_group\MauticEvents;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to handle mautic events.
 */
class MauticSubscriber implements EventSubscriberInterface {


  /**
   * Function to handle the event that occurred.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The event.
   */
  public function sendData(Event $event) {
    \Drupal::logger('iq_group')->notice('mautic event triggered');
    // Send data to mautic or to the mautic module.
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MauticEvents::USER_OPT_IN][] = ['sendData', 256];
    $events[MauticEvents::USER_DOWNLOAD_WHITEPAPER][] = ['sendData', 257];
    $events[MauticEvents::USER_PROFILE_EDIT][] = ['sendData', 258];
    $events[MauticEvents::USER_PROFILE_EDIT][] = ['sendData', 259];
    return $events;
  }

}
