<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Unit;

use Drupal\scheduled_transitions\ScheduledTransitionsPermissions;
use Drupal\Tests\UnitTestCase;

/**
 * Tests scheduled transactions dynamic permissions.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsPermissions
 * @group scheduled_transitions
 */
class ScheduledTransitionsPermissionsUnitTest extends UnitTestCase {

  /**
   * Tests result of permission function.
   *
   * @covers ::viewScheduledTransitionsPermission
   */
  public function testViewScheduledTransitionsPermission(): void {
    $actual = ScheduledTransitionsPermissions::viewScheduledTransitionsPermission('foo', 'bar');
    $this->assertEquals('view scheduled transitions foo bar', $actual);
  }

  /**
   * Tests result of permission function.
   *
   * @covers ::viewScheduledTransitionsPermission
   */
  public function testAddScheduledTransitionsPermission(): void {
    $actual = ScheduledTransitionsPermissions::addScheduledTransitionsPermission('foo', 'bar');
    $this->assertEquals('add scheduled transitions foo bar', $actual);
  }

}
