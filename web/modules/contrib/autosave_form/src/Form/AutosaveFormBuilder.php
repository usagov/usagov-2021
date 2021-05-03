<?php

namespace Drupal\autosave_form\Form;

use Drupal\autosave_form\EmptyAlter\Extension\ModuleHandlerEmptyAlter;
use Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface;
use Drupal\autosave_form\EmptyAlter\Theme\ThemeManagerEmptyAlter;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormCacheInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\Form\FormValidatorInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides form building and processing with AutosaveForm enabled.
 *
 * @ingroup form_api
 */
class AutosaveFormBuilder extends FormBuilder {

  use AutosaveButtonClickedTrait;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The autosave form storage.
   *
   * @var \Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface
   */
  protected $autosaveEntityFormStorage;

  /**
   * Controls whether to execute ::doBuildForm or not.
   *
   * If set to FALSE the normal form processing will run, otherwise if set to
   * TRUE doBuildForm will not be executed. This is useful in the use case
   * where we don't need the processed form like in e.g. autosave submit after
   * having already at least one autosave state, from which point we don't need
   * the form state values, but only the user input and the last cached form
   * state.
   *
   * @var bool
   */
  protected $doBuildFormSkip = FALSE;

  /**
   * Constructs a new FormBuilder.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Form\FormValidatorInterface $form_validator
   *   The form validator.
   * @param \Drupal\Core\Form\FormSubmitterInterface $form_submitter
   *   The form submission processor.
   * @param \Drupal\Core\Form\FormCacheInterface $form_cache
   *   The form cache.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface $autosave_entity_form_storage
   *   The autosave form storage service.
   */
  public function __construct(FormBuilderInterface $form_builder, FormValidatorInterface $form_validator, FormSubmitterInterface $form_submitter, FormCacheInterface $form_cache, ModuleHandlerInterface $module_handler, EventDispatcherInterface $event_dispatcher, RequestStack $request_stack, ClassResolverInterface $class_resolver, ElementInfoManagerInterface $element_info, ThemeManagerInterface $theme_manager, CsrfTokenGenerator $csrf_token = NULL, AutosaveEntityFormStorageInterface $autosave_entity_form_storage) {
    parent::__construct($form_validator, $form_submitter, $form_cache, $module_handler, $event_dispatcher, $request_stack, $class_resolver, $element_info, $theme_manager, $csrf_token);
    $this->formBuilder = $form_builder;
    $this->autosaveEntityFormStorage = $autosave_entity_form_storage;
  }

