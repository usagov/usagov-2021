<?php

namespace Drupal\stepbystep_test\Plugin\Sequence;

use Drupal\stepbystep\Plugin\SequenceBase;

/**
 * Example Step by Step sequence.
 *
 * @Sequence(
 *   id = "stepbystep_test_sequence",
 *   route = "stepbystep_test.test_sequence",
 *   name = @Translation("Test sequence"),
 *   description = @Translation("This sequence tests navigating a sequence."),
 *   completed_description = @Translation("The test sequence has been completed."),
 * )
 */
class TestSequence extends SequenceBase {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    return [
      'step1' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 1'),
        'form_id' => 'stepbystep_test_form_1',
      ],
      'step2' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 2'),
        'form_id' => 'stepbystep_test_form_1',
        'exclude_form_elements' => ['textfield2'],
      ],
      'step3' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 3'),
        'form_id' => 'stepbystep_test_form_1',
        'completed_if' => TRUE,
      ],
      'step4' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 4'),
        'form_id' => 'stepbystep_test_form_1',
        'not_applicable_if' => TRUE,
      ],
      'step5' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 5'),
        'form_id' => 'stepbystep_test_form_1',
      ],
      'step6' => [
        'route' => 'stepbystep_test.test_form1',
        'title' => $this->t('Step 6'),
        'form_id' => 'stepbystep_test_form_1',
      ],
    ];
  }

}
