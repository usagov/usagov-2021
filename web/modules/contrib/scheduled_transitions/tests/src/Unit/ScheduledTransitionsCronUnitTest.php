<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\scheduled_transitions\ScheduledTransitionsHooks;
use Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests cron hooks.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsHooks
 * @group scheduled_transitions
 */
class ScheduledTransitionsCronUnitTest extends UnitTestCase {

  /**
   * A test config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $testConfigFactory;

  /**
   * Test jobs.
   *
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface
   */
  protected $testJobs;

  /**
   * ScheduledTransitionsCronUnitTest constructor.
   */
  public function __construct() {
    parent::__construct();
    $this->testConfigFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->testJobs = $this->createMock(ScheduledTransitionsJobsInterface::class);
  }

  /**
   * Tests creating queue items during cron.
   *
   * @covers ::cron
   */
  public function testCronOn(): void {
    $testConfig = $this->createMock(ImmutableConfig::class);
    $testConfig->expects($this->once())
      ->method('get')
      ->with('automation.cron_create_queue_items')
      ->willReturn(TRUE);

    $this->testConfigFactory->expects($this->once())
      ->method('get')
      ->with('scheduled_transitions.settings')
      ->willReturn($testConfig);

    $this->testJobs->expects($this->once())->method('jobCreator');

    $hooksService = new ScheduledTransitionsHooks($this->testConfigFactory, $this->testJobs);
    $hooksService->cron();
  }

  /**
   * Tests not creating queue items during cron.
   *
   * @covers ::cron
   */
  public function testCronOff(): void {
    $testConfig = $this->createMock(ImmutableConfig::class);
    $testConfig->expects($this->once())
      ->method('get')
      ->with('automation.cron_create_queue_items')
      ->willReturn(FALSE);

    $this->testConfigFactory->expects($this->once())
      ->method('get')
      ->with('scheduled_transitions.settings')
      ->willReturn($testConfig);

    $this->testJobs->expects($this->never())->method('jobCreator');

    $hooksService = new ScheduledTransitionsHooks($this->testConfigFactory, $this->testJobs);
    $hooksService->cron();
  }

}
