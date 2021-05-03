<?php

namespace Drupal\stepbystep\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a common interface for Step by Step sequences.
 */
interface SequenceInterface extends PluginInspectionInterface {

  /**
   * URL query parameter to hold the current sequence ID.
   */
  const SEQUENCE = 'stepbystep';

  /**
   * URL query parameter to hold the current step ID.
   */
  const STEP = 'step';

  /**
   * Step status indicating that a step has been completed.
   */
  const COMPLETED = 'completed';

  /**
   * Step status indicating that a step has been skipped.
   */
  const SKIPPED = 'skipped';

  /**
   * Step status indicating that a step is not applicable.
   */
  const NOT_APPLICABLE = 'n/a';

  /**
   * Step status indicating that a step's status should be reset to 'to do'.
   */
  const RESET = 'reset';

  /**
   * Returns the route of the sequence introduction form.
   *
   * @return string
   *   The route of the sequence introduction form.
   */
  public function getRoute();

  /**
   * Returns the sequence display name.
   *
   * @return string
   *   The sequence display name. Used in breadcrumbs and messages.
   */
  public function getName();

  /**
   * Returns the sequence title.
   *
   * @return string
   *   The sequence title. Used in page titles.
   */
  public function getTitle();

  /**
   * Returns the sequence description.
   *
   * @return string
   *   The sequence description. Shown on the sequence introduction form.
   */
  public function getDescription();

  /**
   * Returns a list of links to show on the sequence introduction form.
   *
   * @return array
   *   A render array of links (or really, any content) to show in the Advanced
   *   section of the sequence introduction form.
   */
  public function getAdvancedLinks();

  /**
   * Returns the definition of a step.
   *
   * @param string|null $step_id
   *   The ID of the step to get the definition of, or NULL to get the
   *   definition of the current step.
   *
   * @return array|bool
   *   The definition of the step if it exists, with an additional 'id' key
   *   containing the step ID.
   *   FALSE if the step is not defined in the sequence.
   */
  public function getStep($step_id = NULL);

  /**
   * Sets the current step of the sequence.
   *
   * @param string $step_id
   *   The ID of the step that will become the current step.
   */
  public function setStep($step_id);

  /**
   * Returns whether the sequence contains the given step.
   *
   * @param string $step_id
   *   The step ID to check.
   *
   * @return bool
   *   TRUE if the sequence contains a step with the given ID, FALSE otherwise.
   */
  public function hasStep($step_id);

  /**
   * Returns the URL of a step or the sequence introduction form.
   *
   * @param string $step_id
   *   The step ID to get the URL of, or NULL to get the URL of the sequence
   *   introduction form.
   * @param bool $redirect
   *   TRUE to return the final URL of the step. (/form?stepbystep=x&step=y)
   *   FALSE to return an intermediate URL that will redirect to the final URL.
   *   Using the an intermediate URL lets the StepByStepController intercept
   *   the step before it is shown.
   *
   * @return \Drupal\Core\Url
   *   The URL of the step or the sequence introduction form.
   */
  public function getUrl($step_id = NULL, $redirect = FALSE);

  /**
   * Returns the URL of the next step to do.
   *
   * @return \Drupal\Core\Url
   *   The URL of the next step to do, or the URL of the sequence introduction
   *   form if there are no steps left to do.
   */
  public function getNextUrl();

  /**
   * Returns the ID of the next step to do.
   *
   * A step is considered 'to do' if it has not been marked with any status.
   * If there are no remaining steps to do after the current step, the first
   * available step before the current step may be returned. If there are no
   * steps left to do anywhere in the sequence, NULL is returned.
   *
   * During the process of locating the next available step, any intermediate
   * steps are analyzed and their statuses may be updated.
   *
   * @return string|null
   *   The ID of the next step to do, or NULL if there are no steps left to do.
   */
  public function getNextStep();

  /**
   * Returns whether the current user has permission to access a step.
   *
   * @param string $step_id
   *   The step ID to check access for.
   *
   * @return bool
   *   Whether the current user has permission to access the site page
   *   corresponding to the step.
   */
  public function userAccess($step_id);

