<?php

namespace Drupal\Tests\scheduler\Functional;

use Drush\TestTraits\DrushTestTrait;

/**
 * Tests the Drush commands provided by Scheduler.
 *
 * @group scheduler
 */
class SchedulerDrushTest extends SchedulerBrowserTestBase {

  use DrushTestTrait;

  /**
   * Tests the Scheduler Drush messages.
   */
  public function testDrushCronMessages() {
    // Run the plain command using the full scheduler:cron command name, and
    // check that all of the output messages are shown.
    $this->drush('scheduler:cron');
    $messages = $this->getErrorOutput();
    $this->assertStringContainsString('Lightweight cron run activated by drush command', $messages, 'Starting dblog message not found', TRUE);
    $this->assertStringContainsString('Lightweight cron run completed', $messages, 'Ending dblog message not found', TRUE);
    $this->assertStringContainsString('Message: Scheduler lightweight cron completed', $messages, 'Drush message not found', TRUE);

    // Use the sch:cron alias and simulate the --nomsg parameter, then check
    // that the drush confirmation message is not shown.
    $this->drush('sch:cron', [], ['nomsg' => TRUE]);
    $messages = $this->getErrorOutput();
    $this->assertStringNotContainsString('Message: Scheduler lightweight cron completed', $messages, '--nomsg parameter did not work', TRUE);

    // Use the alternative alias sch-cron and add the --nolog parameter, then
    // check that the dblog messages are not shown.
    $this->drush('sch-cron', [], ['nolog' => TRUE]);
    $messages = $this->getErrorOutput();
    $this->assertStringNotContainsString('Lightweight cron run activated by drush command', $messages, '--nolog parameter did not work for starting message', TRUE);
    $this->assertStringNotContainsString('Lightweight cron run completed', $messages, '--nolog parameter did not work for ending message', TRUE);
  }

  /**
   * Tests scheduled publishing via Drush command.
   */
  public function testDrushCronPublishing() {
    // Create a node which is scheduled for publishing.
    $title1 = $this->randomMachineName(20);
    $this->drupalCreateNode([
      'title' => $title1,
      'type' => $this->type,
      'publish_on' => strtotime('-3 hours'),
    ]);

    // Create a node which is scheduled for unpublishing.
    $title2 = $this->randomMachineName(20);
    $this->drupalCreateNode([
      'title' => $title2,
      'type' => $this->type,
      'unpublish_on' => strtotime('-2 hours'),
    ]);

    // Run Scheduler's drush cron command and check that the expected publishing
    // and unpublishing messages are found.
    $this->drush('scheduler:cron');
    $messages = $this->getErrorOutput();
    $this->assertStringContainsString(sprintf('%s: scheduled publishing of %s', $this->typeName, $title1), $messages, 'Scheduled publishing message not found', TRUE);
    $this->assertStringContainsString(sprintf('%s: scheduled unpublishing of %s', $this->typeName, $title2), $messages, 'Scheduled unpublishing message not found', TRUE);
  }

}
