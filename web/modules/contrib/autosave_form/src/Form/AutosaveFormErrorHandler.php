<?php

namespace Drupal\autosave_form\Form;

use Drupal\Core\Form\FormErrorHandler;
use Drupal\Core\Form\FormErrorHandlerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Handles form errors.
 */
class AutosaveFormErrorHandler extends FormErrorHandler {

  use AutosaveButtonClickedTrait;

  /**
   * The form error handler.
   *
   * @var \Drupal\Core\Form\FormErrorHandlerInterface
   */
  protected $formErrorHandler;

  /**
   * Constructs a AutosaveFormErrorHandler object.
   *
   * @param \Drupal\Core\Form\FormErrorHandlerInterface $form_error_handler
   *   The form error handler service.
   */
  public function __construct(FormErrorHandlerInterface $form_error_handler) {
    $this->formErrorHandler = $form_error_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function handleFormErrors(array &$form, FormStateInterface $form_state) {
    if ($this->isAutosaveTriggered($form_state)) {
      return $this;
    }
    return $this->formErrorHandler->handleFormErrors($form, $form_state);
  }

}