  /**
   * Returns all steps in this sequence.
   *
   * @return array
   *   Definitions for all steps in the sequence, keyed by step ID.
   *   Steps may contain the following keys. All keys are optional unless marked
   *   as (required).
   *    - 'route' (required): The name of the route of the existing page to use
   *      for the step.
   *    - 'route_parameters': Key/value pairs of route parameters pass to the
   *      step route.
   *    - 'title' (required): A translated, human readable name for this step.
   *    - 'form_id' (required): A string, or an array of strings, identifying
   *      the ID(s) of the form(s) that are displayed in this step and are to be
   *      modified within this step.  Multiple form IDs can apply for one step
   *      when a form interface has multiple stages, or if a form appears on a
   *      different page whose ?destination URL contains a link to this step.
   *      If you declare multiple form IDs here, many other settings may be
   *      given as multidimensional arrays, keyed by form ID first, in order
   *      only to apply to that specific form - these settings are annotated
   *      "May be declared per-form-id".  You are likely to want to use that
   *      format in combination with 'wait_until_done' and/or
   *      'no_wizard_buttons' on some forms within the step.
   *    - 'exclude_form_id': A string, or an array of strings, identifying the
   *      ID(s) of the form(s) that are displayed on the page(s) for this step
   *      but which you do NOT wish to be shown in this step in your sequence.
   *    - 'form_elements': An array of form elements to be shown in
   *      this step.  A form tree path may be used to identify an element, and
   *      expressed in the format 'search_fieldset][search_form][search'.
   *      Standard Drupal form elements, including the primary submit button,
   *      will be included automatically.  If this value is omitted, all
   *      elements in the form will be shown.  (May be declared per-form-id.)
   *    - 'exclude_form_elements': Instead of specifying elements to
   *      show, an array of form elements may be specified here, and they alone
   *      will be hidden.  (May be declared per-form-id.)
   *    - 'instructions': Additional (translated) text to be displayed at the
   *      top of the form when it is shown within the sequence.
   *    - 'wait_until_done': A boolean value.  If set to TRUE, instead of
   *      replacing the submit button with a 'Save and continue' button, the
   *      submit button is retained and serves to submit the form as usual,
   *      while an additional 'Save and continue' button is added which both
   *      submits the form and proceeds to the next step in the sequence.  (May
   *      be declared per-form-id.)
   *    - 'do_not_rename_submit': If TRUE, the form's existing submit button
   *      is not relabelled to 'Save and continue'.  (May be declared
   *      per-form-id.)
   *    - 'do_not_rename_title': If TRUE, the form's existing title is not
   *      renamed to match the title of the step.  (May be declared
   *      per-form-id.)
   *    - 'submit_buttons': An array of form tree paths identifying the submit
   *      buttons that, when clicked, will move on to the next step and mark the
   *      current step as completed. If this array contains a single element,
   *      the button will also be moved to the bottom of the form and relabelled
   *      to "Save and continue". This option is only needed if the form does
   *      not have a 'submit' or 'actions][submit' element, or if multiple
   *      buttons may be used to mark this step as completed. If multiple submit
   *      buttons are given, the 'wait_until_done' option has no effect.  (May
   *      be declared per-form-id.)
   *    - 'no_skip_button': A boolean value.  If TRUE, then the step
   *      is displayed without a 'skip this step' button, to strongly encourage
   *      the user to complete it.  However, subsequent steps can still be
   *      accessed directly via the links in the progress summary, so to really
   *      enforce a step one would need to implement a callback that checks
   *      whether the required step had been completed, and use that callback
   *      with 'not_applicable_if' on all other steps.  (May be declared
   *      per-form-id.)
   *    - 'no_wizard_buttons': If TRUE, the form is displayed without any Step
   *      by Step control buttons.  This is most useful in a per-form-id
   *      situation, when submitting the form will result in another form being
   *      shown under the same URL and that form does include Step by Step
   *      buttons, or when a form is shown on a url whose ?destination will
   *      redirect back to the step page, or when two or more (sub-)forms are
   *      visible on the page at once but only one of them should have the
   *      wizard buttons added.  (May be declared per-form-id.)
   *    - 'no_progress': If TRUE, the form is displayed without the Step by
   *      Step progress bar at the top. (May be declared per-form-id.)
   *    - 'suppress_menus': Array of strings. If the form is usually displayed
   *      with primary and secondary tabs, this setting can be used to hide the
   *      tab menus. The array may contain the values 'primary' and 'secondary'
   *      to control which level of tabs to hide. (May be declared per-form-id.)
   *    - 'skip_if_not_present':  An array of form elements which must be
   *      present in the form (as generated by existing code) otherwise Step by
   *      Step will mark it 'not applicable' and skip over it.  Items in this
   *      array are combined by OR.  Step will be skipped if any one of them is
   *      not present.  To skip step only if several items are ALL missing
   *      group them inside an inner array.  For example:
   *        'skip_if_not_present' => [
   *          ['this', 'or][this'],      // need to see at least one of these
   *          'required][element',       // and need to see this
   *        ]
   *      (May be declared per-form-id.)
   *    - 'not_applicable_if': A boolean expression or string containing the
   *      name of a callback function which will be evaluated when the step is
   *      accessed either directly or because it is next in sequence.  If the
   *      callback function returns non-empty (e.g. TRUE), then this step will
   *      be marked 'not applicable' and skipped over, with a message shown to
   *      the user. (May NOT be declared per-form-id.)
   *    - 'completed_if': Similar to 'not_applicable_if', but if the
   *      callback function returns non-empty then the step will be marked
   *      'completed' and skipped over, and a message indicating successful
   *      'completion is shown to the user.  (May NOT be declared per-form-id.)
   *    - 'upon_recompletion_reset_steps':  An array of sequence step IDs.
   *      Whenever the current step is marked or re-marked 'completed', the
   *      steps specified here will be marked as 'to do', regardless of whether
   *      they were previously marked 'not applicable' or 'completed'. Typically
   *      this would be used for steps that come after the current step and
   *      whose contents or available options depend on the setting of the
   *      current step.  Combining with 'completed_if' is probably not
   *      advisable. (May NOT be declared per-form-id.)
   *    - 'overrides': An array, keyed by form_id, of arrays, keyed by
   *      form tree path, of arrays of override values keyed by property name
   *      (without the leading '#').  Values of any form attribute may be
   *      overridden using this setting.  The form tree path is a string such as
   *      'node][taxonomy][term'.  (NB: MUST be declared per-form-id.)
   */
  public function getSteps();

