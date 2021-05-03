<?php

namespace Drupal\Tests\redirect\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * UI tests for redirect module.
 *
 * @group redirect
 */
class RedirectJavascriptTest extends WebDriverTestBase {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * @var \Drupal\redirect\RedirectRepository
   */
  protected $repository;

  /**
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['redirect', 'node', 'path', 'dblog', 'views', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->adminUser = $this->drupalCreateUser(
      [
        'administer redirects',
        'administer redirect settings',
        'access content',
        'bypass node access',
        'create url aliases',
        'administer taxonomy',
        'administer url aliases',
      ]
    );

    $this->repository = \Drupal::service('redirect.repository');

    $this->storage = $this->container->get('entity_type.manager')->getStorage('redirect');
  }

  /**
   * Test the redirect UI.
   */
  public function testRedirectUI() {
    $this->drupalLogin($this->adminUser);

    // Test populating the redirect form with predefined values.
    $this->drupalGet(
      'admin/config/search/redirect/add', [
      'query' => [
        'source' => 'non-existing',
        'source_query' => ['key' => 'val', 'key1' => 'val1'],
        'redirect' => 'node',
        'redirect_options' => ['query' => ['key' => 'val', 'key1' => 'val1']],
      ]
    ]
    );
    $this->assertFieldByName('redirect_source[0][path]', 'non-existing?key=val&key1=val1');
    $this->assertFieldByName('redirect_redirect[0][uri]', '/node?key=val&key1=val1');

    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', 'non-existing');
    $page->fillField('redirect_redirect[0][uri]', '/node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');

    // Try to find the redirect we just created.
    $redirect = $this->repository->findMatchingRedirect('non-existing');
    $this->assertEqual($redirect->getSourceUrl(), Url::fromUri('base:non-existing')->toString());
    $this->assertEqual($redirect->getRedirectUrl()->toString(), Url::fromUri('base:node')->toString());

    // After adding the redirect we should end up in the list. Check if the
    // redirect is listed.
    $this->assertUrl('admin/config/search/redirect');
    $this->assertSession()->pageTextContains('non-existing');
    $this->assertLink(Url::fromUri('base:node')->toString());
    $this->assertSession()->pageTextContains(t('Not specified'));

    // Test the edit form and update action.
    $this->clickLink(t('Edit'));
    $this->assertFieldByName('redirect_source[0][path]', 'non-existing');
    $this->assertFieldByName('redirect_redirect[0][uri]', '/node');
    $this->assertFieldByName('status_code', $redirect->getStatusCode());

    // Append a query string to see if we handle query data properly.
    $this->drupalPostForm(
      NULL, [
      'redirect_source[0][path]' => 'non-existing?key=value',
    ], t('Save')
    );

    // Check the location after update and check if the value has been updated
    // in the list.
    $this->assertUrl('admin/config/search/redirect');
    $this->assertSession()->pageTextContains('non-existing?key=value');

    // The path field should not contain the query string and therefore we
    // should be able to load the redirect using only the url part without
    // query.
    $this->storage->resetCache();
    $redirects = $this->repository->findBySourcePath('non-existing');
    $redirect = array_shift($redirects);
    $this->assertEqual($redirect->getSourceUrl(), Url::fromUri('base:non-existing', ['query' => ['key' => 'value']])->toString());

    // Test the source url hints.
    // The hint about an existing base path.
    $this->drupalGet('admin/config/search/redirect/add');
    $page->fillField('redirect_source[0][path]', 'non-existing?key=value');
    $page->fillField('redirect_redirect[0][uri]', '');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRaw(
      t(
        'The base source path %source is already being redirected. Do you want to <a href="@edit-page">edit the existing redirect</a>?',
        ['%source' => 'non-existing?key=value', '@edit-page' => $redirect->toUrl('edit-form')->toString()]
      )
    );

    // The hint about a valid path.
    $this->drupalGet('admin/config/search/redirect/add');
    $page->fillField('redirect_source[0][path]', 'node');
    $page->fillField('redirect_redirect[0][uri]', '');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRaw(
      t(
        'The source path %path is likely a valid path. It is preferred to <a href="@url-alias">create URL aliases</a> for existing paths rather than redirects.',
        ['%path' => 'node', '@url-alias' => Url::fromRoute('entity.path_alias.add_form')->toString()]
      )
    );

    // Test validation.
    // Duplicate redirect.
    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', 'non-existing?key=value');
    $page->fillField('redirect_redirect[0][uri]', '/node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $this->assertRaw(
      t(
        'The source path %source is already being redirected. Do you want to <a href="@edit-page">edit the existing redirect</a>?',
        ['%source' => 'non-existing?key=value', '@edit-page' => $redirect->toUrl('edit-form')->toString()]
      )
    );

    // Redirecting to itself.
    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', 'node');
    $page->fillField('redirect_redirect[0][uri]', '/node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $this->assertRaw(t('You are attempting to redirect the page to itself. This will result in an infinite loop.'));

    // Redirecting the front page.
    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', '<front>');
    $page->fillField('redirect_redirect[0][uri]', '/node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $this->assertRaw(t('It is not allowed to create a redirect from the front page.'));

    // Redirecting a url with fragment.
    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', 'page-to-redirect#content');
    $page->fillField('redirect_redirect[0][uri]', '/node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $this->assertRaw(t('The anchor fragments are not allowed.'));

    // Adding path that starts with /
    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', '/page-to-redirect');
    $page->fillField('redirect_redirect[0][uri]', '/node');
    // Wait on ajax is unpredictable, wait for one second.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $this->assertRaw(t('The url to redirect from should not start with a forward slash (/).'));

    // Test filters.
    // Add a new redirect.
    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', 'test27');
    $page->fillField('redirect_redirect[0][uri]', '/node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');

    // Filter with non existing value.
    $this->drupalGet(
      'admin/config/search/redirect', [
      'query' => [
        'status_code' => '3',
      ],
    ]
    );

    $rows = $this->xpath('//tbody/tr');
    // Check if the list has no rows.
    $this->assertTrue(count($rows) == 0);

    // Filter with existing values.
    $this->drupalGet(
      'admin/config/search/redirect', [
      'query' => [
        'redirect_source__path' => 'test',
        'status_code' => '2',
      ],
    ]
    );

    $rows = $this->xpath('//tbody/tr');
    // Check if the list has 1 row.
    $this->assertTrue(count($rows) == 1);

    $this->drupalGet(
      'admin/config/search/redirect', [
      'query' => [
        'redirect_redirect__uri' => 'nod',
      ],
    ]
    );

    $rows = $this->xpath('//tbody/tr');
    // Check if the list has 2 rows.
    $this->assertTrue(count($rows) == 2);

    // Test the plural form of the bulk delete action.
    $this->drupalGet('admin/config/search/redirect');
    $edit = [
      'redirect_bulk_form[0]' => TRUE,
      'redirect_bulk_form[1]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Apply to selected items'));
    $this->assertSession()->pageTextContains('Are you sure you want to delete these redirects?');
    $this->clickLink('Cancel');

    // Test the delete action.
    $page->find('css', '.dropbutton-toggle button')->press();
    $this->clickLink(t('Delete'));
    $this->assertRaw(
      t(
        'Are you sure you want to delete the URL redirect from %source to %redirect?',
        ['%source' => Url::fromUri('base:non-existing', ['query' => ['key' => 'value']])->toString(), '%redirect' => Url::fromUri('base:node')->toString()]
      )
    );
    $this->drupalPostForm(NULL, [], t('Delete'));
    $this->assertUrl('admin/config/search/redirect');

    // Test the bulk delete action.
    $this->drupalPostForm(NULL, ['redirect_bulk_form[0]' => TRUE], t('Apply to selected items'));
    $this->assertSession()->pageTextContains('Are you sure you want to delete this redirect?');
    $this->assertSession()->pageTextContains('test27');
    $this->drupalPostForm(NULL, [], t('Delete'));

    $this->assertSession()->pageTextContains(t('There is no redirect yet.'));
  }

}
