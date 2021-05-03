<?php

namespace Drupal\stepbystep;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\stepbystep\Annotation\Sequence;
use Drupal\stepbystep\Plugin\SequenceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin manager for Step by Step sequence plugins.
 */
class SequenceManager extends DefaultPluginManager {

  /**
   * SequenceManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Sequence',
      $namespaces,
      $module_handler,
      SequenceInterface::class,
      Sequence::class
    );
    $this->alterInfo('stepbystep_sequence');
    $this->setCacheBackend($cache_backend, 'stepbystep_sequence');
  }

  /**
   * Creates a sequence plugin instance from an HTTP request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request containing parameters for the Step by Step sequence and step.
   *
   * @return \Drupal\stepbystep\Plugin\SequenceInterface
   *   A sequence plugin instance from the current request parameters.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createInstanceFromRequest(Request $request) {
    /** @var \Drupal\stepbystep\Plugin\SequenceInterface $sequence */

    // Read the sequence ID and step ID from the request.
    list($sequence_id, $step_id) = static::getSequenceInfoFromRequest($request);
    // Create an instance of the plugin if it exists.
    if ($this->hasDefinition($sequence_id)) {
      $sequence = $this->createInstance($sequence_id);
      if ($sequence->hasStep($step_id)) {
        $sequence->setStep($step_id);
        return $sequence;
      }
      else {
        throw new PluginNotFoundException("Step '$step_id' does not exist.");
      }
    }
    else {
      throw new PluginNotFoundException("Step by Step sequence '$sequence_id' does not exist.");
    }
  }

  /**
   * Returns the sequence ID and step ID from HTTP request query parameters.
   *
   * Each step has a "primary" page whose URL looks something like:
   *    /admin/people?stepbystep=x&step=y
   * Each step can also have one or more "secondary" pages in the form:
   *    /admin?destination=/admin/people%3Fstepbystep%3Dx%26step%3Dy
   * This method extracts the sequence ID and step ID from either form of URL,
   * allowing sequence plugins to control the appearance of forms on either
   * their primary or secondary pages.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The HTTP request to read the parameters from, or NULL to use the current
   *   request. When possible, pass a Request object rather than NULL to make
   *   testing easier.
   *
   * @return array|bool
   *   An array of length 2 containing the sequence ID and step ID from the
   *   request, or FALSE if the request does not point to a sequence.
   */
  public static function getSequenceInfoFromRequest(Request $request = NULL) {
    if (empty($request)) {
      $request = \Drupal::request();
    }
    $query = $request->query;
    if ($query->has(SequenceInterface::SEQUENCE)) {
      // The 'stepbystep' parameter exists, so read the values directly.
      return [
        $query->get(SequenceInterface::SEQUENCE),
        $query->get(SequenceInterface::STEP),
      ];
    }
    if ($query->has('destination')) {
      // The 'stepbystep' parameter does not exist, but the 'destination'
      // parameter exists. Attempt to extract the sequence ID and step ID
      // from the destination.
      $options = UrlHelper::parse($query->get('destination'));
      if (array_key_exists(SequenceInterface::SEQUENCE, $options['query'])) {
        return [
          $options['query'][SequenceInterface::SEQUENCE],
          $options['query'][SequenceInterface::STEP],
        ];
      }
    }
    return FALSE;
  }

  /**
   * Returns whether a request contains Step by Step parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The HTTP request to read the parameters from, or NULL to use the current
   *   request. When possible, pass a Request object rather than NULL to make
   *   testing easier.
   *
   * @return bool
   *   TRUE if the request contains Step by Step parameters, FALSE otherwise.
   */
  public static function isSequenceActive(Request $request = NULL) {
    return static::getSequenceInfoFromRequest($request) !== FALSE;
  }

  /**
   * Returns the cache contexts that can change when a sequence is active.
   *
   * Various cacheable parts of a page should act differently when the page is
   * viewed in a Step by Step sequence. Applying these cache contexts ensures
   * that they are cached separately for when a sequence is active vs inactive.
   *
   * @return array
   *   The cache contexts that can change when a sequence is active.
   *
   * @see https://www.drupal.org/docs/8/api/cache-api/cache-contexts
   */
  public static function getSequenceCacheContexts() {
    // The 'stepbystep' and 'step' query parameters indicate the primary page
    // of a step is being viewed. The 'destination' query parameter indicates
    // that a secondary page of a step is potentially being viewed.
    return [
      'url.query_args:' . SequenceInterface::SEQUENCE,
      'url.query_args:' . SequenceInterface::STEP,
      'url.query_args:destination',
    ];
  }

}
