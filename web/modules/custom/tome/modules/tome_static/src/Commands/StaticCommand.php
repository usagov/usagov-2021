<?php

namespace Drupal\tome_static\Commands;

use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tome_base\CommandBase;
use Drupal\tome_static\StaticGeneratorInterface;
use Drupal\tome_static\StaticUITrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Contains the tome:static command.
 *
 * @internal
 */
class StaticCommand extends CommandBase {

  use StaticUITrait;
  use StringTranslationTrait;

  /**
   * The default number of processes to invoke.
   *
   * @var int
   */
  const PROCESS_COUNT = 5;

  /**
   * The default number of paths to export per process.
   */
  const PATH_COUNT = 5;

  /**
   * The default number of retry per failed process.
   */
  const RETRY_COUNT = 1;

  /**
   * The static service.
   *
   * @var \Drupal\tome_static\StaticGeneratorInterface
   */
  protected $static;

  /**
   * The state system.
   *
   * @var \\Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a StaticCommand instance.
   *
   * @param \Drupal\tome_static\StaticGeneratorInterface $static
   *   The static service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system.
   */
  public function __construct(StaticGeneratorInterface $static, StateInterface $state) {
    parent::__construct();
    $this->static = $static;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('tome:static')
      ->setDescription('Exports all pages on your site to static HTML.')
      ->addOption('process-count', NULL, InputOption::VALUE_OPTIONAL, 'Limits the number of processes to run concurrently.', static::PROCESS_COUNT)
      ->addOption('path-count', NULL, InputOption::VALUE_OPTIONAL, 'The number of paths to export per process.', static::PATH_COUNT)
      ->addOption('run-server', NULL, InputOption::VALUE_NONE, 'If a local HTTP server should be started after the export.')
      ->addOption('port', NULL, InputOption::VALUE_OPTIONAL, 'The port to run the server on.', 8889)
      ->addOption('ignore-warnings', NULL, InputOption::VALUE_NONE, 'If configuration warnings should be shown.')
      ->addOption('path-pattern', NULL, InputOption::VALUE_OPTIONAL, 'If you only want to export a specific paths based on pattern.', '')
      ->addOption('retry-count', NULL, InputOption::VALUE_OPTIONAL, 'The number of retry per failed process', static::RETRY_COUNT)
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Assume "yes" as answer to all prompts,');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $options = $input->getOptions();

    if ($this->state->get(StaticGeneratorInterface::STATE_KEY_BUILDING, FALSE)) {
      if (!$options['yes'] && !$this->io()->confirm('Another user may be running a static build, proceed only if the last build failed unexpectedly. Ignore and continue build?', FALSE)) {
        return 0;
      }
    }
    $this->state->set(StaticGeneratorInterface::STATE_KEY_BUILDING, TRUE);

    $warnings = $this->getWarnings();

    if (empty($options['uri']) || $options['uri'] === 'http://default') {
      $warnings[] = 'No "--uri" option provided. This could lead to invalid absolute URLs. To resolve, pass the "--uri" option.';
    }

    if (!$options['ignore-warnings'] && $warnings) {
      $warnings[] = 'To suppress these messages, pass the --ignore-warnings option.';
      $this->io->comment($warnings);
    }

    // @todo Is this a Drush bug?
    if (base_path() !== '/') {
      \Drupal::urlGenerator()->getContext()->setBaseUrl(rtrim(base_path(), '/'));
    }

    $this->static->prepareStaticDirectory();

    $paths = $this->static->getPaths();

    // Add a filter to export only pattern based paths.
    if ($options['path-pattern'] !== '') {
      $paths = preg_filter($options['path-pattern'], '$0', $paths);
    }

    $this->io->writeln('Generating static HTML...');
    $this->exportPaths($paths, [], $options['process-count'], $options['path-count'], TRUE, $options['retry-count'], $options['uri']);
    $this->io->success('Exported static HTML and related assets.');

    $this->state->set(StaticGeneratorInterface::STATE_KEY_BUILDING, FALSE);

    $this->static->cleanupStaticDirectory();

    if ($options['run-server']) {
      $this->runCommand($this->executable . ' tome:preview --port=' . escapeshellarg($options['port']), NULL, NULL);
    }
  }

  /**
   * Exports the given paths to the static directory.
   *
   * @param string[] $paths
   *   An array of paths.
   * @param array $old_paths
   *   An array of paths that have already been processed.
   * @param int $process_count
   *   The number of processes to invoke.
   * @param int $path_count
   *   The number of paths to export per process.
   * @param bool $show_progress
   *   Whether or not a progress bar should be shown.
   * @param int $retry_count
   *   The number of times to retry a failed command.
   * @param string $uri
   *   The URI of the site, probably passed by -l or --uri.
   */
  protected function exportPaths(array $paths, array $old_paths, $process_count, $path_count, $show_progress, $retry_count, $uri) {
    $paths = $this->static->exportPaths($paths);

    if (empty($paths)) {
      return;
    }

    if ($this->io->isVerbose()) {
      $this->io->writeln('Exporting paths:');
      $this->io->listing($paths);
    }

    $commands = [];
    $chunks = array_chunk($paths, $path_count);
    foreach ($chunks as $chunk) {
      $command = $this->executable . ' tome:static-export-path ' . escapeshellarg(implode(',', $chunk)) . ' --return-json --process-count=' . escapeshellarg($process_count) . ' --uri=' . escapeshellarg($uri);
      $commands[] = $command;
    }

    $show_progress && $this->io->progressStart(count($paths));

    $invoke_paths = [];
    $collected_errors = $this->runCommands($commands, $process_count, $retry_count, function (Process $process) use ($show_progress, &$invoke_paths, $path_count) {
      $show_progress && $this->io->progressAdvance($path_count);
      $output = $process->getOutput();
      if (!empty($output) && $json = json_decode($output, TRUE)) {
        $invoke_paths = array_merge($invoke_paths, $json);
      }
    });

    $invoke_paths = array_diff($invoke_paths, $old_paths);
    $old_paths = array_merge($old_paths, $invoke_paths);

    $show_progress && $this->io->progressFinish();
    if (!empty($collected_errors)) {
      $this->displayErrors($collected_errors);
    }
    if (count($invoke_paths)) {
      $this->io->writeln('Processing related assets and paths...');
      $this->exportPaths($invoke_paths, $old_paths, $process_count, $path_count, $show_progress, $retry_count, $uri);
    }
  }

}
