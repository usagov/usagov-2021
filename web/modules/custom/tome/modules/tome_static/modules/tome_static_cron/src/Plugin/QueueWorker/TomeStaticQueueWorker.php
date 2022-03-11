<?php

namespace Drupal\tome_static_cron\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\tome_static\RequestPreparer;
use Drupal\tome_static\StaticGeneratorInterface;
use Drupal\tome_static\TomeStaticHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Process a queue of static paths.
 *
 * @QueueWorker(
 *   id = "tome_static_cron",
 *   title = @Translation("Tome Static path processor"),
 *   cron = {"time" = 60}
 * )
 *
 * @internal
 */
class TomeStaticQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * An array of paths to invoke.
   */
  const STATE_KEY_INVOKE_PATHS = 'tome_static_cron.invoke_paths';

  /**
   * An array of paths already processed this run.
   */
  const STATE_KEY_OLD_PATHS = 'tome_static_cron.old_paths';

  /**
   * The static generator.
   *
   * @var \Drupal\tome_static\StaticGeneratorInterface
   */
  protected $static;

  /**
   * The request preparer.
   *
   * @var \Drupal\tome_static\RequestPreparer
   */
  protected $requestPreparer;

  /**
   * The current request, or NULL if there is no request.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The state system.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\tome_static\StaticGeneratorInterface $static
   *   The static generator.
   * @param \Drupal\tome_static\RequestPreparer $request_preparer
   *   The request preparer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticGeneratorInterface $static, RequestPreparer $request_preparer, RequestStack $request_stack, StateInterface $state, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->static = $static;
    $this->requestPreparer = $request_preparer;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->state = $state;
    $this->logger = $logger_factory->get('tome_static_cron');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tome_static.generator'),
      $container->get('tome_static.request_preparer'),
      $container->get('request_stack'),
      $container->get('state'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $base_url = $data['base_url'];

    $state_invoke_paths = $this->state->get(self::STATE_KEY_INVOKE_PATHS, []);

    if (isset($data['action']) && $data['action'] === 'process_invoke_paths') {
      $state_invoke_paths = $this->static->exportPaths($state_invoke_paths);
      _tome_static_cron_queue_paths($state_invoke_paths, $base_url);
      $this->state->set(TomeStaticQueueWorker::STATE_KEY_INVOKE_PATHS, []);
      return;
    }

    $path = $data['path'];

    if ($this->currentRequest) {
      $this->static->prepareStaticDirectory();
      $original_params = TomeStaticHelper::setBaseUrl($this->currentRequest, $base_url);
    }

    $this->requestPreparer->prepareForRequest();

    try {
      $invoke_paths = $this->static->requestPath($path);
    }
    catch (\Exception $e) {
      $this->logger->error('Error when requesting path "' . $path . '"": ' . $e->getMessage() . ' ' . $e->getTraceAsString(), []);
      $invoke_paths = [];
    }

    $this->state->set(self::STATE_KEY_INVOKE_PATHS, array_merge($state_invoke_paths, $invoke_paths));

    if ($this->currentRequest) {
      TomeStaticHelper::restoreBaseUrl($this->currentRequest, $original_params);
    }
  }

}
