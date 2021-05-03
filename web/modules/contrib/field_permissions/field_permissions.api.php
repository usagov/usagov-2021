<?php

/**
 * @file
 * Hooks for the field_permissions module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters discovered field_permission_type plugins.
 *
 * @param array $definitions
 *   Discovered definitions.
 */
function hook_field_permission_type_plugin_alter(array &$definitions) {
  unset($definitions['private']);
}

/**
 * @} End of "addtogroup hooks".
 */
