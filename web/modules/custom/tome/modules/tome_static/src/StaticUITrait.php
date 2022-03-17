<?php

namespace Drupal\tome_static;

/**
 * Trait containing methods useful for different static user interfaces.
 *
 * @internal
 */
trait StaticUITrait {

  /**
   * Collects warnings to help users correct issues in rendered HTML.
   *
   * @return array
   *   An array of warning messages to display to the user.
   */
  protected function getWarnings() {
    $warnings = [];
    $performance_config = \Drupal::config('system.performance');
    if (!$performance_config->get('css.preprocess') || !$performance_config->get('js.preprocess')) {
      if (!$performance_config->get('css.preprocess') && !$performance_config->get('js.preprocess')) {
        $message = $this->t('CSS and JS preprocessing is disabled.');
      }
      elseif (!$performance_config->get('css.preprocess')) {
        $message = $this->t('CSS preprocessing is disabled.');
      }
      else {
        $message = $this->t('JS preprocessing is disabled.');
      }
      $warnings[] = $message . ' ' . $this->t('This could lead to performance issues. To resolve, visit /admin/config/development/performance.');
    }
    $twig_config = \Drupal::getContainer()->getParameter('twig.config');
    if ($twig_config['debug'] || !$twig_config['cache']) {
      if ($twig_config['debug'] && !$twig_config['cache']) {
        $message = $this->t('Twig debugging is enabled and caching is disabled.');
      }
      elseif ($twig_config['debug']) {
        $message = $this->t('Twig debugging is enabled.');
      }
      else {
        $message = $this->t('Twig caching is disabled.');
      }
      $warnings[] = $message . ' ' . $this->t('This could lead to performance issues. To resolve, edit the "twig.config" parameter in the "sites/*/services.yml" file, then rebuild cache.');
    }
    return $warnings;
  }

  /**
   * Formats an exception caught when requesting a path.
   *
   * @param string $path
   *   The path being exported.
   * @param \Exception $exception
   *   An exception.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A formatted message to present to the user.
   */
  protected function formatPathException($path, \Exception $exception) {
    return $this->t('Exception caught when requesting path @path in @file, line @line: @error', [
      '@path' => $path,
      '@file' => $exception->getFile(),
      '@line' => $exception->getLine(),
      '@error' => $exception->getMessage(),
    ]);
  }

  /**
   * Translates a string to the current language or to a given language.
   *
   * @param string $string
   *   A string containing the English text to translate.
   * @param array $args
   *   (optional) An associative array of replacements.
   * @param array $options
   *   (optional) An associative array of additional options.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   An object that, when cast to a string, returns the translated string.
   */
  abstract protected function t($string, array $args = [], array $options = []);

}
