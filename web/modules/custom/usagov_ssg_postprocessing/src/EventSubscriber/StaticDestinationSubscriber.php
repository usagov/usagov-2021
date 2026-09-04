<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\usagov_ssg_postprocessing\SsgMetricTrait;
use Drupal\usagov_ssg_postprocessing\StaticUrlTransformer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts letter query parameters into static output destinations.
 */
final class StaticDestinationSubscriber implements EventSubscriberInterface {

  use SsgMetricTrait;

  /**
   * Reacts to a destination modification event.
   */
  public function modifyDestination(ModifyDestinationEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $destination = $event->getDestination();
    $new_destination = StaticUrlTransformer::rewriteLetterPath($destination);
    $event->setDestination($new_destination);

    if ($destination !== $new_destination) {
      $this->ssgMetricEnd('tome_letter_modify_destination', $metric_start, 'end', [
        'changed' => TRUE,
        'destination' => $destination,
        'new_destination' => $new_destination,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TomeStaticEvents::MODIFY_DESTINATION => ['modifyDestination'],
    ];
  }

}
