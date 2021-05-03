<?php

namespace Drupal\Tests\paragraphs\Functional\Experimental;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\paragraphs\Functional\Classic\ParagraphsTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Base class for tests.
 */
abstract class ParagraphsExperimentalTestBase extends ParagraphsTestBase {

  use ParagraphsTestBaseTrait;

  /**
   * Sets the Paragraphs widget add mode.
   *
   * @param string $content_type
   *   Content type name where to set the widget mode.
   * @param string $paragraphs_field
   *   Paragraphs field to change the mode.
   * @param string $mode
   *   Mode to be set. ('dropdown', 'select' or 'button').
   */
  protected function setAddMode($content_type, $paragraphs_field, $mode) {
    $form_display = EntityFormDisplay::load('node.' . $content_type . '.default')
      ->setComponent($paragraphs_field, [
        'type' => 'paragraphs',
        'settings' => ['add_mode' => $mode]
      ]);
    $form_display->save();
  }

  /**
   * Removes the default paragraph type.
   *
   * @param $content_type
   *   Content type name that contains the paragraphs field.
   */
  protected function removeDefaultParagraphType($content_type) {
    $this->drupalGet('node/add/' . $content_type);
    $this->drupalPostForm(NULL, [], 'Remove');
    $this->assertSession()->pageTextNotContains('No paragraphs added yet.');
  }

}
