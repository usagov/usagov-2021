<?php

namespace Drupal\tome_static\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\tome_static\RequestPreparer;
use Drupal\tome_static\StaticGeneratorInterface;
use Drupal\tome_static\StaticUITrait;
use Drupal\tome_static\TomeStaticHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains a form for initializing a static build.
 *
 * @internal
 */
class StaticGeneratorForm extends FormBase {

  use StaticUITrait;

  /**
   * The static generator.
   *
   * @var \Drupal\tome_static\StaticGeneratorInterface
   */
  protected $static;

  /**
   * The state system.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The request preparer.
   *
   * @var \Drupal\tome_static\RequestPreparer
   */
  protected $requestPreparer;

  /**
   * StaticGeneratorForm constructor.
   *
   * @param \Drupal\tome_static\StaticGeneratorInterface $static
   *   The static generator.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   * @param \Drupal\tome_static\RequestPreparer $request_preparer
   *   The request preparer.
   */
  public function __construct(StaticGeneratorInterface $static, StateInterface $state, RequestPreparer $request_preparer) {
    $this->static = $static;
    $this->state = $state;
    $this->requestPreparer = $request_preparer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tome_static.generator'),
      $container->get('state'),
      $container->get('tome_static.request_preparer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tome_static_generator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Submitting this form will initiate a build of all uncached static pages site using Tome. Existing files in the static export directory (@dir) will be overridden.', [
        '@dir' => $this->static->getStaticDirectory(),
      ]) . '</p>',
    ];

    $form['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#default_value' => $this->state->get(StaticGeneratorInterface::STATE_KEY_URL, 'http://127.0.0.1'),
      '#required' => TRUE,
      '#size' => 30,
      '#description' => $this->t('The absolute URL used for generating static pages. This should match the domain on the site where the static site will be deployed.'),
    ];

    $warnings = $this->getWarnings();
    if ($this->state->get(StaticGeneratorInterface::STATE_KEY_BUILDING, FALSE)) {
      $warnings[] = $this->t('Another user may be running a static build, proceed only if the last build failed unexpectedly.');
    }

    if (!empty($warnings)) {
      $form['warnings'] = [
        '#type' => 'container',
        'title' => [
          '#markup' => '<strong>' . $this->t('Build warnings') . '</strong>',
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => [],
        ],
      ];
      foreach ($warnings as $warning) {
        $form['warnings']['list']['#items'][] = [
          '#markup' => $warning,
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!UrlHelper::isValid($form_state->getValue('base_url'), TRUE)) {
      $form_state->setError($form['base_url'], $this->t('The provided URL is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->set(StaticGeneratorInterface::STATE_KEY_BUILDING, TRUE);
    $base_url = $form_state->getValue('base_url');
    $this->state->set(StaticGeneratorInterface::STATE_KEY_URL, $base_url);

    $this->static->prepareStaticDirectory();
    $original_params = TomeStaticHelper::setBaseUrl($this->getRequest(), $base_url);

    $paths = $this->static->getPaths();

    TomeStaticHelper::restoreBaseUrl($this->getRequest(), $original_params);

    $this->setBatch($paths, $base_url);
  }

  /**
   * Exports all remaining paths at the end of a previous batch.
   *
   * @param string $base_url
   *   The base URL.
   * @param array $context
   *   The batch context.
   */
  public function batchInvokePaths($base_url, array &$context) {
    if (!empty($context['results']['invoke_paths'])) {
      $context['results']['old_paths'] = isset($context['results']['old_paths']) ? $context['results']['old_paths'] : [];
      $context['results']['invoke_paths'] = array_diff($context['results']['invoke_paths'], $context['results']['old_paths']);
      $context['results']['old_paths'] = array_merge($context['results']['invoke_paths'], $context['results']['old_paths']);
      $invoke_paths = $this->static->exportPaths($context['results']['invoke_paths']);
      if (!empty($invoke_paths)) {
        $this->setBatch($invoke_paths, $base_url);
      }
    }
  }

  /**
   * Exports a path using Tome.
   *
   * @param string $path
   *   The path to export.
   * @param string $base_url
   *   The base URL.
   * @param array $context
   *   The batch context.
   */
  public function exportPath($path, $base_url, array &$context) {
    $original_params = TomeStaticHelper::setBaseUrl($this->getRequest(), $base_url);

    $this->requestPreparer->prepareForRequest();
    try {
      $invoke_paths = $this->static->requestPath($path);
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $this->formatPathException($path, $e);
      $invoke_paths = [];
    }

    TomeStaticHelper::restoreBaseUrl($this->getRequest(), $original_params);

    $context['results']['invoke_paths'] = isset($context['results']['invoke_paths']) ? $context['results']['invoke_paths'] : [];
    $context['results']['invoke_paths'] = array_merge($context['results']['invoke_paths'], $invoke_paths);
  }

  /**
   * Batch finished callback after all paths and assets have been exported.
   *
   * @param bool $success
   *   Whether or not the batch was successful.
   * @param mixed $results
   *   Batch results set with context.
   */
  public function finishCallback($success, $results) {
    $this->state->set(StaticGeneratorInterface::STATE_KEY_BUILDING, FALSE);

    $this->messenger()->deleteAll();
    if (!$success) {
      $this->messenger()->addError($this->t('Static build failed - consult the error log for more details.'));
      return;
    }
    if (!empty($results['errors'])) {
      foreach ($results['errors'] as $error) {
        $this->messenger()->addError($error);
      }
    }
    $this->messenger()->addStatus($this->t('Static build complete! To download the build, <a href=":download">click here.</a>', [
      '@dir' => $this->static->getStaticDirectory(),
      ':download' => Url::fromRoute('tome_static.download_page')->toString(),
    ]));
    $this->static->cleanupStaticDirectory();
  }

  /**
   * Sets a new batch.
   *
   * @param array $paths
   *   An array of paths to invoke.
   * @param string $base_url
   *   The base URL.
   */
  protected function setBatch(array $paths, $base_url) {
    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Generating static HTML...'))
      ->setFinishCallback([$this, 'finishCallback']);
    $paths = $this->static->exportPaths($paths);
    foreach ($paths as $path) {
      $batch_builder->addOperation([$this, 'exportPath'], [$path, $base_url]);
    }
    $batch_builder->addOperation([$this, 'batchInvokePaths'], [$base_url]);
    batch_set($batch_builder->toArray());
  }

}
