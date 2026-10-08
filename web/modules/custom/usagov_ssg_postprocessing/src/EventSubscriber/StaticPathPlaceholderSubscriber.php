<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\usagov_ssg_postprocessing\SsgMetricTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prevents invalid paths discovered during static generation.
 */
final class StaticPathPlaceholderSubscriber implements EventSubscriberInterface {

  use SsgMetricTrait;

  /**
   * Reacts to a path placeholder event.
   */
  public function excludeInvalidPaths(PathPlaceholderEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $path = $event->getPath();

    if ($path !== '/' && str_ends_with($path, '/')) {
      $event->setInvalid();
      $this->ssgMetricEnd('tome_path_placeholder_filter', $metric_start, 'invalid', [
        'path' => $path,
        'reason' => 'trailing_slash',
      ]);
      return;
    }

    if (preg_match('/(es\/)?node\/\d+$/', $path)) {
      $event->setInvalid();
      $this->ssgMetricEnd('tome_path_placeholder_filter', $metric_start, 'invalid', [
        'path' => $path,
        'reason' => 'node_path',
      ]);
      return;
    }

    $this->ssgMetricEnd('tome_path_placeholder_filter', $metric_start, 'valid', [
      'path' => $path,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TomeStaticEvents::PATH_PLACEHOLDER => ['excludeInvalidPaths'],
    ];
  }

}