  /**
   * Returns whether the sequence has been started.
   *
   * A sequence is considered "started" if any steps have a non-empty status.
   *
   * @return bool
   *   TRUE if the sequence has been started, FALSE otherwise.
   */
  public function isStarted();

  /**
   * Returns the number of finished steps in the sequence.
   *
   * Any step with a status is considered finished, including 'skipped' and
   * 'n/a'.
   *
   * @return int
   *   The number of finished steps in the sequence.
   */
  public function getFinishedStepsCount();

  /**
   * Returns whether the sequence has any steps remaining to do.
   *
   * A sequence is considered to have steps remaining if any steps have an empty
   * status.
   *
   * @return bool
   *   TRUE if the sequence has steps remaining to do, FALSE otherwise.
   */
  public function hasStepsRemaining();

  /**
   * Returns the progress the user has made through the sequence.
   *
   * @return array
   *   An associative array where the keys are step IDs, and the values are
   *   the SequenceInterface status constants. A missing step indicates that the
   *   step is still "to do".
   */
  public function getProgress();

  /**
   * Resets the progress of the sequence, or of certain steps in the sequence.
   *
   * @param string $status_to_reset
   *   If not NULL and not FALSE, only steps with this status will be reset.
   *   Otherwise, the status of all steps will be reset. A value of FALSE
   *   indicates that the all-steps reset was requested via the 'start again'
   *   button, i.e. potentially by a user without admin privileges for the
   *   sequence, in case a different behaviour is required in this case.
   */
  public function resetProgress($status_to_reset = NULL);

  /**
   * Sets the status of a step.
   *
   * @param array|string $step_id
   *   The ID(s) of the step(s) to set the status of.
   * @param string $status
   *   One of the SequenceInterface status constants.
   *
   * @return string
   *   The status that was set.
   */
  public function markStep($step_id, $status);

  /**
   * Alters a form to become a step in a Step by Step sequence.
   *
   * @param array $form
   *   The form structure to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The ID of the form being altered.
   *
   * @see hook_form_alter()
   */
  public function alterForm(array &$form, FormStateInterface $form_state, $form_id);

  /**
   * Allows sequences to perform custom form alterations before rendering.
   *
   * @param array $form
   *   The form structure to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The ID of the form being altered.
   * @param array $current_step
   *   The current step in the sequence.
   *
   * @see hook_form_alter()
   */
  public function finishAlter(array &$form, FormStateInterface $form_state, $form_id, array $current_step);

  /**
   * Additional validation handler for Step by Step forms.
   *
   * Called when the user submits the form, whether or not they have chosen
   * to move on to the next step or remain on the current step.
   * Executes after the form's existing validation handlers.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see \Drupal\Core\Form\FormInterface::validateForm()
   */
  public function validateForm(array &$form, FormStateInterface $form_state);

  /**
   * Additional submit handler for Step by Step forms.
   *
   * Only called when the user clicks the Save and Continue button.
   * Executes after the form's existing submit handlers.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see \Drupal\Core\Form\FormInterface::submitForm()
   */
  public function submitForm(array &$form, FormStateInterface $form_state);

  /**
   * Alters local tasks (tabs) on forms that appear in a Step by Step sequence.
   *
   * @param array $data
   *   The local tasks that appear on the page.
   * @param string $route_name
   *   The route name of the page.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheability metadata for the current route's local tasks.
   *
   * @see hook_menu_local_tasks_alter()
   */
  public function alterLocalTasks(array &$data, $route_name, RefinableCacheableDependencyInterface &$cacheability);

  /**
   * Alters a sequence introduction form.
   *
   * @param array $form
   *   The form structure to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see hook_form_alter()
   */
  public function alterIntroductionForm(array &$form, FormStateInterface $form_state);

  /**
   * Submission handler for a sequence introduction form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see \Drupal\Core\Form\FormInterface::submitForm()
   */
  public function submitIntroductionForm(array &$form, FormStateInterface $form_state);

}
