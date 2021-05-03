<?php

/**
 * @file
 * Webform libraries.
 */

use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Utility\WebformDialogHelper;

/**
 * Implements hook_library_info_build().
 */
function webform_library_info_build() {
  $base_path = base_path();
  $default_query_string = \Drupal::state()->get('system.css_js_query_string') ?: '0';

  /** @var \Drupal\webform\WebformInterface[] $webforms */
  $webforms = Webform::loadMultiple();
  $libraries = [];
  foreach ($webforms as $webform_id => $webform) {
    $assets = array_filter($webform->getAssets());
    foreach ($assets as $type => $value) {
      // Note:
      // Set 'type' to 'external' and manually build the CSS/JS file path
      // to prevent JS from being parsed by locale_js_alter()
      // @see locale_js_alter()
      // @see https://www.drupal.org/node/1803330
      $settings = ['type' => 'external', 'preprocess' => FALSE, 'minified' => FALSE];
      if ($type === 'css') {
        $libraries["webform.css.$webform_id"] = [
          'css' => ['theme' => ["{$base_path}webform/css/{$webform_id}?{$default_query_string}" => $settings]],
        ];
      }
      else {
        $libraries["webform.javascript.$webform_id"] = [
          'js' => ["{$base_path}webform/javascript/{$webform_id}?{$default_query_string}" => $settings],
        ];
      }
    }
  }
  return $libraries;
}

/**
 * Implements hook_library_info_alter().
 */
function webform_library_info_alter(&$libraries, $extension) {
  // Only alter modules that declare webform libraries.
  // @see hook_webform_libraries_info()
  $webform_libraries_modules = \Drupal::moduleHandler()->getImplementations('webform_libraries_info');
  $webform_libraries_modules[] = 'webform';

  if (!in_array($extension, $webform_libraries_modules)) {
    return;
  }

  // Use Tippy.js 6.x which is compatible with Drupal 9.x.
  if (isset($libraries['libraries.tippyjs']) && floatval(\Drupal::VERSION) >= 9) {
    /** @var \Drupal\webform\WebformLibrariesManagerInterface $libraries_manager */
    $libraries_manager = \Drupal::service('webform.libraries_manager');
    $tippyjs_library = $libraries_manager->getLibrary('tippyjs/6.x');
    $libraries['libraries.tippyjs']['version'] = $tippyjs_library['version'];
    $libraries['libraries.tippyjs']['cdn'] = ['/libraries/tippyjs/6.x/' => 'https://unpkg.com/tippy.js@' .  $tippyjs_library['version'] . '/dist/'];
    $libraries['libraries.tippyjs']['js'] = ['/libraries/tippyjs/6.x/' . basename($tippyjs_library['download_url']->toString()) => []];
  }

  // If webform date element in D8 use the old dependency on 'core/drupal.date'.
  if (isset($libraries['webform.element.date'])
    && floatval(\Drupal::VERSION) < 9
    && !\Drupal::moduleHandler()->moduleExists('jquery_ui_datepicker')) {
    $libraries['webform.element.date']['dependencies'] = ['core/drupal.date', 'core/drupalSettings'];
  }

  // If chosen_lib.module is installed, then update the dependency.
  if (\Drupal::moduleHandler()->moduleExists('chosen_lib')) {
    if (isset($libraries['webform.element.chosen'])) {
      $dependencies =& $libraries['webform.element.chosen']['dependencies'];
      foreach ($dependencies as $index => $dependency) {
        if ($dependency === 'webform/libraries.jquery.chosen') {
          $dependencies[$index] = 'chosen_lib/chosen';
          $dependencies[] = 'chosen_lib/chosen.css';
          break;
        }
      }
    }
  }

  // If select2.module is installed, then update the dependency.
  if (\Drupal::moduleHandler()->moduleExists('select2')) {
    if (isset($libraries['webform.element.select2'])) {
      $dependencies =& $libraries['webform.element.select2']['dependencies'];
      foreach ($dependencies as $index => $dependency) {
        if ($dependency === 'webform/libraries.jquery.select2') {
          $dependencies[$index] = 'select2/select2';
          break;
        }
      }
    }
  }

  /** @var \Drupal\webform\WebformLibrariesManagerInterface $libraries_manager */
  $libraries_manager = \Drupal::service('webform.libraries_manager');

  // Map /library/* paths to CDN.
  // @see webform.libraries.yml.
  foreach ($libraries as $library_name => &$library) {
    // Remove excluded libraries.
    if ($libraries_manager->isExcluded($library_name)) {
      unset($libraries[$library_name]);
      continue;
    }
    // Skip libraries installed by other modules.
    if (isset($library['module'])) {
      continue;
    }

    if (!empty($library['dependencies'])) {
      // Remove excluded libraries from dependencies.
      foreach ($library['dependencies'] as $dependency_index => $dependency_name) {
        if ($libraries_manager->isExcluded($dependency_name)) {
          $library['dependencies'][$dependency_index] = NULL;
          $library['dependencies'] = array_filter($library['dependencies']);
        }
      }
    }

    // Handle CDN support.
    if (isset($library['cdn']) && isset($library['directory'])
      && !$libraries_manager->exists($library['directory'])) {
      _webform_library_info_alter_recursive($library, $library['cdn']);
    }
  }
}

