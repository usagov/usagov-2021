<?php

namespace Drupal\tome_static\Commands;

use Drupal\Core\State\StateInterface;
use Drupal\tome_static\RequestPreparer;
use Drupal\tome_static\StaticGeneratorInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contains the tome:static-export-path command.
 *
 * @internal
 */
class StaticExportPathCommand extends StaticCommand {

  /**
   * The default number of retry per failed process.
   */
  const RETRY_COUNT = 1;

  /**
   * The request preparer.
   *
   * @var \Drupal\tome_static\RequestPreparer
   */
  protected $requestPreparer;

  /**
   * Constructs a StaticCommand instance.
   *
   * @param \Drupal\tome_static\StaticGeneratorInterface $static
   *   The static service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   * @param \Drupal\tome_static\RequestPreparer $request_preparer
   *   The request preparer.
   */
  public function __construct(StaticGeneratorInterface $static, StateInterface $state, RequestPreparer $request_preparer) {
    parent::__construct($static, $state);
    $this->requestPreparer = $request_preparer;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('tome:static-export-path')
      ->setDescription('Exports static HTML for a specific path.')
      ->addArgument('chunk', InputArgument::REQUIRED, 'A comma separated list of paths.')
      ->addOption('process-count', NULL, InputOption::VALUE_OPTIONAL, 'Limits the number of processes to run concurrently.', static::PROCESS_COUNT)
      ->addOption('path-count', NULL, InputOption::VALUE_OPTIONAL, 'The number of paths to export per process.', static::PATH_COUNT)
      ->addOption('return-json', NULL, InputOption::VALUE_NONE, 'Whether or not paths that need invoking should be returned as JSON.')
      ->addOption('retry-count', NULL, InputOption::VALUE_OPTIONAL, 'The number of retry per failed process', static::RETRY_COUNT);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $chunk = $input->getArgument('chunk');
    $paths = explode(',', $chunk);
    $invoke_paths = [];
    foreach ($paths as $path) {
      $this->requestPreparer->prepareForRequest();
      try {
        $invoke_paths = array_merge($this->static->requestPath($path), $invoke_paths);
      }
      catch (\Exception $e) {
        $this->io->getErrorStyle()->error($this->formatPathException($path, $e));
      }
    }
    $options = $input->getOptions();
    if ($options['return-json']) {
      $this->io->write(json_encode($invoke_paths, JSON_PRETTY_PRINT));
    }
    else {
      $this->exportPaths($invoke_paths, $paths, $options['process-count'], $options['path-count'], FALSE, $options['retry-count'], $options['uri']);
    }
  }

}
