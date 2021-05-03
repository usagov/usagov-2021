<?php

namespace Drupal\stepbystep_test\Plugin\Sequence;

use Drupal\stepbystep\Plugin\SequenceBase;

/**
 * Example Step by Step sequence.
 *
 * @Sequence(
 *   id = "stepbystep_test_step_options",
 *   route = "stepbystep_test.test_step_options_sequence",
 *   name = @Translation("Test step options"),
 *   description = @Translation("This sequence tests all options that can be set for each step."),
 * )
 */
class TestStepOptionsSequence extends SequenceBase {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    return [
      // Basic form.
      'step1' => [
        'route' => 'stepbystep_test.test_form1',
        'route_parameters' => ['parameter1' => 'value1'],
        'title' => $this->t('Step 1'),
        'form_id' => 'stepbystep_test_form_1',
        'instructions' => 'Form 1 instructions',
      ],
      // Uses 'form_id'.
      'step2' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 2'),
        'form_id' => 'stepbystep_test_form_1',
      ],
      // Uses 'exclude_form_id'.
      'step3' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 3'),
        'form_id' => 'stepbystep_test_form_2',
        'exclude_form_id' => 'stepbystep_test_form_1',
      ],
      // Uses 'form_elements' not per-form-id.
      'step4' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 4'),
        'form_id' => 'stepbystep_test_form_1',
        'form_elements' => ['textfield1', 'details1][textfield3'],
      ],
      // Uses 'form_elements' per-form-id.
      'step5' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 5'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'form_elements' => [
          'stepbystep_test_form_1' => ['textfield1', 'details1][textfield3'],
          'stepbystep_test_form_2' => ['textfield2', 'details1][textfield4'],
        ],
      ],
      // Uses 'exclude_form_elements' not per-form-id.
      'step6' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 6'),
        'form_id' => 'stepbystep_test_form_1',
        'exclude_form_elements' => ['textfield1', 'details1][textfield3'],
      ],
      // Uses 'exclude_form_elements' per-form-id.
      'step7' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 7'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'exclude_form_elements' => [
          'stepbystep_test_form_1' => [
            'textfield1',
            'details1][textfield3',
          ],
          'stepbystep_test_form_2' => [
            'textfield2',
            'details1][textfield4', 'actions][cancel',
          ],
        ],
      ],
      // Uses 'wait_until_done' not per-form-id.
      'step8' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 8'),
        'form_id' => 'stepbystep_test_form_1',
        'wait_until_done' => TRUE,
      ],
      // Uses 'wait_until_done' per-form-id.
      'step9' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 9'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'wait_until_done' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'do_not_rename_submit' not per-form-id.
      'step10' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 10'),
        'form_id' => 'stepbystep_test_form_1',
        'do_not_rename_submit' => TRUE,
      ],
      // Uses 'do_not_rename_submit' per-form-id.
      'step11' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 11'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'do_not_rename_submit' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'no_skip_button' not per-form-id.
      'step12' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 12'),
        'form_id' => 'stepbystep_test_form_1',
        'no_skip_button' => TRUE,
      ],
      // Uses 'no_skip_button' per-form-id.
      'step13' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 13'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'no_skip_button' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'no_skip_button' per-form-id.
      'step14' => [
        'route' => 'stepbystep_test.test_form2',
        'title' => $this->t('Step 14'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'no_skip_button' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'no_wizard_buttons' not per-form-id.
      'step15' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 15'),
        'form_id' => 'stepbystep_test_form_1',
        'no_wizard_buttons' => TRUE,
      ],
      // Uses 'no_wizard_buttons' per-form-id.
      'step16' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 16'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'no_wizard_buttons' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'no_wizard_buttons' per-form-id.
      'step17' => [
        'route' => 'stepbystep_test.test_form2',
        'title' => $this->t('Step 17'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'no_wizard_buttons' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'skip_if_not_present' not per-form-id, OR method.
      'step18' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 18'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => ['textfield1'],
      ],
      // Uses 'skip_if_not_present' not per-form-id, OR method.
      'step19' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 19'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => ['does_not_exist'],
      ],
      // Uses 'skip_if_not_present' not per-form-id, AND method.
      'step20' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 20'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => [['textfield1', 'does_not_exist']],
      ],
      // Uses 'skip_if_not_present' not per-form-id, AND method.
      'step21' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 21'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => [['does_not_exist', 'does_not_exist_2']],
      ],
      // Uses 'skip_if_not_present' per-form-id, OR method.
      'step22' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 22'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => [
          'stepbystep_test_form_1' => ['textfield1'],
        ],
      ],
      // Uses 'skip_if_not_present' per-form-id, OR method.
      'step23' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 23'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => [
          'stepbystep_test_form_1' => ['does_not_exist'],
        ],
      ],
      // Uses 'skip_if_not_present' per-form-id, AND method.
      'step24' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 24'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => [
          'stepbystep_test_form_1' => [['textfield1', 'does_not_exist']],
        ],
      ],
      // Uses 'skip_if_not_present' per-form-id, AND method.
      'step25' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 25'),
        'form_id' => 'stepbystep_test_form_1',
        'skip_if_not_present' => [
          'stepbystep_test_form_1' => [['does_not_exist', 'does_not_exist_2']],
        ],
      ],
      // Uses 'skip_if_not_present' per-form-id.
      'step26' => [
        'route' => 'stepbystep_test.test_form2',
        'title' => $this->t('Step 26'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'skip_if_not_present' => [
          'stepbystep_test_form_1' => ['does_not_exist'],
        ],
      ],
      // Uses 'not_applicable_if' with a boolean.
      'step27' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 27'),
        'form_id' => 'stepbystep_test_form_1',
        'not_applicable_if' => TRUE,
      ],
      // Uses 'not_applicable_if' with a boolean.
      'step28' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 28'),
        'form_id' => 'stepbystep_test_form_1',
        'not_applicable_if' => FALSE,
      ],
      // Uses 'not_applicable_if' with a callback returning TRUE.
      'step29' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 29'),
        'form_id' => 'stepbystep_test_form_1',
        'not_applicable_if' => '::trueCallback',
      ],
      // Uses 'not_applicable_if' with a callback returning FALSE.
      'step30' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 30'),
        'form_id' => 'stepbystep_test_form_1',
        'not_applicable_if' => '::falseCallback',
      ],
      // Uses 'completed_if' with a boolean.
      'step31' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 31'),
        'form_id' => 'stepbystep_test_form_1',
        'completed_if' => TRUE,
      ],
      // Uses 'completed_if' with a boolean.
      'step32' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 32'),
        'form_id' => 'stepbystep_test_form_1',
        'completed_if' => FALSE,
      ],
      // Uses 'completed_if' with a callback returning TRUE.
      'step33' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 33'),
        'form_id' => 'stepbystep_test_form_1',
        'completed_if' => '::trueCallback',
      ],
      // Uses 'completed_if' with a callback returning FALSE.
      'step34' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 34'),
        'form_id' => 'stepbystep_test_form_1',
        'completed_if' => '::falseCallback',
      ],
      // Uses 'upon_recompletion_reset_steps'.
      'step35' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 35'),
        'form_id' => 'stepbystep_test_form_1',
        'upon_recompletion_reset_steps' => ['step2', 'step3'],
      ],
      // Uses 'overrides'.
      'step36' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 36'),
        'form_id' => 'stepbystep_test_form_1',
        'overrides' => [
          'stepbystep_test_form_1' => [
            'textfield1' => ['title' => 'Form 1 text field 1 override'],
            'details1][textfield3' => ['default_value' => 'Form 1 text field 3 value'],
          ],
        ],
      ],
      // Uses 'no_progress' not per-form-id.
      'step37' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 37'),
        'form_id' => 'stepbystep_test_form_1',
        'no_progress' => TRUE,
      ],
      // Uses 'no_progress' per-form-id.
      'step38' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 38'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'no_progress' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'no_progress' per-form-id.
      'step39' => [
        'route' => 'stepbystep_test.test_form2',
        'title' => $this->t('Step 39'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'no_progress' => [
          'stepbystep_test_form_1' => TRUE,
        ],
      ],
      // Uses 'submit_buttons' not per-form-id with a single submit button.
      'step40' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 40'),
        'form_id' => 'stepbystep_test_form_1',
        'submit_buttons' => ['extra_actions][submit1'],
      ],
      // Uses 'submit_buttons' not per-form-id with multiple submit buttons.
      'step41' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 41'),
        'form_id' => 'stepbystep_test_form_1',
        'submit_buttons' => ['extra_actions][submit1', 'extra_actions][submit2'],
      ],
      // Uses 'submit_buttons' per-form-id with a single submit button.
      'step42' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 42'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'submit_buttons' => [
          'stepbystep_test_form_1' => ['extra_actions][submit1'],
        ],
      ],
      // Uses 'submit_buttons' per-form-id with multiple submit buttons.
      'step43' => [
        'route' => 'stepbystep_test.multi_form_controller',
        'title' => $this->t('Step 43'),
        'form_id' => ['stepbystep_test_form_1', 'stepbystep_test_form_2'],
        'submit_buttons' => [
          'stepbystep_test_form_1' => ['extra_actions][submit1', 'extra_actions][submit2'],
        ],
      ],
    ];
  }

  /**
   * Simple callback function that returns TRUE.
   */
  public function trueCallback() {
    return TRUE;
  }

  /**
   * Simple callback function that returns FALSE.
   */
  public function falseCallback() {
    return FALSE;
  }

}