/**
 * Recursive through a webform library.
 *
 * @param array $library
 *   A webform library defined in webform.libraries.yml.
 * @param array $cdn
 *   A associative array of library paths mapped to CDN URL.
 */
function _webform_library_info_alter_recursive(array &$library, array $cdn) {
  foreach ($library as $key => &$value) {
    // CSS and JS files and listed in associative arrays keyed via string.
    if (!is_string($key) || !is_array($value)) {
      continue;
    }

    // Ignore the CDN's associative array.
    if ($key === 'cdn') {
      continue;
    }

    // Replace the CDN sources (i.e. /library/*) with the CDN URL destination
    // (https://cdnjs.cloudflare.com/ajax/libs/*).
    foreach ($cdn as $source => $destination) {
      if (strpos($key, $source) === 0) {
        $uri = str_replace($source, $destination, $key);
        $library[$uri] = $value;
        $library[$uri]['type'] = 'external';
        unset($library[$key]);
        break;
      }
    }

    // Recurse downward to find nested libraries.
    _webform_library_info_alter_recursive($value, $cdn);
  }
}

/**
 * Implements hook_css_alter().
 */
function webform_css_alter(&$css, AttachedAssetsInterface $assets) {
  // Remove the stable.theme's off-canvas CSS reset for webform admin routes.
  // @see https://www.drupal.org/project/drupal/issues/2826722
  //
  // NOTE: Most admin themes correctly style jQuery UI dialogs because Drupal's
  // admin UI, especially Views, relies on them.
  $use_off_canvas = WebformDialogHelper::useOffCanvas();
  // @todo Remove once Claro theme support offcanvas tray.
  $is_claro_theme = \Drupal::service('webform.theme_manager')->isActiveTheme('claro');
  $is_admin_route = \Drupal::service('router.admin_context')->isAdminRoute();
  $is_webform_route = preg_match('/(^webform|^entity\.webform|^entity\.node\.webform)/', \Drupal::routeMatch()->getRouteName());
  if ($is_claro_theme || ($use_off_canvas && $is_admin_route && $is_webform_route)) {
    foreach ($css as $key => $item) {
      // @see 'core/misc/dialog/off-canvas
      // @see core/themes/stable/css/core/dialog/off-canvas
      if (strpos($key, '/off-canvas.') !== FALSE) {
        unset($css[$key]);
      }
    }
  }

  _webform_asset_alter($css, 'css');
}

/**
 * Implements hook_js_alter().
 */
function webform_js_alter(&$javascript, AttachedAssetsInterface $assets) {
  _webform_asset_alter($javascript, 'javascript');
}

/**
 * Alter Webform CSS or JavaScript assets and make sure they appear last.
 *
 * @param array $items
 *   An array of all CSS or JavaScript being presented on the page.
 * @param string $type
 *   The type of asset being attached.
 *
 * @see hook_library_info_build()
 */
function _webform_asset_alter(array &$items, $type) {
  foreach ($items as $key => &$item) {
    if (strpos($key, "webform/$type/") === 0) {
      $item['weight'] = 1000;
      $item['group'] = 1000;
    }
  }
}
