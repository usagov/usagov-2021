<?php

namespace Drupal\Tests\scanner\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Helper test class with some added functions for testing.
 */
abstract class ScannerTestBase extends BrowserTestBase {

  // Contains helper methods.
  use ScannerHelperTrait;

  use ParagraphsTestBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'paragraphs', 'views', 'scanner'];

  /**
   * Create a content type and a node.
   *
   * @param string $title
   *   A title for the node that will be returned.
   * @param string $body
   *   The text to use as the body.
   * @param string $content_type
   *   The node bundle type.
   * @param string $content_type_label
   *   The content type label.
   *
   * @return \Drupal\node\NodeInterface
   *   A fully formatted node object.
   */
  protected function createContentTypeNode($title, $body, $content_type, $content_type_label) {
    $args = [
      'type' => $content_type,
      'name' => $content_type_label,
    ];
    $this->createContentType($args);

    $args = [
      'body' => [
        [
          'value' => $body,
          'format' => filter_default_format(),
        ],
      ],
      'title' => $title,
      'type' => $content_type,
    ];

    return $this->createNode($args);
  }

}
