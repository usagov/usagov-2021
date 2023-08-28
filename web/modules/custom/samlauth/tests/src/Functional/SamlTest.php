<?php

namespace Drupal\Tests\samlauth\Functional;

use Drupal\samlauth\Controller\SamlController;
use Drupal\Tests\BrowserTestBase;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\user\RoleInterface;

/**
 * Semi random tests for the samlauth module.
 *
 * The most important part (login functionality) isn't tested yet.
 *
 * @group samlauth
 */
class SamlTest extends BrowserTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * Modules to Enable.
   *
   * @var array
   */
  protected static $modules = ['samlauth'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Import testsaml config.
    $config = file_get_contents(__DIR__ . "/../../fixtures/samlauth.authentication.yml");
    $config = Yaml::decode($config);
    \Drupal::configFactory()->getEditable('samlauth.authentication')->setData($config)->save();
  }

  /**
   * Tests the Admin Page.
   */
  public function testAdminPage() {
    // Test that the administration page is present.
    // These aren't very good tests, but the form and config systems are already
    // thoroughly tested, so we're just checking the basics here.
    $web_user = $this->drupalCreateUser(['configure saml']);
    $this->drupalLogin($web_user);
    $this->drupalGet('admin/config/people/saml');
    $this->assertSession()->pageTextContains('Login / Logout');
    $this->assertSession()->pageTextContains('Service Provider');
    $this->assertSession()->pageTextContains('Identity Provider');
    $this->assertSession()->pageTextContains('User Info and Syncing');
    $this->assertSession()->pageTextContains('SAML Message Construction');
    $this->assertSession()->pageTextContains('SAML Message Validation');
  }

  /**
   * Tests metadata coming back.
   */
  public function testMetadata() {
    $web_user = $this->drupalCreateUser(['view sp metadata']);
    $this->drupalLogin($web_user);

    // Test that we get metadata.
    $this->drupalGet('saml/metadata');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('entityID="samlauth"');
  }

  /**
   * Tests behavior of password reset / login screen.
   */
  public function testPasswordReset() {
    $core_msg_mail_sent = version_compare(\Drupal::VERSION, '9.2.0-dev') >= 0
      ? 'an email will be sent with instructions to reset your password.'
      : 'Further instructions have been sent to your email address.';
    $mails = $this->drupalGetMails();
    $initial_count_mails = count($mails);
    $config = \Drupal::configFactory()->getEditable(SamlController::CONFIG_OBJECT_NAME);

    $web_user = $this->drupalCreateUser();
    $this->drupalLogin($web_user);

    // Baseline: The 'real' error about being a SAML user is suppressed.
    $this->assertEquals(FALSE, $config->get('local_login_saml_error'), "'local_login_saml_error' config is FALSE.");

    // Baseline: un-linked users can still reset their password.
    $this->drupalGet('user/password');
    $this->submitForm([], 'Submit');
    $this->assertSession()->responseContains($core_msg_mail_sent);
    $mails = $this->drupalGetMails();
    $this->assertEquals($initial_count_mails + 1, count($mails));

    // Linked users only can if a role-based config value says they can. They
    // do not see a message about this by default, but the mail is not sent.
    \Drupal::service('externalauth.authmap')->save($web_user, 'samlauth', $this->randomString());
    $this->drupalGet('user/password');
    $this->submitForm([], 'Submit');
    $this->assertSession()->responseContains($core_msg_mail_sent);
    $mails = $this->drupalGetMails();
    $this->assertEquals($initial_count_mails + 1, count($mails));
    // The user does see an error if the appropriate config value is set.
    $config->set('local_login_saml_error', TRUE)->save();
    $this->drupalGet('user/password');
    $this->submitForm([], 'Submit');
    $this->assertSession()->responseContains('This user is only allowed to log in through an external authentication provider.');
    $this->assertSession()->responseNotContains($core_msg_mail_sent);
    $mails = $this->drupalGetMails();
    $this->assertEquals($initial_count_mails + 1, count($mails));

    // Linked users can reset their password if they have the proper permission.
    \Drupal::configFactory()->getEditable(SamlController::CONFIG_OBJECT_NAME)
      ->set('drupal_login_roles', [RoleInterface::AUTHENTICATED_ID])->save();
    $this->submitForm([], 'Submit');
    $this->assertSession()->responseContains($core_msg_mail_sent);
    $mails = $this->drupalGetMails();
    $this->assertEquals($initial_count_mails + 2, count($mails));

    // The same logic applies to the login form. Test in reverse order: now
    // that the user has the permission, we can log in...
    $this->drupalLogout();
    $this->drupalLogin($web_user);

    // ...but not if we revoke the permission (and the user has an authmap
    // entry). The fact that we see the specific message means that the
    // user/password was actually recognized.
    $this->drupalLogout();
    \Drupal::configFactory()->getEditable(SamlController::CONFIG_OBJECT_NAME)
      ->set('drupal_login_roles', [])->save();
    $this->drupalGet(Url::fromRoute('user.login'));
    $this->submitForm([
      'name' => $web_user->getAccountName(),
      'pass' => $web_user->passRaw,
    ], t('Log in'));
    $this->assertSession()->responseContains('This user is only allowed to log in through an external authentication provider.');
    // The user sees the general (untrue) "Unrecognized" error if the
    // appropriate config value is not set.
    $config->set('local_login_saml_error', FALSE)->save();
    $this->drupalGet(Url::fromRoute('user.login'));
    $this->submitForm([
      'name' => $web_user->getAccountName(),
      'pass' => $web_user->passRaw,
    ], t('Log in'));
    $this->assertSession()->responseNotContains('This user is only allowed to log in through an external authentication provider.');
    $this->assertSession()->responseContains('Unrecognized username or password.');
  }

}