  public function buildForm($form_id, FormStateInterface &$form_state) {
    $form = parent::buildForm($form_id, $form_state);
    if ($form_state::hasAnyErrors()) {
      // Under circumstances it might happen that the form is submitted but
      // returned with validation errors and the form alter hooks are executed
      // thus leading to the autosave form alter code being executed as well and
      // putting the autosave resume/discard message to the form, which should
      // not happen if the form is being returned to the browser with validation
      // errors. In order to prevent this we have to add the resume/discard
      // message and options only on HTTP GET requests or on POST requests if
      // restore or reject submit operations have been performed or in a more
      // complex case if the message has not been yet confirmed but other
      // AJAX / POST requests are being triggered in the background. As we could
      // not detect the last case we still put the form elements into the form,
      // but on the client side we will not show the message if the form is
      // returned with validation errors.
      $form['#attached']['drupalSettings']['autosaveForm']['formHasErrors'] = TRUE;

      // Additionally unset the form elements and settings which might have been
      // added, but aren't actually needed.
      unset($form['#attached']['drupalSettings']['autosaveForm']['message']);
      unset($form[AutosaveFormInterface::AUTOSAVE_RESTORE_ELEMENT_NAME]);
      unset($form[AutosaveFormInterface::AUTOSAVE_REJECT_ELEMENT_NAME]);
      unset($form['autosave_restore_discard']);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processForm($form_id, &$form, FormStateInterface &$form_state) {
    if ($this->isAutosaveTriggered($form_state)) {
      // @todo should we add a condition, that the form state is already cached
      // in order to stop fully processing the form?
      $this->doBuildFormSkip = TRUE;

      // As we'll skip doBuildForm we have to take care of setting the
      // triggering element.
      $form_state->setTriggeringElement($form[AutosaveFormInterface::AUTOSAVE_ELEMENT_NAME]);

      // Needed to execute the submit handler, as this will not be done if
      // duBuildForm is not being executed.
      $form_state->setSubmitHandlers($form[AutosaveFormInterface::AUTOSAVE_ELEMENT_NAME]['#submit']);
      $form_state->setProcessInput();
      $form_state->setSubmitted();
    }

    $response = parent::processForm($form_id, $form, $form_state);
    $this->doBuildFormSkip = FALSE;

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function doBuildForm($form_id, &$element, FormStateInterface &$form_state) {
    return $this->doBuildFormSkip ? $element : parent::doBuildForm($form_id, $element, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildForm($form_id, FormStateInterface &$form_state, $old_form = NULL) {
    $this->restoreAutosavedState($form_id, $form_state);
    return parent::rebuildForm($form_id, $form_state, $old_form);
  }

  /**
   * Restores an autosaved form state.
   *
   * @param $form_id
   *   The form id.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function restoreAutosavedState($form_id, FormStateInterface $form_state) {
    if (!$form_state->get('autosave_form_restored') && ($autosaved_timestamp = $form_state->get('autosave_form_state_timestamp'))) {
      $form_object = $form_state->getFormObject();

      // Restore entity form.
      if ($form_object instanceof EntityFormInterface) {
        $entity = $form_object->getEntity();
        $autosaved_state = $this->autosaveEntityFormStorage->getEntityAndFormState($form_id, $entity->getEntityTypeId(), $entity->id(), $entity->language()->getId(), $this->currentUser()->id(), NULL, $autosaved_timestamp);

        if (is_null($autosaved_state)) {
          // @todo Cover the case that the autosaved state has been purged
          // meanwhile.
          return;
        }

        /** @var EntityInterface $autosaved_entity */
        $autosaved_entity = $autosaved_state['entity'];
        /** @var FormStateInterface $autosaved_form_state */
        $autosaved_form_state = $autosaved_state['form_state'];

        // Restore the form with the entity from the autosaved state.
        $form_object->setEntity($autosaved_entity);
        // Restore the user input.
        $current_user_input = $form_state->getUserInput();
        $autosaved_user_input = $autosaved_form_state->getUserInput();
        // We have to rebuild the form and keep the generated form token
        // instead of putting the one from the autosaved input, otherwise the
        // form builder will set an form state error and, which is going to
        // result into an exception, as setting form state errors after the
        // validation phase is forbidden.
        if (isset($current_user_input['form_token'])) {
          $autosaved_user_input['form_token'] = $current_user_input['form_token'];
        }
        $form_state->setUserInput($autosaved_user_input);
        // Recover the form state storage, which is needed to continue from the
        // state at which the form was left.
        $form_state->setStorage($autosaved_form_state->getStorage());

        // Flag the form state as being restored from autosave.
        $form_state->set('autosave_form_restored', TRUE);
      }
      elseif ($form_object instanceof FormInterface) {
        // @todo add support for regular forms.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareForm($form_id, &$form, FormStateInterface &$form_state) {
    $prevent_hooks = FALSE;

    if ($this->isAutosaveTriggered($form_state)) {
      // There is no need of generating a new form build id after triggering
      // autosave.
      $form['#build_id'] = $form_state->getUserInput()['form_build_id'];

      if ($form_state->isCached()) {
        $prevent_hooks = TRUE;
      }
    }

    if ($prevent_hooks) {
      // Prevent running hooks.
      $module_handler = $this->moduleHandler;
      $theme_manager = $this->themeManager;
      $this->moduleHandler = new ModuleHandlerEmptyAlter();
      $this->themeManager = new ThemeManagerEmptyAlter();
    }

    parent::prepareForm($form_id, $form, $form_state);

    if ($prevent_hooks) {
      $this->moduleHandler = $module_handler;
      $this->themeManager = $theme_manager;
    }
  }

}
