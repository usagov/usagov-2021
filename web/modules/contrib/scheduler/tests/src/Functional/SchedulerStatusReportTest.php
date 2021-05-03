<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Core\Url;

/**
 * Tests the Scheduler section of the status report.
 *
 * @group scheduler
 */
class SchedulerStatusReportTest extends SchedulerBrowserTestBase {

  /**
   * Tests that the Scheduler Time Check report is shown.
   */
  public function testStatusReport() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/reports/status');

    $this->assertSession()->pageTextContains('Scheduler Time Check');
    $this->assertSession()->pageTextContains('In most cases the server time should match Coordinated Universal Time (UTC) / Greenwich Mean Time (GMT)');

    $admin_regional_settings = Url::fromRoute('system.regional_settings');
    $this->assertLink('changed by admin users');
    $this->assertLinkByHref($admin_regional_settings->toString());

    $account_edit = Url::fromRoute('entity.user.edit_form', ['user' => $this->adminUser->id()]);
    $this->assertLink('user account');
    $this->assertLinkByHref($account_edit->toString());
  }

}
