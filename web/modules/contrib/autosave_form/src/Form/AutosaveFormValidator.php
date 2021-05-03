<?php

namespace Drupal\autosave_form\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormErrorHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormValidator;
use Drupal\Core\Form\FormValidatorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides validation of form submissions with AutosaveForm enabled.
 */
class AutosaveFormValidator extends FormValidator  {

  use AutosaveButtonClickedTrait;

  /**
   * The form validator service.
   *
   * @var \Drupal\Core\Form\FormValidatorInterface
   */
  protected $formValidator;

  /**
   * Constructs a AutosaveFormValidator object.
   *
   * @param \Drupal\Core\Form\FormValidatorInterface $form_validator
   *   The form validator service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Form\FormErrorHandlerInterface $form_error_handler
   *   The form error handler.
   */
  public function __construct(FormValidatorInterface $form_validator, RequestStack $request_stack, TranslationInterface $string_translation, CsrfTokenGenerator $csrf_token, LoggerInterface $logger, FormErrorHandlerInterface $form_error_handler) {
    $this->formValidator = $form_validator;
    parent::__construct($request_stack, $string_translation, $csrf_token, $logger, $form_error_handler);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm($form_id, &$form, FormStateInterface &$form_state) {
    $autosave_submission = $this->isAutosaveTriggered($form_state);

    if ($autosave_submission) {
      // On subsequent autosaving we don't need to execute the form validation
      // as we are not going to build the intermediate entity. However it might
      // happen that between here and the autosave submission handler the
      // autosaved state has been purged and therefore we have to check
      // explicitly for that there instead of building the intermediate entity.
      $form_state->setTemporaryValue('autosave_form_form_validation_skipped', TRUE);
    }
    else {
      // We have to execute the validation in the case of autosave submission
      // for the very first time as in this case we'll build the intermediate
      // entity for comparison and some input values are being prepared in the
      // validate functions. This is the case with e.g. autocomplete for entity
      // references.
      $this->formValidator->validateForm($form_id, $form, $form_state);
    }

    // In order for the autosave submit callback to be executed we have to
    // clear the errors caused from the validation, otherwise no submit
    // callbacks will be executed.
    if ($autosave_submission && $form_state::hasAnyErrors()) {
      $form_state->clearErrors();
    }
  }

}
