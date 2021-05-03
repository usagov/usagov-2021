<?php

namespace Drupal\Tests\linkit\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the linkit alterations on the text format forms.
 *
 * @group linkit
 */
class LinkitFormatAdminTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['editor', 'filter', 'linkit'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $account = $this->drupalCreateUser([
      'administer filters',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests that linkit filter is toggling the filter_html allowed tags.
   */
  public function testToggleLinkitFilter() {
    $session = $this->getSession();
    $page = $session->getPage();

    // Go to add filter page.
    $this->drupalGet('admin/config/content/formats/add');

    // Enable the 'Limit allowed HTML tags and correct faulty HTML' filter.
    $page->findField('filters[filter_html][status]')->check();

    $javascript = "(function (){ return jQuery('p.editor-update-message > strong').text(); })()";
    $this->assertNotContains('<a href hreflang data-entity-substitution data-entity-type data-entity-uuid title>', $session->evaluateScript($javascript));

    // Enable the 'Linkit filter' filter.
    $page->findField('filters[linkit][status]')->check();
    $this->assertContains('<a href hreflang data-entity-substitution data-entity-type data-entity-uuid title>', $session->evaluateScript($javascript));

    // Disable the 'Linkit filter' filter.
    $page->findField('filters[linkit][status]')->uncheck();
    $this->assertNotContains('<a href hreflang data-entity-substitution data-entity-type data-entity-uuid title>', $session->evaluateScript($javascript));
  }

}
