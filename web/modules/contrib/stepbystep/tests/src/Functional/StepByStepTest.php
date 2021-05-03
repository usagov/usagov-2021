<?php

namespace Drupal\Tests\stepbystep\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Step by Step sequences.
 *
 * @group stepbystep
 */
class StepByStepTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['stepbystep_test'];

  /**
   * Default theme to use when running tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Setup before executing each test.
   */
  protected function setUp() {
    parent::setUp();

    $user = $this->setUpCurrentUser([], [], TRUE);
    $this->drupalLogin($user);
  }

  /**
   * Tests that Step by Step is active when query parameters are present.
   */
  public function testStepByStepDetection() {
    // Not active.
    $this->drupalGet('stepbystep_test/test_form1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseNotContains('Save and continue');

    // Step 1 active.
    $this->drupalGet('stepbystep_test/test_form1', ['query' => ['stepbystep' => 'stepbystep_test_sequence', 'step' => 'step1']]);
    $this->assertSession()->responseContains('Save and continue');

    // Step 2 active.
    $this->drupalGet('stepbystep_test/test_form1', ['query' => ['stepbystep' => 'stepbystep_test_sequence', 'step' => 'step2']]);
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseNotContains('Form 1 text field 2');

    // Destination, not active.
    $this->drupalGet('stepbystep_test/test_form1', ['query' => ['destination' => '/admin']]);
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseNotContains('Save and continue');

    // Destination, step 1 active.
    $this->drupalGet('stepbystep_test/test_form1', ['query' => ['destination' => '/stepbystep_test/test_form1?stepbystep=stepbystep_test_sequence&step=step1']]);
    $this->assertSession()->responseContains('Save and continue');

    // Destination, step 2 active.
    $this->drupalGet('stepbystep_test/test_form1', ['query' => ['destination' => '/stepbystep_test/test_form1?stepbystep=stepbystep_test_sequence&step=step2']]);
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseNotContains('Form 1 text field 2');
  }

  /**
   * Tests progressing through a sequence with various statuses and states.
   */
  public function testStepProgression() {
    // Introduction form.
    $this->drupalGet('stepbystep_test/test_sequence');
    $this->assertSession()->pageTextContains('Test sequence');
    $this->assertSession()->pageTextContains('This sequence tests navigating a sequence.');
    $this->assertSession()->pageTextContains('Progress so far through this wizard');
    $this->assertSession()->responseContains('Start');
    $this->assertSession()->pageTextNotContains('The test sequence has been completed');
    $this->assertSession()->responseNotContains('Start again from the beginning');
    $this->assertSession()->responseNotContains('Resume');
    $this->assertStatus('Step 1', 'to do');
    $this->assertStatus('Step 2', 'to do');
    $this->assertStatus('Step 3', 'to do');
    $this->assertStatus('Step 4', 'to do');
    $this->assertStatus('Step 5', 'to do');

    // Go to the first step.
    $this->click('#edit-submit');

    // Test the Save and Continue button.
    $this->getSession()->getPage()->fillField('textfield1', 'test value 1');
    $this->getSession()->getPage()->fillField('textfield3', 'test value 3');
    // Check that submitting the form goes to the next step.
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->responseContains('Step 2</h1>');
    // Check that the form submit handlers were run.
    $this->drupalGet('stepbystep_test/test_sequence/step1');
    $this->assertSession()->pageTextContains('textfield1 test value 1 textfield2 textfield3 test value 3 textfield4');
    // Check the status was correctly updated.
    $this->assertStatus('Step 1', 'Completed');
    $this->assertStatus('Step 2', 'to do');

    // Check the introduction form is correct.
    $this->assertSession()->pageTextContains('This sequence tests navigating a sequence.');
    $this->assertSession()->pageTextContains('Progress so far through this wizard');
    $this->assertSession()->responseContains('Resume');
    $this->assertSession()->responseContains('Start again from the beginning');

    // Test clicking Resume takes us to the first available step.
    $this->click('#edit-submit');
    $this->assertSession()->responseContains('Step 2</h1>');

    // Test the Skip button.
    $this->drupalGet('stepbystep_test/test_sequence/step1');
    $this->getSession()->getPage()->fillField('textfield1', 'new value 1');
    // Check that skipping the form goes to the next step.
    $this->click('.stepbystep-skip-button');
    $this->assertSession()->responseContains('Step 2</h1>');
    // Check that the form submit handlers were NOT run.
    $this->drupalGet('stepbystep_test/test_sequence/step1');
    $this->assertSession()->pageTextContains('textfield1 test value 1 textfield2 textfield3 test value 3 textfield4');
    // Check the status was correctly updated.
    $this->assertStatus('Step 1', 'Skipped');
    $this->assertStatus('Step 2', 'to do');

    // Test validation.
    $this->drupalGet('stepbystep_test/test_sequence/step1');
    // Fill an invalid value.
    $this->getSession()->getPage()->fillField('textfield1', 'value 1 that is more than 20 characters');
    $this->click('.stepbystep-submit-button');
    // Check validation handlers were run, submit handlers were not run, and we
    // have not moved to a new step.
    $this->assertSession()->pageTextContains('Text field 1 must be 20 characters or less');
    $this->assertSession()->responseContains('Step 1</h1>');
    $this->assertSession()->pageTextContains('textfield1 test value 1 textfield2 textfield3 test value 3 textfield4');
    // The status should not have changed.
    $this->assertStatus('Step 1', 'Skipped');
    // Check that validation handlers do not run when Skip is clicked.
    $this->drupalGet('stepbystep_test/test_sequence/step1');
    // Fill an invalid value.
    $this->getSession()->getPage()->fillField('textfield1', 'value 1 that is more than 20 characters');
    $this->click('.stepbystep-skip-button');
    // Check validation handlers were not run, submit handlers were not run, and
    // have moved to a new step.
    $this->assertSession()->responseContains('Step 2</h1>');
    $this->assertSession()->pageTextContains('textfield1 test value 1 textfield2 textfield3 test value 3 textfield4');
    $this->assertSession()->pageTextNotContains('Text field 1 must be 20 characters or less');

    // Test skipping over steps.
    $this->drupalGet('stepbystep_test/test_sequence/step2');
    $this->click('.stepbystep-submit-button');
    // Steps 3 and 4 should mark themselves as n/a and completed.
    $this->assertSession()->responseContains('Step 5</h1>');
    $this->assertStatus('Step 2', 'Completed');
    $this->assertStatus('Step 3', 'Completed');
    $this->assertStatus('Step 4', 'Not applicable');
    $this->assertStatus('Step 5', 'to do');

    // Test the Next button takes us to the first 'to do' step.
    $this->drupalGet('stepbystep_test/test_sequence/step1');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->responseContains('Step 5</h1>');

    // Test completing the last step takes us to an earlier 'to do' step if one
    // exists.
    $this->drupalGet('stepbystep_test/test_sequence/step6');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->responseContains('Step 5</h1>');

    // Test completing all steps takes us to the introduction form.
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Test sequence');
    $this->assertSession()->pageTextContains('The test sequence has been completed');
    $this->assertSession()->pageTextNotContains('Progress so far through this wizard');
    $this->assertSession()->pageTextNotContains('This sequence tests navigating a sequence');
    $this->assertSession()->responseContains('Start again from the beginning');
    $this->assertSession()->responseNotContains('Resume');
    $this->assertStatus('Step 1', 'Completed');
    $this->assertStatus('Step 2', 'Completed');
    $this->assertStatus('Step 3', 'Completed');
    $this->assertStatus('Step 4', 'Not applicable');
    $this->assertStatus('Step 5', 'Completed');

    // Test the Reset button.
    $this->click('#edit-reset');
    $this->assertSession()->pageTextContains('Test sequence');
    $this->assertSession()->pageTextContains('This sequence tests navigating a sequence.');
    $this->assertSession()->pageTextContains('Progress so far through this wizard');
    $this->assertSession()->responseContains('Start');
    $this->assertSession()->pageTextNotContains('The test sequence has been completed');
    $this->assertSession()->responseNotContains('Start again from the beginning');
    $this->assertSession()->responseNotContains('Resume');
    $this->assertStatus('Step 1', 'to do');
    $this->assertStatus('Step 2', 'to do');
    $this->assertStatus('Step 3', 'to do');
    $this->assertStatus('Step 4', 'to do');
    $this->assertStatus('Step 5', 'to do');
  }

  /**
   * Tests the possible options for each step.
   *
   * All options are tested in a single test to greatly reduce setup time.
   */
  public function testStepOptions() {
    $this->optionTestTitle();
    $this->optionTestRouteParameters();
    $this->optionTestFormId();
    $this->optionTestExcludeFormId();
    $this->optionTestFormElements();
    $this->optionTestExcludeFormElements();
    $this->optionTestInstructions();
    $this->optionTestWaitUntilDone();
    $this->optionTestDoNotRenameSubmit();
    $this->optionTestNoSkipButton();
    $this->optionTestNoWizardButtons();
    $this->optionTestSkipIfNotPresent();
    $this->optionTestNotApplicableIf();
    $this->optionTestCompletedIf();
    $this->optionTestUponRecompletionResetSteps();
    $this->optionTestOverrides();
    $this->optionTestNoProgress();
    $this->optionTestSubmitButtons();
  }

  /**
   * Tests the 'title' step option.
   */
  private function optionTestTitle() {
    $this->drupalGet('stepbystep_test/test_step_options/step1');
    $this->assertSession()->pageTextContains('Step 1');
  }

  /**
   * Tests the 'route_parameters' step option.
   */
  private function optionTestRouteParameters() {
    $this->drupalGet('stepbystep_test/test_step_options/step1');
    $this->assertSession()->pageTextContains('Parameter 1 value1');
  }

  /**
   * Tests the 'form_id' step option.
   */
  private function optionTestFormId() {
    $this->drupalGet('stepbystep_test/test_step_options/step2');

    // Check that wizard buttons were applied to Form 1 and not Form 2.
    $this->assertSession()->responseNotContains('Save 1');
    $this->assertSession()->responseContains('Cancel 1');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save 2');
    $this->assertSession()->responseContains('Cancel 2');
  }

  /**
   * Tests the 'exclude_form_id' step option.
   */
  private function optionTestExcludeFormId() {
    $this->drupalGet('stepbystep_test/test_step_options/step3');

    // Check that Form 1 is hidden and Form 2 is visible.
    $this->assertSession()->responseNotContains('Save 1');
    $this->assertSession()->responseNotContains('Cancel 1');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseContains('Cancel 2');
  }

  /**
   * Tests the 'form_elements' step option.
   */
  private function optionTestFormElements() {
    // Check visible elements not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step4');
    $this->assertSession()->pageTextContains('Form 1 text field 1');
    $this->assertSession()->pageTextContains('Form 1 text field 3');
    $this->assertSession()->pageTextNotContains('Form 1 text field 2');
    $this->assertSession()->pageTextNotContains('Form 1 text field 4');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');

    // Check visible elements declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step5');
    $this->assertSession()->pageTextContains('Form 1 text field 1');
    $this->assertSession()->pageTextContains('Form 1 text field 3');
    $this->assertSession()->pageTextNotContains('Form 1 text field 2');
    $this->assertSession()->pageTextNotContains('Form 1 text field 4');
    $this->assertSession()->pageTextContains('Form 2 text field 2');
    $this->assertSession()->pageTextContains('Form 2 text field 4');
    $this->assertSession()->pageTextNotContains('Form 2 text field 1');
    $this->assertSession()->pageTextNotContains('Form 2 text field 3');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->pageTextContains('Cancel 2');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
  }

  /**
   * Tests the 'exclude_form_elements' step option.
   */
  private function optionTestExcludeFormElements() {
    // Check hidden elements not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step6');
    $this->assertSession()->pageTextNotContains('Form 1 text field 1');
    $this->assertSession()->pageTextNotContains('Form 1 text field 3');
    $this->assertSession()->pageTextContains('Form 1 text field 2');
    $this->assertSession()->pageTextContains('Form 1 text field 4');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');

    // Check hidden elements declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step7');
    $this->assertSession()->pageTextNotContains('Form 1 text field 1');
    $this->assertSession()->pageTextNotContains('Form 1 text field 3');
    $this->assertSession()->pageTextContains('Form 1 text field 2');
    $this->assertSession()->pageTextContains('Form 1 text field 4');
    $this->assertSession()->pageTextNotContains('Form 2 text field 2');
    $this->assertSession()->pageTextNotContains('Form 2 text field 4');
    $this->assertSession()->pageTextContains('Form 2 text field 1');
    $this->assertSession()->pageTextContains('Form 2 text field 3');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->pageTextNotContains('Cancel 2');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
  }

  /**
   * Tests the 'instructions' step option.
   */
  private function optionTestInstructions() {
    $this->drupalGet('stepbystep_test/test_step_options/step1');
    $this->assertSession()->pageTextContains('Form 1 instructions');
  }

  /**
   * Tests the 'wait_until_done' step option.
   */
  private function optionTestWaitUntilDone() {
    // Check wait_until_done not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step8');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
    $this->click('input[value="Save 1"]');
    // Cannot use $this->assertSession()->addressMatches() to check the query
    // string.
    $this->assertContains('step8', $this->getUrl());
    $this->click('input[value="Save and continue"]');
    $this->assertContains('step9', $this->getUrl());

    // Check wait_until_done declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step9');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->pageTextContains('Cancel 2');
    $this->assertSession()->responseNotContains('Save 2');
    $this->click('input[value="Save 1"]');
    // Cannot use $this->assertSession()->addressMatches() to check the query
    // string.
    $this->assertContains('step9', $this->getUrl());
    $this->click('input[value="Save and continue"]');
    $this->assertContains('step10', $this->getUrl());
  }

  /**
   * Tests the 'do_not_rename_submit' step option.
   */
  private function optionTestDoNotRenameSubmit() {
    // Check do_not_rename_submit not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step10');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseNotContains('Save and continue');

    // Check do_not_rename_submit declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step11');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->pageTextContains('Cancel 2');
    $this->assertSession()->responseNotContains('Save 2');
  }

  /**
   * Tests the 'no_skip_button' step option.
   */
  private function optionTestNoSkipButton() {
    // Check no_skip_button not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step12');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseNotContains('Skip this step');

    // Check no_skip_button declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step13');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseNotContains('Skip this step');
    $this->drupalGet('stepbystep_test/test_step_options/step14');
    $this->assertSession()->pageTextContains('Cancel 2');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseContains('Skip this step');
  }

  /**
   * Tests the 'no_wizard_buttons' step option.
   */
  private function optionTestNoWizardButtons() {
    // Check no_wizard_buttons not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step15');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseNotContains('Skip this step');
    $this->assertSession()->responseNotContains('Save and continue');

    // Check no_wizard_buttons declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step16');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseNotContains('Skip this step');
    $this->assertSession()->responseNotContains('Save and continue');
    $this->drupalGet('stepbystep_test/test_step_options/step17');
    $this->assertSession()->pageTextContains('Cancel 2');
    $this->assertSession()->responseNotContains('Save 2');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseContains('Skip this step');
  }

  /**
   * Tests the 'skip_if_not_present' step option.
   */
  private function optionTestSkipIfNotPresent() {
    // Check skip_if_not_present not declared per-form-id, OR method.
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step18');
    $this->assertSession()->pageTextContains('Step 18');
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step19');
    $this->assertSession()->pageTextContains('Step 20');
    $this->assertStatus('Step 19', 'Not applicable');

    // Check skip_if_not_present not declared per-form-id, AND method.
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step20');
    $this->assertSession()->pageTextContains('Step 20');
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step21');
    $this->assertSession()->pageTextContains('Step 22');
    $this->assertStatus('Step 21', 'Not applicable');

    // Check skip_if_not_present declared per-form-id, OR method.
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step22');
    $this->assertSession()->pageTextContains('Step 22');
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step23');
    $this->assertSession()->pageTextContains('Step 24');
    $this->assertStatus('Step 23', 'Not applicable');

    // Check skip_if_not_present declared per-form-id, AND method.
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step24');
    $this->assertSession()->pageTextContains('Step 24');
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step25');
    $this->assertSession()->pageTextContains('Step 26');
    $this->assertStatus('Step 25', 'Not applicable');

    // Check skip_if_not_present declared per-form-id, different form.
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step26');
    $this->assertSession()->pageTextContains('Step 26');
  }

  /**
   * Tests the 'not_applicable_if' step option.
   *
   * Steps with not_applicable_if do not redirect if accessed directly, so test
   * by starting on the previous step and clicking 'Save and continue'.
   */
  private function optionTestNotApplicableIf() {
    // Check not_applicable_if with a boolean.
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step26');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 28');
    $this->assertStatus('Step 27', 'Not applicable');
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step27');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 28');

    // Check not_applicable_if with a callback.
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step28');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 30');
    $this->assertStatus('Step 29', 'Not applicable');
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step29');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 30');
  }

  /**
   * Tests the 'completed_if' step option.
   *
   * Steps with completed_if do not redirect if accessed directly, so test
   * by starting on the previous step and clicking 'Save and continue'.
   */
  private function optionTestCompletedIf() {
    // Check completed_if with a boolean.
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step30');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 32');
    $this->assertStatus('Step 31', 'Completed');
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step31');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 32');

    // Check completed_if with a callback.
    // Check redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step32');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 34');
    $this->assertStatus('Step 33', 'Completed');
    // Check no redirect.
    $this->drupalGet('stepbystep_test/test_step_options/step33');
    $this->click('.stepbystep-submit-button');
    $this->assertSession()->pageTextContains('Step 34');
  }

  /**
   * Tests the 'upon_recompletion_reset_steps' step option.
   */
  private function optionTestUponRecompletionResetSteps() {
    // First mark some steps completed.
    $this->drupalGet('stepbystep_test/test_step_options/step1');
    $this->click('.stepbystep-submit-button');
    $this->drupalGet('stepbystep_test/test_step_options/step2');
    $this->click('.stepbystep-submit-button');
    $this->drupalGet('stepbystep_test/test_step_options/step3');
    $this->click('.stepbystep-submit-button');
    $this->drupalGet('stepbystep_test/test_step_options/step4');
    $this->click('.stepbystep-submit-button');
    // Check their statuses are 'Completed'.
    $this->assertStatus('Step 1', 'Completed');
    $this->assertStatus('Step 2', 'Completed');
    $this->assertStatus('Step 3', 'Completed');
    $this->assertStatus('Step 4', 'Completed');

    // Submit the step to reset the other steps.
    $this->drupalGet('stepbystep_test/test_step_options/step35');
    $this->click('.stepbystep-submit-button');
    // Check the statuses are correct.
    $this->assertStatus('Step 1', 'Completed');
    $this->assertStatus('Step 2', 'to do');
    $this->assertStatus('Step 3', 'to do');
    $this->assertStatus('Step 4', 'Completed');
    $this->assertStatus('Step 35', 'Completed');
  }

  /**
   * Tests the 'overrides' step option.
   */
  private function optionTestOverrides() {
    // Check visible elements not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step36');
    $this->assertSession()->pageTextContains('Form 1 text field 1 override');
    $this->assertSession()->fieldValueEquals('Form 1 text field 3', 'Form 1 text field 3 value');
    $this->assertSession()->pageTextContains('Form 2 text field 1');
    $this->assertSession()->fieldValueEquals('Form 2 text field 3', '');
  }

  /**
   * Tests the 'no_progress' step option.
   */
  private function optionTestNoProgress() {
    // Check no_progress not declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step37');
    $this->assertSession()->responseNotContains('progress__bar');

    // Check no_progress declared per-form-id.
    $this->drupalGet('stepbystep_test/test_step_options/step38');
    $this->assertSession()->responseNotContains('progress__bar');
    $this->drupalGet('stepbystep_test/test_step_options/step39');
    $this->assertSession()->responseContains('progress__bar');
    $this->assertSession()->pageTextContains('Test step options: Step 14 of 43');
  }

  /**
   * Tests the 'submit_buttons' step option.
   */
  private function optionTestSubmitButtons() {
    // Check submit_buttons not declared per-form-id, single button.
    $this->drupalGet('stepbystep_test/test_step_options/step40');
    $this->getSession()->getPage()->fillField('textfield1', 'new value 40');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseNotContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseNotContains('Form 1 submit extra 1');
    $this->assertSession()->responseContains('Form 1 submit extra 2');
    // Click the extra button that is not declared in submit_buttons.
    $this->click('input[value="Form 1 submit extra 2"]');
    // Check we are still on the current step and submit handlers were run.
    $this->assertContains('step40', $this->getUrl());
    $this->drupalGet('stepbystep_test/test_step_options/step40');
    $this->assertSession()->pageTextContains('textfield1 new value 40 extra');
    $this->getSession()->getPage()->fillField('textfield1', 'newer value 40');
    $this->click('input[value="Save and continue"]');
    // Check we are on the next step and submit handlers were run.
    $this->assertContains('step41', $this->getUrl());
    $this->drupalGet('stepbystep_test/test_step_options/step40');
    $this->assertSession()->pageTextContains('textfield1 newer value 40 extra');

    // Check submit_buttons not declared per-form-id, multiple buttons.
    $this->drupalGet('stepbystep_test/test_step_options/step41');
    $this->getSession()->getPage()->fillField('textfield1', 'new value 41');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseNotContains('Save and continue');
    $this->assertSession()->responseContains('Form 1 submit extra 1');
    $this->assertSession()->responseContains('Form 1 submit extra 2');
    // Click one of the extra submit buttons.
    $this->click('input[value="Form 1 submit extra 2"]');
    // Check we are on the next step and submit handlers were run.
    $this->assertContains('step42', $this->getUrl());
    $this->drupalGet('stepbystep_test/test_step_options/step41');
    $this->assertSession()->pageTextContains('textfield1 new value 41 extra');
    // Check the original save button does not move on to the next step.
    $this->getSession()->getPage()->fillField('textfield1', 'newer value 41');
    $this->click('input[value="Save 1"]');
    $this->assertContains('step41', $this->getUrl());
    $this->drupalGet('stepbystep_test/test_step_options/step41');
    $this->assertSession()->pageTextContains('textfield1 newer value 41');
    $this->assertSession()->pageTextNotContains('textfield1 newer value 41 extra');

    // Check submit_buttons declared per-form-id, single button.
    $this->drupalGet('stepbystep_test/test_step_options/step42');
    $this->getSession()->getPage()->fillField('textfield1', 'new value 42');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseNotContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseNotContains('Form 1 submit extra 1');
    $this->assertSession()->responseContains('Form 1 submit extra 2');
    $this->assertSession()->responseContains('Form 2 submit extra 1');
    $this->assertSession()->responseContains('Form 2 submit extra 2');

    // Check submit_buttons declared per-form-id, multiple buttons.
    $this->drupalGet('stepbystep_test/test_step_options/step43');
    $this->getSession()->getPage()->fillField('textfield1', 'new value 43');
    $this->assertSession()->pageTextContains('Cancel 1');
    $this->assertSession()->responseContains('Save 1');
    $this->assertSession()->responseContains('Skip this step');
    $this->assertSession()->responseContains('Save and continue');
    $this->assertSession()->responseContains('Form 1 submit extra 1');
    $this->assertSession()->responseContains('Form 1 submit extra 2');
    $this->assertSession()->responseContains('Form 2 submit extra 1');
    $this->assertSession()->responseContains('Form 2 submit extra 2');
  }

  /**
   * Asserts the status of a step.
   *
   * @param string $step_title
   *   The title of the step to check.
   * @param string $status
   *   The English label of the expected status.
   */
  private function assertStatus($step_title, $status) {
    $url = $this->getSession()->getCurrentUrl();
    if (strpos($url, 'stepbystep_test/test_sequence') || strpos($url, 'stepbystep=stepbystep_test_sequence')) {
      $this->drupalGet('stepbystep_test/test_sequence');
    }
    elseif (strpos($url, 'stepbystep_test/test_step_options') || strpos($url, 'stepbystep=stepbystep_test_step_options')) {
      $this->drupalGet('stepbystep_test/test_step_options');
    }
    else {
      throw new \Exception('assertStatus() could not determine sequence ID from the current URL: ' . $url);
    }
    $this->assertSession()->pageTextContains("$step_title $status");
  }

}
