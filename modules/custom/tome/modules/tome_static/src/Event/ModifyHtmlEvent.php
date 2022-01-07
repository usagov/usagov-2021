<?php

namespace Drupal\tome_static\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Allows modules to modify the HTML of a static page before save.
 */
class ModifyHtmlEvent extends Event {

  /**
   * The page's HTML.
   *
   * @var string
   */
  protected $html;

  /**
   * An array of paths to invoke.
   *
   * @var array
   */
  protected $invokePaths = [];

  /**
   * An array of paths to exclude.
   *
   * This is useful if you're replacing paths in the HTML.
   *
   * @var array
   */
  protected $excludePaths = [];

  /**
   * The current path.
   *
   * @var string
   */
  protected $path;

  /**
   * Constructs a ModifyHtmlEvent object.
   *
   * @param string $html
   *   The page's HTML.
   * @param string $path
   *   The current path.
   */
  public function __construct($html, $path) {
    $this->html = $html;
    $this->path = $path;
  }

  /**
   * Returns the current path.
   *
   * @return string
   *   The path.
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * Adds a path to invoke.
   *
   * @param string $path
   *   The path.
   */
  public function addInvokePath($path) {
    $this->invokePaths[] = $path;
  }

  /**
   * Gets the invoke paths.
   *
   * @return array
   *   The invoke paths.
   */
  public function getInvokePaths() {
    return $this->invokePaths;
  }

  /**
   * Gets the exclude paths.
   *
   * @return array
   *   The exclude paths.
   */
  public function getExcludePaths() {
    return $this->excludePaths;
  }

  /**
   * Adds a path to exclude.
   *
   * @param string $path
   *   The path.
   */
  public function addExcludePath($path) {
    $this->excludePaths[] = $path;
  }

  /**
   * Gets the HTML for this page.
   *
   * @return string
   *   The HTML.
   */
  public function getHtml() {
    return $this->html;
  }

  /**
   * Sets the HTML for this page.
   *
   * @param string $html
   *   The HTML.
   */
  public function setHtml($html) {
    $this->html = $html;
  }

}
