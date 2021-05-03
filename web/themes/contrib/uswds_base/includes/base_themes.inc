<?php

/**
 * @file
 * Utility code related to base themes.
 *
 * The concepts/wording in this file are inspired by (ie, stolen from) the D7
 * Omega base theme.
 */

/**
 * Gets the full theme trail, from active to top-level base.
 *
 * @return array
 *   An array of all themes in the trail, ordered from active to top-level base.
 */
function uswds_base_theme_trail() {
  $theme = \Drupal::theme()->getActiveTheme();
  $all = [$theme->getName() => $theme] + $theme->getBaseThemeExtensions();
  // This gives us a complete lineage of themes, but we actually only care about
  // the USWDS Base theme and any child themes.
  $trail = [];
  foreach ($all as $key => $value) {
    $trail[$key] = $value;
    if ('uswds_base' == $key) {
      break;
    }
  }
  return $trail;
}

/**
 * Finds the first occurrence of a given file in the theme trail.
 *
 * @param string $file
 *   The relative path to a file.
 *
 * @return string
 *   The path to the file. If the file does not exist at all, it will simply
 *   return the path of the file as it would be if it existed in the given theme
 *   directly. This ensures that the code that uses this function does not break
 *   if a file does not exist anywhere.
 */
function uswds_base_theme_trail_file($file) {
  $trail = uswds_base_theme_trail();
  foreach ($trail as $name => $theme) {
    $current = $theme->getPath() . '/' . $file;
    if (file_exists($current)) {
      return $current;
    }
  }
  // The default (fallback) path is the path of the active theme, even if it
  // does not actually have that file.
  $active = array_shift($trail);
  return $active->getPath() . '/' . $file;
}

/**
 * Implements hook_theme_registry_alter().
 */
function uswds_base_theme_registry_alter(&$registry) {

  $trail = uswds_base_theme_trail();
  // For the purpose of this, we want the theme trail in reverse order: from
  // root base theme to active theme. This way we can let base themes run their
  // functions before the active theme.
  $trail = array_reverse($trail);

  foreach ($trail as $theme) {
    uswds_base_register_theme_hooks($registry, $theme);
  }
}

/**
 * Helper function to register preprocessor functions for a theme.
 */
function uswds_base_register_theme_hooks(&$registry, $theme) {

  // Iterate over all preprocess files in the current theme.
  foreach (['preprocess'] as $type) {
    $length = -(strlen($type) + 1);

    // Only look for files that match the 'something.preprocess.inc' pattern.
    $mask = '/.' . $type . '.inc$/';

    $theme_name = $theme->getName();
    $themePath = drupal_get_path('theme', 'uswds_base');

    // Recursively scan the folder for the current step for (pre-)process
    // files and write them to the registry.
    $files = \Drupal::service('file_system')->scanDirectory($themePath . '/' . $type, $mask);


    // We sort here because we want to be sure that base hooks (eg,
    // foo.preprocess.inc) are handled before theme suggestions (eg,
    // foo__bar.preprocess.inc).
    ksort($files);

    foreach ($files as $file) {
      $hook = strtr(substr($file->name, 0, $length), '-', '_');
      $callback = "{$theme_name}_{$type}_{$hook}";

      // Is this a base hook or a theme suggestion?
      $hook_parts = explode('__', $hook);
      $theme_suggestion = count($hook_parts) > 1;
      $base_hook = $hook_parts[0];

      // If this is a theme suggestion that does not already exist, add it.
      if ($theme_suggestion && !isset($registry[$hook]) && isset($registry[$base_hook])) {
        $registry[$hook] = $registry[$base_hook];
        $registry[$hook]['base hook'] = $base_hook;
      }

      // If this might be a base hook instead, make sure that it's included in
      // ALL theme suggestions in the registry.
      if (!$theme_suggestion) {
        foreach ($registry as &$entry) {
          if (!empty($entry['base hook'])) {
            if ($hook == $entry['base hook']) {
              if (!in_array($callback, $entry["$type functions"])) {
                $entry["$type functions"][] = $callback;
                if (empty($entry['includes']) || !in_array($file->uri, $entry['includes'])) {
                  $entry['includes'][] = $file->uri;
                }
              }
            }
          }
        }
      }

      // If there is no entry in the registry to modify, skip.
      if (!isset($registry[$hook])) {
        continue;
      }

      // Append the included (pre-)process hook to the array of functions.
      $registry[$hook]["$type functions"][] = $callback;

      // By adding this file to the 'includes' array we make sure that it is
      // available when the hook is executed.
      $registry[$hook]['includes'][] = $file->uri;
    }
  }
}
