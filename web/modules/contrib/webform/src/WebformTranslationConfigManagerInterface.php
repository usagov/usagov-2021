<?php

namespace Drupal\webform;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for webform config translation classes.
 */
interface WebformTranslationConfigManagerInterface {

  /**
   * Alter config translation form.
   *
   * @param $form
   *   Nested array of form elements that comprise the form.
   * @param $form_state
   *   The current state of the form.
   */
  public function alterForm(&$form, FormStateInterface $form_state);

  /**
   * Validate the webform config translation form.
   *
   * @param $form
   *   Nested array of form elements that comprise the form.
   * @param $form_state
   *   The current state of the form.
   */
  public static function validateWebformForm(&$form, FormStateInterface $form_state);

}
