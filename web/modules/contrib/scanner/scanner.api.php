<?php

/**
 * @file
 * Hooks provided by the scanner module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter scanner definitions groups.
 *
 * @param array $scanners
 *   Scanner definitions, keyed by plugin ID.
 */
function hook_scanner_info_alter(array &$scanners) {
  // Change the default node scanner handler.
  $scanners['scanner_node']['class'] = 'Drupal\hook\Plugin\Scanner\CustomNodeScanner';
}

/**
 * @} End of "addtogroup hooks".
 */
