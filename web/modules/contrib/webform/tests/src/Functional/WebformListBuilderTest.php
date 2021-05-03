<?php

namespace Drupal\Tests\webform\Functional;

/**
 * Tests for webform list builder.
 *
 * @group webform
 */
class WebformListBuilderTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'webform', 'webform_test_submissions'];

  /**
   * Tests the webform overview filter.
   */
  public function testFilter() {
    $this->drupalLogin($this->rootUser);

    // Check filter default category and state.
    $this->drupalGet('/admin/structure/webform');
    $this->assertOptionSelected('edit-category', '');
    $this->assertOptionSelected('edit-state', '');

    // Set filter category and state.
    \Drupal::configFactory()->getEditable('webform.settings')
      ->set('form.filter_category', 'Test: Submissions')
      ->set('form.filter_state', 'open')
      ->save();

    // Check filter customized category and state.
    $this->drupalGet('/admin/structure/webform');
    $this->assertOptionSelected('edit-category', 'Test: Submissions');
    $this->assertOptionSelected('edit-state', 'open');

    // Check customized filter can still be cleared.
    $this->drupalGet('/admin/structure/webform', ['query' => ['category' => '', 'state' => '']]);
    $this->assertOptionSelected('edit-category', '');
    $this->assertOptionSelected('edit-state', '');
  }

  /**
   * Tests the webform overview access.
   */
  public function testAccess() {
    $assert_session = $this->assertSession();

    // Test with a superuser.
    $any_webform_user = $this->createUser([
      'access webform overview',
      'create webform',
      'edit any webform',
      'delete any webform',
    ]);
    $this->drupalLogin($any_webform_user);
    $list_path = '/admin/structure/webform';
    $this->drupalGet($list_path);
    $assert_session->linkExists('Test: Submissions');
    $assert_session->linkExists('Results');
    $assert_session->linkExists('Build');
    $assert_session->linkExists('Settings');
    $assert_session->linkExists('View');
    $assert_session->linkExists('Duplicate');
    $assert_session->linkExists('Delete');

    // Test with a user that only has submission access.
    $any_webform_submission_user = $this->createUser([
      'access webform overview',
      'view any webform submission',
      'edit any webform submission',
      'delete any webform submission',
    ]);
    $this->drupalLogin($any_webform_submission_user);
    $this->drupalGet($list_path);
    // Webform name should not be a link as the user doesn't have access to the
    // submission page.
    $assert_session->linkExists('Test: Submissions');
    $assert_session->linkExists('Results');
    $assert_session->linkNotExists('Build');
    $assert_session->linkNotExists('Settings');
    $assert_session->linkExists('View');
    $assert_session->linkNotExists('Duplicate');
    $assert_session->linkNotExists('Delete');

    // Disable webform page setting to ensure the view links get removed.
    $webform_config = \Drupal::configFactory()->getEditable('webform.webform.test_submissions');
    $settings = $webform_config->get('settings');
    $settings['page'] = FALSE;
    $webform_config->set('settings', $settings)->save();
    $this->drupalGet($list_path);
    $assert_session->linkNotExists('Test: Submissions');
    $assert_session->responseContains('Test: Submissions');
    $this->assertLinkNotInRow('Test: Submissions', 'View');

    // Test with role that is configured via webform access settings.
    $rid = $this->drupalCreateRole(['access webform overview']);
    $special_access_user = $this->createUser();
    $special_access_user->addRole($rid);
    $special_access_user->save();
    $access = $webform_config->get('access');
    $access['view_any']['roles'][] = $rid;
    $webform_config->set('access', $access)->save();
    $this->drupalLogin($special_access_user);
    $this->drupalGet($list_path);
    $assert_session->responseContains('Test: Submissions');
    $assert_session->linkExists('Results');
  }

  /**
   * Asserts a link is not in a row.
   *
   * @param string $row_text
   *   Text to find a row.
   * @param string $link
   *   The link to find.
   *
   * @throws \Exception
   *   When the row can't be found.
   */
  protected function assertLinkNotInRow($row_text, $link) {
    $row = $this->getSession()->getPage()->find('css', sprintf('table tr:contains("%s")', $row_text));
    if (!$row) {
      throw new \Exception($this->getSession()->getDriver(), 'table row', 'value', $row_text);
    }

    $links = $row->findAll('named', ['link', $link]);
    $this->assertEmpty($links, sprintf('Link with label %s found in row %s.', $link, $row_text));
  }

}
