<?php

/**
 * @file
 * Generate test elements with #states.
 */

/**
 * Generate test elements with #states.
 *
 * @return array
 *   An array containing test elements with #states.
 */
function webform_test_test_states_client_invisible() {
  module_load_include('inc', 'webform_test', 'includes/webform_test.test_states');

  $elements = [];
  $elements['trigger'] = [
    '#type' => 'checkbox',
    '#title' => 'Hide and empty elements',
  ];
  $elements += _webform_test_states('invisible', [
    '#states' => [
      'invisible' => [
        ':input[name="trigger"]' => [
          'checked' => TRUE,
        ],
      ],
    ],
  ]);
  return $elements;
}
