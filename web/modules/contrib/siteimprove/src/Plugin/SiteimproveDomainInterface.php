<?php

namespace Drupal\siteimprove\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for Siteimprove domain plugins.
 */
interface SiteimproveDomainInterface extends PluginInspectionInterface {

  /**
   * Form constructor.
   *
   * Build Siteimprove Domain plugin settings form.
   *
   * @param array $form
   *   Build form for setting up plugin.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param string $plugin_definition
   *   Plugin definition.
   */
  public function buildForm(array &$form, FormStateInterface &$form_state, $plugin_definition);

  /**
   * Form validation handler.
   *
   * If the plugin needs to perform a form validation, override this function.
   *
   * @param array $form
   *   Form to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state);

  /**
   * Form submission handler.
   *
   * Submit Siteimprove Domain plugin settings.
   *
   * @param array $form
   *   Form to submit.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state);

}
