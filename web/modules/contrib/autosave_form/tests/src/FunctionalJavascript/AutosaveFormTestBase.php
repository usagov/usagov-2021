<?php

namespace Drupal\Tests\autosave_form\FunctionalJavascript;

use Behat\Mink\Exception\ResponseTextException;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Basic functionality for autosave form tests.
 */
abstract class AutosaveFormTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['autosave_form', 'autosave_form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Profile to use.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * The user to test with.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * The autosave submission interval in milliseconds.
   *
   * @var int
   */
  protected $interval = 2000;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->prepareSetUp();
  }

  /**
   * Prepares the test setup.
   */
  protected function prepareSetUp() {
    // Adjust the autosave form submission interval.
    \Drupal::configFactory()
      ->getEditable('autosave_form.settings')
      ->set('interval', $this->interval)
      ->save();

    $this->webUser = $this->drupalCreateUser($this->getUserPermissions());
    $this->prepareUser();
    $this->drupalLogin($this->webUser);
  }

  /**
   * Prepares the user before login.
   */
  protected function prepareUser() {}

  /**
   * Loads the page and submits the autosave restore.
   *
   * @param string|\Drupal\Core\Url $path
   *   Drupal path or URL to load into Mink controlled browser.
   * @param $last_autosave_timestamp
   *   The last autosave timestamp for the provided path to check
   *   that the correct restore message is shown to the user.
   */
  protected function reloadPageAndRestore($path, $last_autosave_timestamp) {
    $this->logHtmlOutput(__FUNCTION__ . ' before reload');
    $this->drupalGet($path);
    $this->logHtmlOutput(__FUNCTION__ . ' after reload');

    $this->assertAutosaveResumeDiscardMessageIsShown(TRUE, $last_autosave_timestamp);

    $this->pressAutosaveRestoreButton();
    $this->waitForAutosaveResumeButtonToDisappear();
  }

  /**
   * Asserts whether the autosave resume/discard message is shown or not.
   *
   * @param bool $excepted
   *   The expectation whether the message should be shown or not.
   * @param $last_autosave_timestamp
   *   The autosave state timestamp contained in the message.
   * @param int $timeout
   *   (Optional) Timeout in seconds, defaults to 10.
   *
   * @throws ResponseTextException
   */
  protected function assertAutosaveResumeDiscardMessageIsShown($excepted, $last_autosave_timestamp, $timeout = 30) {
    $date = \Drupal::service('date.formatter')->format((int) $last_autosave_timestamp, 'custom', 'M d, Y H:i');
    $message = (string) t('A version of this page you were editing at @date was saved as a draft. Do you want to resume editing or discard it?', ['@date' => $date]);

    if ($excepted) {

      while ($timeout > 0) {
        $timeout--;

        try {
          $this->assertSession()->pageTextContains($message);
          break;
        }
        catch (ResponseTextException $e) {
          if ($timeout <= 0) {
            throw $e;
          }
        }

        if ($timeout > 0) {
          sleep(1);
        }
      }
    }
    else {
      sleep($timeout);
      $this->assertSession()->pageTextNotContains($message);
    }
  }

  /**
   * Waits until a specific count of autosave submits have been triggered.
   *
   * @param $count
   *   The count of the autosave submits to wait for.
   *
   * @return bool
   *   TRUE, if the autosave submits have occurred, FALSE otherwise.
   */
  protected function waitForAutosaveSubmits($count) {
    /** @var \Drupal\Core\State\StateInterface $state */
    $state = \Drupal::state();
    $state->resetCache();
    $start_count = $state->get('autosave_submit_count', 0);

    // Define a timeout for the autosave submissions by multiplying the .
    $system_slowness_factor = 5;
    $timeout = ($this->interval / 1000) * $system_slowness_factor * $count;
    $deadline_time = time() + $timeout;

    while (TRUE) {
      $state->resetCache();
      $current_count = $state->get('autosave_submit_count', 0);
      if ($current_count >= ($start_count + $count)) {
        return TRUE;
      }

      if (time() >= $deadline_time) {
        return FALSE;
      }

      // sleep for 0.1 second.
      usleep(100000);
    }
  }

  /**
   * Asserts that the autosave form library is loaded.
   *
   * @param bool $loaded
   */
  protected function assertAutosaveFormLibraryLoaded($loaded) {
    $dom_loaded = $this->getSession()->evaluateScript('Drupal.hasOwnProperty("autosaveForm")');
    $this->assertEquals($loaded, $dom_loaded);
  }

  /**
   * Presses the autosave restore button.
   */
  protected function pressAutosaveRestoreButton() {
    $page = $this->getSession()->getPage();
    $restore_button = $page->find('css', '.autosave-form-resume-button');
    $this->assertNotEmpty($restore_button);
    $restore_button->press();
  }

  /**
   * Waits for the autosave restore button to disappear.
   *
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 30000.
   *
   * @return bool
   *   TRUE, if the element has disappeared, FALSE otherwise.
   */
  protected function waitForAutosaveResumeButtonToDisappear($timeout = 30000) {
    $restored = $this->waitForElementToDisappear('css', '.autosave-form-resume-button', $timeout);
    $this->logHtmlOutput(__FUNCTION__ . ' after resume button disappears');
    $this->assertTrue($restored);
    return $restored;
  }

  /**
   * Waits for the specified selector and to disappear.
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param string|array $locator
   *   The selector locator.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return bool
   *   TRUE, if the element has disappeared, FALSE otherwise.
   */
  protected function waitForElementToDisappear($selector, $locator, $timeout = 10000) {
    $page = $this->getSession()->getPage();

    $result = $page->waitFor($timeout / 1000, function() use ($page, $selector, $locator) {
      $element = $page->find($selector, $locator);
      if (empty($element)) {
        return TRUE;
      }
      return FALSE;
    });

    return $result;
  }

  /**
   * Logs the html of the current page.
   */
  protected function logHtmlOutput($debug_text = NULL) {
    if ($this->htmlOutputEnabled) {
      $html_output = 'Current URL: ' . $this->getSession()->getCurrentUrl();
      if ($debug_text) {
        $html_output .= '<hr />' . $debug_text;
      }
      $html_output .= '<hr />' . $this->getSession()->getPage()->getContent();
      $html_output .= $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
  }

  /**
   * Asserts whether autosave is currently running.
   *
   * @param bool $running
   *   The expected autosave running state.
   */
  protected function assertAutosaveIsRunning($running) {
    $script = <<<EndOfScript
(function () {
  if (typeof Drupal.autosaveForm !== 'undefined') {
    return Drupal.autosaveForm.autosaveFormRunning;
  }
  else {
    return FALSE;
  }
})();
EndOfScript;

    $is_running = $this->getSession()->evaluateScript($script);
    $this->assertEquals($running, $is_running);
  }

  /**
   * Returns the user permissions.
   *
   * @return array
   */
  protected abstract function getUserPermissions();

}
