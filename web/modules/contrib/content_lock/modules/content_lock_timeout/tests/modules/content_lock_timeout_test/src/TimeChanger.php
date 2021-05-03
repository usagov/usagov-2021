<?php

namespace Drupal\content_lock_timeout_test;

use Drupal\Component\Datetime\Time;

/**
 * This is a datetime.time service for testing only.
 *
 * @package Drupal\Tests\content_lock_timeout\Functional
 */
class TimeChanger extends Time {

  protected $overwrittenTime = NULL;

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime() {
    $time = \Drupal::keyValue('time')->get('time', NULL);
    if (!empty($time)) {
      return $time;
    }
    return parent::getCurrentTime();
  }

  /**
   * Forward current time to the given timestamp.
   *
   * @param int $time
   *   New time to set.
   */
  public function setCurrentTime($time) {
    \Drupal::keyValue('time')->set('time', $time);
  }

  /**
   * Reset the current time to the real time.
   */
  public function resetCurrentTime() {
    \Drupal::keyValue('time')->delete('time');
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    $time = \Drupal::keyValue('time')->get('time', NULL);
    if (!empty($time)) {
      return $time;
    }
    return parent::getRequestTime();
  }

}
