<?php

namespace Drupal\stepbystep\Annotation;

use Drupal\Component\Annotation\Plugin;

// phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerCamelName -- $completed_description follows Drupal plugin naming conventions better than $completedDescription.

/**
 * Defines a Step by Step sequence annotation object.
 *
 * @see \Drupal\stepbystep\Plugin\SequenceBase
 * @see \Drupal\stepbystep\Plugin\SequenceInterface
 * @see \Drupal\stepbystep\SequenceManager
 * @see plugin_api
 *
 * @Annotation
 */
class Sequence extends Plugin {

  /**
   * The sequence ID.
   *
   * @var string
   */
  public $id;

  /**
   * The route of the sequence start page.
   *
   * @var string
   */
  public $route;

  /**
   * The name of the sequence.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The title of the sequence.
   *
   * Shown on the introduction and summary page. If empty, $name will be used.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * A description of the purpose of this sequence.
   *
   * Shown on the introduction and summary page.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The text to replace the description after the sequence is completed.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $completed_description = '';

}
