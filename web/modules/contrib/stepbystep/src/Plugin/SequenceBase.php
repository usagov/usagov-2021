<?php

namespace Drupal\stepbystep\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormElementHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\views\Form\ViewsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Base class for Step by Step sequence plugins.
 *
 * Modules may create discoverable Step by Step sequence plugins by extending
 * this class and adding a Sequence annotation.
 *
 * When extending this class, only overriding getSteps() is required.
 * There are many other methods that can optionally be overridden for further
 * customization.
 *
 * @see \Drupal\stepbystep\Annotation\Sequence
 */
abstract class SequenceBase extends PluginBase implements SequenceInterface, ContainerFactoryPluginInterface {

  /**
   * The ID of the currently active step in the sequence.
   *
   * @var string
   */
  protected $currentStep;

  /**
   * The progress of each status in the sequence.
   *
   * @var array
   *
   * @see SequenceInterface::getProgress()
   */
  protected $progress;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Creates a sequence instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, RedirectDestinationInterface $redirect_destination, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->redirectDestination = $redirect_destination;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('redirect.destination'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRoute() {
    return $this->pluginDefinition['route'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->pluginDefinition['title'] ?? $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    // If the sequence is completed and a completed_description has been
    // defined, return that. Otherwise, return the standard description.
    return $this->hasStepsRemaining() || !isset($this->pluginDefinition['completed_description'])
      ? $this->pluginDefinition['description']
      : $this->pluginDefinition['completed_description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAdvancedLinks() {

  }

  /**
   * {@inheritdoc}
   */
  public function getStep($step_id = NULL) {
    // An empty $step_id means the current step.
    if (empty($step_id)) {
      $step_id = $this->currentStep;
    }
    $step = $this->getSteps()[$step_id] ?? FALSE;
    // If the step was found, add an additional element containing the step ID,
    // allowing callers to easily retrieve the current step and its definition
    // at the same time.
    if ($step) {
      $step += ['id' => $step_id];
    }
    return $step;
  }

  /**
   * {@inheritdoc}
   */
  public function setStep($step_id) {
    if ($this->hasStep($step_id)) {
      $this->currentStep = $step_id;
    }
    else {
      throw new \InvalidArgumentException("Step '$step_id' does not exist.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasStep($step_id) {
    return isset($this->getSteps()[$step_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl($step_id = NULL, $redirect = FALSE) {
    // If no step is given, return the URL of the introduction form.
    if (empty($step_id)) {
      return Url::fromRoute($this->pluginDefinition['route']);
    }
    // If a redirect URL is requested, use the route of the introduction form
    // and add the step as a route parameter. The SequenceController will
    // perform the redirect. See the comment on this function for why a
    // redirect URL might be wanted.
    if ($redirect) {
      return $this->getUrl()->setRouteParameter('step', $step_id);
    }
    $step = $this->getStep($step_id);
    if (empty($step)) {
      throw new NotFoundHttpException();
    }

    // Get the information about the page that should be used for this step,
    // and construct the URL from it.
    $route_name = $step['route'];
    $route_parameters = $step['route_parameters'] ?? [];
    // Add the query parameters to trigger Step by Step handling on the page.
    $options = [
      'query' => [
        self::SEQUENCE => $this->getPluginId(),
        self::STEP => $step_id,
      ],
    ];
    return Url::fromRoute($route_name, $route_parameters, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getNextUrl() {
    return $this->getUrl($this->getNextStep());
  }

  /**
   * {@inheritdoc}
   */
  public function getNextStep() {
    $steps = $this->getSteps();
    $sequence_progress = $this->getProgress();
    // Set flag to wait until criteria are met before accepting the next step in
    // the sequence.
    $use_next_available = FALSE;
    // Set up a variable to hold the first available step found in the sequence.
    $first_available_step = NULL;
    // Accept steps from the start of the list onwards if no $current_step was
    // set, or if that step is not found in the sequence.
    $current_step = $this->currentStep;
    if (empty($current_step) || !isset($steps[$current_step])) {
      $use_next_available = TRUE;
    }
    foreach ($steps as $step_id => $step) {
      if (empty($sequence_progress[$step_id])) {
        // No progress has been recorded for the step. Analyze the step using
        // any defined rules for conditional completion or skipping.
        $status = $this->analyzeStep($step_id);
        if (empty($status)) {
          // If the step still needs to be done, and we are accepting steps,
          // return it now.
          if ($use_next_available) {
            return $step_id;
          }
          // If we are not accepting steps yet, save it as the first available
          // step so it can be returned later if no steps after the current step
          // are found that need to be done.
          if (empty($first_available_step)) {
            $first_available_step = $step_id;
          }
        }
      }
      // If the specified current step has been found in the sequence, accept
      // applicable steps from the next step onwards in the sequence.
      if ($step_id == $current_step) {
        $use_next_available = TRUE;
      }
    }
    if (empty($first_available_step)) {
      // We have reached the end of the sequence and no steps were found that
      // need to be done. Add a message to notify the user that the sequence is
      // complete before returning.
      $skipped_exists = in_array(static::SKIPPED, $this->getProgress());
      $status = $skipped_exists
        ? $this->t('You have finished %name (but you might wish to go back and do any steps that you skipped).', ['%name' => $this->getName()])
        : $this->t('You have finished %name.', ['%name' => $this->getName()]);
      $this->messenger()->addStatus($status);
    }
    return $first_available_step;
  }

  /**
   * {@inheritdoc}
   */
  public function userAccess($step_id) {
    return $this->getUrl($step_id)->access($this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function isStarted() {
    return !empty(array_filter($this->getProgress()));
  }

  /**
   * {@inheritdoc}
   */
  public function getFinishedStepsCount() {
    return count(array_filter($this->getProgress()));
  }

  /**
   * {@inheritdoc}
   */
  public function hasStepsRemaining() {
    return count($this->getSteps()) - $this->getFinishedStepsCount() > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getProgress() {
    // If a cached copy of the sequence progress is available, return that.
    // Otherwise, read it from config.
    if (!isset($this->progress)) {
      // The configuration name for each sequence contains its plugin ID.
      $this->progress = $this->configFactory
        ->get('stepbystep.progress.' . $this->getPluginId())
        ->get('progress') ?? [];
    }
    return $this->progress;
  }

  /**
   * Saves the progress status of the entire sequence to config.
   *
   * This function is protected because no validation is performed here.
   * External classes should use markStep() or resetProgress() to modify the
   * progress of this sequence.
   *
   * @param array $progress
   *   The sequence progress in the format returned by getProgress().
   */
  protected function saveProgress(array $progress) {
    // Update the cached copy of the progress.
    $this->progress = $progress;
    // Save the progress to config.
    $this->configFactory
      ->getEditable('stepbystep.progress.' . $this->getPluginId())
      ->set('progress', $progress)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function resetProgress($status_to_reset = NULL) {
    if (empty($status_to_reset)) {
      // Delete progress for all steps by saving an empty array.
      $this->saveProgress([]);
    }
    else {
      $progress = $this->getProgress();
      // Remove all steps with the given status, and save whatever remains.
      $filtered_progress = array_filter(
        $progress,
        function ($status) use ($status_to_reset) {
          return $status != $status_to_reset;
        }
      );
      $this->saveProgress($filtered_progress);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function markStep($step_id, $status) {
    $progress = $this->getProgress();
    $sequence_steps = $this->getSteps();
    $steps = (array) $step_id;
    foreach ($steps as $step_id) {
      // Do nothing if $step_id is not defined in this sequence.
      if (!array_key_exists($step_id, $sequence_steps)) {
        continue;
      }

      // Manually handle each status, to prevent spurious other statuses being
      // set.
      switch ($status) {
        case self::COMPLETED:
        case self::SKIPPED:
        case self::NOT_APPLICABLE:
          $progress[$step_id] = $status;
          break;

        case self::RESET:
          unset($progress[$step_id]);
          break;
      }
    }
    $this->saveProgress($progress);
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, $form_id) {
    $step = $this->getStep();
    if ($step === FALSE) {
      throw new NotFoundHttpException('Step "' . $this->currentStep . '" does not exist.');
    }

    // If Step by Step is active but the current form should not be displayed,
    // hide it and perform no further alterations.
    if (isset($step['exclude_form_id']) && in_array($form_id, (array) $step['exclude_form_id'])) {
      $this->hideForm($form, $form_id);
      return;
    }

    // If Step by Step is active but the current form is not supposed to be
    // altered, return immediately.
    if (!in_array($form_id, (array) $step['form_id'])) {
      return;
    }

    // Mark the step as 'n/a' and redirect to the next step if any of the
    // skip_if_not_present elements are missing.
    if ($this->requiredElementsMissing($form, $form_id)) {
      $this->notifyStepNotApplicable($this->currentStep);
      $this->redirect($this->getNextUrl());
      return;
    }

    // By this point, the form is going to be displayed.
    // Perform various alterations on the form to apply the options configured
    // for this step and turn it into a step in the sequence.
    $this->alterActions($form, $form_state, $form_id);
    $this->hideElements($form, $form_state, $form_id);
    $this->addProgressBar($form, $form_id);
    $this->addInstructions($form, $form_id);

    // Set the form/page title.
    if (isset($step['title']) && !$this->flagApplies('do_not_rename_title', $form_id)) {
      $form['#title'] = $step['title'];
      // Store the title in a custom key as well, so it can be used by
      // stepbystep_preprocess_views_view__page() for Views.
      $form['#stepbystep_title_override'] = $step['title'];
    }

    // Add a class to the form so CSS rules can target specific step IDs.
    $form['#attributes']['class'][] = 'stepbystep_' . $step['id'];
    $form['#attached']['library'][] = 'stepbystep/stepbystep.css';

    // Apply overrides last, allowing them to override any alterations performed
    // above.
    $this->applyOverrides($form, $form_id);

    $this->finishAlter($form, $form_state, $form_id, $step);
  }

  /**
   * {@inheritdoc}
   */
  public function finishAlter(array &$form, FormStateInterface $form_state, $form_id, array $current_step) {
    // Provides a convenient place for sequences to perform additional form
    // alterations before rendering.
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // This function is called after existing validation functions have run.
    // Since Step by Step form elements can be hidden, we need to prevent hidden
    // elements from causing validation errors since the user will have no way
    // to correct the error.
    $errors = $form_state->getErrors();
    foreach ($errors as $name => $error) {
      // Examine each validation error and locate the originating form element.
      $element = FormElementHelper::getElementByName($name, $form);
      // If the element was hidden, delete the validation error it produced.
      if (isset($element['#access']) && !$element['#access']) {
        unset($errors[$name]);
      }
    }
    // Clear all validation errors, and add back in only the remaining errors
    // whose originating elements are accessible.
    $form_state->clearErrors();
    foreach ($errors as $name => $error) {
      $form_state->setErrorByName($name, $error);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The step is done, so mark it complete.
    $this->markStep($this->currentStep, self::COMPLETED);
    // Alter status messages if desired.
    $this->alterMessages();
    // Reset the status of dependent steps so they will be re-done.
    $this->resetDependentSteps();
    // Ensure form redirection is enabled so the user will be redirected to the
    // next step, even if the form set its own disableRedirect to TRUE.
    $form_state->disableRedirect(FALSE);
    // Redirect to the next step.
    $form_state->setRedirectUrl($this->getNextUrl());
  }

  /**
   * Submit handler for the Skip button.
   *
   * Marks the current step as Skipped and redirects the user to the next step.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitSkip(array &$form, FormStateInterface $form_state) {
    $this->markStep($this->currentStep, self::SKIPPED);
    $form_state->setRedirectUrl($this->getNextUrl());
  }

  /**
   * {@inheritdoc}
   */
  public function alterLocalTasks(array &$data, $route_name, RefinableCacheableDependencyInterface &$cacheability) {
    // Hide the local tasks (tabs) according to the step setting.
    if (isset($this->getStep()['suppress_menus'])) {
      $suppress_menus = $this->getStep()['suppress_menus'];
      // Hide the secondary tabs before the primary tabs to ensure the values
      // in $data['tabs'] don't shift around as they are unset.
      if (in_array('secondary', $suppress_menus)) {
        unset($data['tabs'][1]);
      }
      if (in_array('primary', $suppress_menus)) {
        unset($data['tabs'][0]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterIntroductionForm(array &$form, FormStateInterface $form_state) {
    // Provides a convenient place for sequences to alter their introduction
    // forms after they are built.
  }

  /**
   * {@inheritdoc}
   */
  public function submitIntroductionForm(array &$form, FormStateInterface $form_state) {
    // Provides a convenient place for sequences to add a submission handler
    // to their introduction forms.
  }

  /**
   * Analyzes a step and updates its status according to its conditional rules.
   *
   * @param string $step_id
   *   ID of the step to analyze.
   *
   * @return string|null
   *   A constant containing the status of the step, or NULL if the step still
   *   needs to be done.
   */
  protected function analyzeStep($step_id) {
    $step = $this->getStep($step_id);

    if (!empty($step['not_applicable_if'])) {
      // Read the boolean or execute the callback to determine whether the step
      // is not applicable.
      $not_applicable = $step['not_applicable_if'] === TRUE || $this->doCallback($step['not_applicable_if']);
      if ($not_applicable) {
        return $this->notifyStepNotApplicable($step_id);
      }
    }

    if (!empty($step['completed_if'])) {
      // Read the boolean or execute the callback to determine whether the step
      // is completed.
      $completed = $step['completed_if'] === TRUE || $this->doCallback($step['completed_if']);
      if ($completed) {
        return $this->notifyStepCompleted($step_id);
      }
    }

    // Mark the step as skipped if the user does not have permision to access
    // it.
    if (!$this->userAccess($step_id)) {
      return $this->markStep($step_id, self::SKIPPED);
    }
  }

  /**
   * Marks a step as already having been completed and notifies the user.
   *
   * @param string $step_id
   *   The ID of the step to mark complete.
   *
   * @return string
   *   The 'completed' status constant.
   */
  protected function notifyStepCompleted($step_id) {
    $this->messenger()->addStatus($this->t(
      'The step "@step_name" has already been completed.', [
        '@step_name' => $this->getStep($step_id)['title'],
      ]
    ));
    return $this->markStep($step_id, self::COMPLETED);
  }

  /**
   * Marks a step as not applicable and notifies the user.
   *
   * @param string $step_id
   *   The ID of the step to mark not applicable.
   *
   * @return string
   *   The 'not applicable' status constant.
   */
  protected function notifyStepNotApplicable($step_id) {
    $this->messenger()->addStatus($this->t(
      'The step "@step_name" was not applicable or not necessary at this time.', [
        '@step_name' => $this->getStep($step_id)['title'],
      ]
    ));
    return $this->markStep($step_id, self::NOT_APPLICABLE);
  }

  /**
   * Alters the message displayed after a form is submitted.
   */
  protected function alterMessages() {
    $step = $this->getStep();
    // If the step provides a submit_message, clear out the existing submit
    // message and add the message provided by the step.
    if (array_key_exists('submit_message', $step)) {
      $this->messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
      if (!empty($step['submit_message'])) {
        $this->messenger()->addStatus($step['submit_message']);
      }
    }
  }

  /**
   * Resets the status of steps dependent on the current step.
   *
   * Steps dependent on the current step need to be re-done, since their
   * contents or available options may have changed.
   */
  protected function resetDependentSteps() {
    $step = $this->getStep();
    if (!empty($step['upon_recompletion_reset_steps'])) {
      $this->markStep($step['upon_recompletion_reset_steps'], self::RESET);
    }
  }

  /**
   * Adds a progress bar above the first form on a page.
   *
   * @param array $form
   *   The form structure.
   * @param string $form_id
   *   The ID of the form being displayed.
   */
  protected function addProgressBar(array &$form, $form_id) {
    // Do nothing if the no_progress flag is set.
    if ($this->flagApplies('no_progress', $form_id)) {
      return;
    }

    $steps_finished = $this->getFinishedStepsCount();
    $total_steps = count($this->getSteps());
    if ($this->hasStepsRemaining()) {
      $message = $this->t('%sequence: Step @step of @total', [
        '%sequence' => $this->getTitle(),
        '@step' => $steps_finished + 1,
        '@total' => $total_steps,
      ]);
    }
    else {
      $message = $this->t('%sequence', ['%sequence' => $this->getTitle()]);
    }
    // Add a div so stepbystep.css can target this specific progress bar.
    // Adding the class directly to the progress bar does not work.
    $form['stepbystep_progress'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'stepbystep_progress'],
      '#weight' => -101,
    ];
    $form['stepbystep_progress']['progress'] = [
      '#theme' => 'progress_bar',
      '#percent' => ($steps_finished / $total_steps) * 100,
      '#message' => $message,
    ];
  }

  /**
   * Adds instructions above the form for the current step.
   *
   * @param array $form
   *   The form structure.
   * @param string $form_id
   *   The ID of the form being displayed.
   */
  protected function addInstructions(array &$form, $form_id) {
    $instructions = $this->valuesForFormId('instructions', $form_id);
    // If the instructions were declared per-form-id, transform the return value
    // to a string.
    if (is_array($instructions)) {
      $instructions = $instructions[$form_id] ?? '';
    }
    if (!empty($instructions)) {
      $form['stepbystep_instructions'] = [
        '#type' => 'container',
        '#markup' => '<p>' . $instructions . '</p>',
        '#weight' => -100,
      ];
    }
  }

  /**
   * Alters the actions (submit buttons) on a form according to step settings.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The ID of the form being displayed.
   */
  protected function alterActions(array &$form, FormStateInterface $form_state, $form_id) {
    // Do nothing if the no_wizard_buttons flag is set.
    if ($this->flagApplies('no_wizard_buttons', $form_id)) {
      return;
    }

    // Hide the extra "Apply to selected items" button at the bottom of views.
    // It's confusing to have an Apply button at the bottom alongside the wizard
    // buttons.
    if ($form_state->getFormObject() instanceof ViewsForm) {
      // Remove the button instead of simply hiding it so a Continue button will
      // be generated that does not submit the views bulk form when clicked.
      unset($form['actions']);
    }

    // Store a reference to the "parent" element that holds the buttons.
    // This is $form['actions'] on well-behaved forms, but the buttons might be
    // direct children of $form in some cases.
    if (isset($form['actions'])) {
      $actions = &$form['actions'];
    }
    else {
      $actions = &$form;
    }

    // Check if the step declared which submit buttons should mark this step as
    // complete and move on to the next step when clicked.
    $declared_submit_button_paths = $this->valuesForFormId('submit_buttons', $form_id);
    $declared_submit_buttons = [];
    if (!empty($declared_submit_button_paths)) {
      // Gather references to each declared submit button form element.
      foreach ($declared_submit_button_paths as $submit_path) {
        $submit_parents_array = explode('][', $submit_path);
        // Get the declared submit element from the form array.
        $declared_submit_buttons[] = &NestedArray::getValue($form, $submit_parents_array);
      }

      // If a single submit button was declared, remove the it from its original
      // location in the form and move it to the top level so it will appear in
      // its proper place below all other form elements.
      if (count($declared_submit_buttons) === 1) {
        $primary_submit = reset($declared_submit_buttons);
        $primary_submit['#button_type'] = 'primary';
        // Remove the submit button from its original location in the form.
        NestedArray::unsetValue($form, $submit_parents_array);
        // Also remove the default submit button, if any, so it does not appear
        // alongside the declared one.
        unset($actions['submit']);
        // Move it to the top level of the form.
        $form['submit'] = &$primary_submit;
      }
      // If multiple submit buttons are declared, do not move them. Just add the
      // stepbystep validate and submit handlers.
      else {
        foreach ($declared_submit_buttons as &$declared_submit_button) {
          $this->addHandlersToSubmitElement($declared_submit_button, $form, FALSE);
        }
      }
    }
    else {
      // Store a reference to the form-provided Submit button.
      $primary_submit = &$actions['submit'];
    }

    // The form provides a Submit button.
    if (!empty($primary_submit)) {
      // It only makes sense to use wait_until_done if the form has a single
      // submit button. It is ambiguous what clicking "Save and continue"
      // would do if there were multiple submit buttons available.
      if ($this->flagApplies('wait_until_done', $form_id)) {
        // This flag indicates that an additional "Save and continue" button
        // should be added instead of replacing the original submit button. Make
        // a copy of the original button (that has not been renamed yet), name
        // it stepbystep_submit_wait, and add the stepbystep handlers.
        $actions['stepbystep_submit_wait'] = $primary_submit;
        // Stepbystep validate handlers are added to this element, in order to
        // suppress validation errors for form elements that are hidden that the
        // user has no way of correcting.
        // Stepbystep submit handlers are not added to this element, so it will
        // not move on to the next step or mark the current step is complete.
        $this->addHandlersToSubmitElement($actions['stepbystep_submit_wait'], $form, TRUE);
      }

      // Modify the primary submit button.
      $this->addHandlersToSubmitElement($primary_submit, $form, FALSE);
      $primary_submit['#weight'] = 90;
      $primary_submit['#attributes']['class'][] = 'stepbystep-submit-button';
      if (!$this->flagApplies('do_not_rename_submit', $form_id)) {
        $primary_submit['#value'] = $this->t('Save and continue');
      }
    }
    elseif (empty($declared_submit_buttons)) {
      // The form does not provide a Submit button. Create one.
      // Assume that the form auto-submits itself and indicate that
      // the 'done' button will therefore only proceed (lest we imply that not
      // clicking it will leave changes un-saved).
      $actions['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Continue'),
        '#submit' => [[$this, 'submitForm']],
        // The form-level submit handler will not run when clicking this button,
        // so suppress the form-level validation as well.
        '#limit_validation_errors' => [],
        '#button_type' => 'primary',
        '#weight' => 90,
        '#attributes' => ['class' => ['stepbystep-submit-button']],
      ];
    }
    if (!$this->flagApplies('no_skip_button', $form_id)) {
      // Create the Skip button.
      $actions['stepbystep_submit_skip'] = [
        '#type' => 'submit',
        '#value' => $this->t('Skip this step'),
        '#submit' => [[$this, 'submitSkip']],
        // No validation should be run when skipping the step.
        '#limit_validation_errors' => [],
        '#weight' => 91,
        '#attributes' => ['class' => ['stepbystep-skip-button']],
      ];
    }
  }

  /**
   * Adds stepbystep validate and submit handlers to a form submit element.
   *
   * @param array $element
   *   The submit form element.
   * @param array $form
   *   The form structure.
   * @param bool $validate_only
   *   If TRUE, only validate handlers are added. If FALSE, both validate and
   *   submit handlers are added.
   */
  protected function addHandlersToSubmitElement(array &$element, array &$form, bool $validate_only = FALSE) {
    // Collect validation handlers from the submit button, or from the form.
    $validate_handlers = $element['#validate'] ?? $form['#validate'] ?? [];
    // Add $this->validateForm() as an additional validation handler.
    $validate_handlers[] = [$this, 'validateForm'];
    // Set all validation handlers at the submit button level to make sure
    // they run.
    $element['#validate'] = $validate_handlers;

    if (!$validate_only) {
      // Collect submit handlers from the submit button, or from the form.
      $submit_handlers = $element['#submit'] ?? $form['#submit'] ?? [];
      // Add $this->submitForm() as an additional submit handler.
      $submit_handlers[] = [$this, 'submitForm'];
      // Set all submit handlers at the submit button level to make sure they
      // run.
      $element['#submit'] = $submit_handlers;
    }
  }

  /**
   * Hides a form if it should not be displayed on the current step.
   *
   * @param array $form
   *   The form structure.
   * @param string $form_id
   *   The ID of the form being displayed.
   */
  protected function hideForm(array &$form, $form_id) {
    // Setting the #access of Views exposed filter forms to FALSE can actually
    // change the behaviour of views. For example, the People view normally
    // shows all users, but restricting the #access of its exposed filter form
    // causes it to only show enabled users. So, use CSS to hide views filter
    // forms and #access for all others.
    if ($form_id == 'views_exposed_form') {
      $form['#attributes']['class'][] = 'hidden';
      // Also hide any elements that were added as wrappers to the exposed
      // filter form.
      if (!empty($form['#theme_wrappers'])) {
        foreach ($form['#theme_wrappers'] as &$wrapper) {
          if (is_array($wrapper)) {
            $wrapper['#attributes']['class'][] = 'hidden';
          }
        }
      }
    }
    else {
      $form['#access'] = FALSE;
    }
  }

  /**
   * Hides form elements according to step settings.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The ID of the form being displayed.
   */
  protected function hideElements(array &$form, FormStateInterface $form_state, $form_id) {
    // Hide excluded form elements.
    $exclude_form_elements = $this->valuesForFormId('exclude_form_elements', $form_id);
    if (!empty($exclude_form_elements)) {
      foreach ($exclude_form_elements as $element) {
        // Construct the $parents array expected by NestedArray::setValue().
        // For example, turn 'actions][submit' into ['actions', 'submit'].
        $element_array = explode('][', $element);
        NestedArray::setValue($form, array_merge($element_array, ['#access']), FALSE);
      }
    }

    // Hide all form elements except those listed in form_elements.
    $form_elements = $this->valuesForFormId('form_elements', $form_id);
    if (!empty($form_elements)) {
      // Prevent additional required form elements from being hidden.
      array_push($form_elements, 'actions', 'submit', 'stepbystep_submit_skip', 'stepbystep_submit_wait', 'form_build_id', 'form_token', 'form_id');
      foreach ($form_elements as $element) {
        // Construct the $parents array expected by NestedArray::setValue().
        // For example, turn 'actions][submit' into ['actions', 'submit'].
        $element_array = explode('][', $element);
        // Add a special tag to elements that should remain visible. All
        // elements lacking the tag will be hidden later.
        NestedArray::setValue($form, array_merge($element_array, ['#stepbystep']), 'show');
      }
      // Hide the un-tagged elements.
      static::hideUnselectedElements($form);
    }
  }

  /**
   * Recursively hides all form elements lacking the #stepbystep tag.
   *
   * @param array $elements
   *   The form elements to process.
   *
   * @return bool
   *   TRUE if the array tree contains any visible elements.
   *   FALSE if all elements in the tree are hidden.
   */
  protected static function hideUnselectedElements(array &$elements) {
    // Flag to indicate if we have located any visible elements.
    $access = FALSE;
    foreach (Element::children($elements) as $key) {
      // If this element lacks the visible tag, and none of its descendants
      // have the tag, hide the element.
      if (empty($elements[$key]['#stepbystep'])
          && !self::hideUnselectedElements($elements[$key])) {
        $elements[$key]['#access'] = FALSE;
      }
      else {
        // Otherwise, do not hide the element. (But if the #access is already
        // FALSE for some other reason, don't override it.) Set the $access flag
        // to indicate that a potentially visible element was found.
        $access = TRUE;
      }
    }
    return $access;
  }

  /**
   * Returns whether the form is missing any of a set of elements.
   *
   * @param array $form
   *   The form structure.
   * @param string $form_id
   *   The ID of the form being displayed.
   *
   * @return bool
   *   TRUE if any required elements are missing.
   *   FALSE if all required elements are present.
   */
  protected function requiredElementsMissing(array $form, $form_id) {
    $step = $this->getStep();
    if (isset($step['skip_if_not_present'])) {
      $skip_if_not_present = $step['skip_if_not_present'];
      // Special handling is required because skip_if_not_present may be
      // declared as multidimensional array, even if not declared per-form-id.
      // If the first key is a string, then the required elements are declared
      // per-form-id and need to be extracted. Otherwise, the elements are not
      // declared per-form-id and can be used as-is.
      if (is_string(array_keys($skip_if_not_present)[0])) {
        $skip_if_not_present = $this->valuesForFormId('skip_if_not_present', $form_id);
      }
      foreach ($skip_if_not_present as $required_element) {
        if (is_array($required_element)) {
          // If the element is an array, it is treated as a group of elements
          // of which at least one must be present. Only return TRUE if they are
          // all missing.
          if ($this->allElementsMissing($form, $required_element)) {
            return TRUE;
          }
        }
        else {
          // If the element is not an array, then it is required to be present.
          // Return TRUE if it is missing.
          $required_element_array = explode('][', $required_element);
          if (!NestedArray::keyExists($form, $required_element_array)) {
            return TRUE;
          }
        }
      }
    }
    // No missing elements were found.
    return FALSE;
  }

  /**
   * Returns whether all elements in a set are missing from the form.
   *
   * @param array $form
   *   The form structure.
   * @param string[] $required_elements
   *   The element names or paths to search for.
   *
   * @return bool
   *   TRUE if all of the required elements are missing.
   *   FALSE if any of the required elements is found.
   */
  protected function allElementsMissing(array $form, array $required_elements) {
    foreach ($required_elements as $required_element) {
      $required_element_array = explode('][', $required_element);
      // Search for the element and return FALSE if it is found in the form.
      if (NestedArray::keyExists($form, $required_element_array)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Applies form value overrides for the current step.
   *
   * @param array $form
   *   The form structure.
   * @param string $form_id
   *   The ID of the form being displayed.
   */
  protected function applyOverrides(array &$form, $form_id) {
    $overrides = $this->valuesForFormId('overrides', $form_id);
    foreach ($overrides as $path => $override) {
      // Generate the parents array (for example, ['actions', 'submit']).
      $parents = explode('][', $path);
      // If $override is not an array, then it is meant to override a property
      // on the $form array itself. If it is an array, it is meant to override a
      // property on one of the form's children.
      if (!is_array($override)) {
        $form['#' . $path] = $override;
      }
      else {
        foreach ($override as $property => $value) {
          // For each property to change, create a full path array.
          // For example, ['actions', 'submit', '#value'].
          $property_path = array_merge($parents, ['#' . $property]);
          // Set the value.
          NestedArray::setValue($form, $property_path, $value);
        }
      }
    }
  }

  /**
   * Return the data values applying to the current specific form within a step.
   *
   * @param string $key
   *   Key identifying a data value that may be specified for a step, or for
   *   some or all of the forms that belong to a step.
   * @param string $form_id
   *   ID of the form currently being displayed.
   *
   * @return array
   *   The array of values applicable to the current form in the current step.
   *   If the data value defined for the step is a non-array, an array of
   *   strings or an empty array, then return that.  If it is an array of
   *   arrays, return the array (if any) keyed by the current form ID.
   */
  protected function valuesForFormId($key, $form_id) {
    $step = $this->getStep();
    if (empty($step[$key])) {
      return [];
    }
    else {
      $values = $step[$key];
      if (!is_array($values)) {
        return $values;
      }
      else {
        $first_value = reset($values);
        if (is_array($first_value)) {
          return $values[$form_id] ?? [];
        }
        else {
          return $values;
        }
      }
    }
  }

  /**
   * Return the value of the flag for the current specific form with in a step.
   *
   * @param string $flag
   *   Key identifying a flag value that may be specified for a step, or for
   *   some or all of the forms that belong to a step.
   * @param string $form_id
   *   ID of the form currently being displayed.
   *
   * @return bool
   *   The value of the flag applicable to the current form in the current step.
   *   If the data value keyed by $key in $step is
   *   - not defined/non existent: then return FALSE.
   *   - a boolean value (or any other non-array value): then return that value.
   *   - an array of strings: then return TRUE if $form_id is in that array.
   *   - an array of booleans keyed by strings: then return the value keyed by
   *     $form_id, or FALSE if no such value is defined.
   */
  protected function flagApplies($flag, $form_id) {
    $step = $this->getStep();
    if (empty($step[$flag])) {
      return FALSE;
    }
    elseif (!is_array($step[$flag])) {
      return $step[$flag];
    }
    else {
      $values = $step[$flag];
      $first_value = reset($values);
      if (is_bool($first_value)) {
        return $values[$form_id] ?? FALSE;
      }
      else {
        return (in_array($form_id, $values));
      }
    }
  }

  /**
   * Helper method for executing sequence plugin callback functions.
   *
   * @param mixed $callback
   *   A PHP callback function. If $callback is a string beginning with '::',
   *   it is interpreted as a method of the current sequence plugin class.
   *
   * @return mixed
   *   The return value of the callback function.
   */
  protected function doCallback($callback) {
    // Transform a string beginning with '::' to a callable array for this
    // object.
    if (is_string($callback) && substr($callback, 0, 2) == '::') {
      $callback = [$this, substr($callback, 2)];
    }
    return call_user_func($callback);
  }

  /**
   * Causes a redirect to a different URL.
   *
   * Where possible, returning a RedirectResponse is preferred to calling this
   * method. However, when 'skip_if_not_present' is used, it is not known
   * whether a redirect is needed until hook_form_alter() is called, at which
   * point it is too late to return a RedirectResponse. So, throw an exception
   * to trigger a redirect in the same manner as the core FormBuilder.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to redirect to.
   */
  protected function redirect(Url $url) {
    $response = new RedirectResponse($url->toString());
    throw new EnforcedResponseException($response);
  }

}
